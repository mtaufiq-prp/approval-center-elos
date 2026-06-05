# Load Test — Approval Center (1000 req/menit)

Membuktikan target produksi **≥1000 create approval request/menit (~16.7/s)** dengan
[k6](https://k6.io). Script: `k6-approval-load.js`.

## Prasyarat

1. Lingkungan staging mendekati produksi (PHP-FPM + MySQL + worker queue jalan).
2. Aktifkan async-start untuk hasil terbaik: `APPROVAL_ASYNC_START=true`, lalu jalankan
   beberapa worker: `php artisan queue:work --tries=3` (mis. 4–8 proses via supervisor).
3. Naikkan rate limit bila perlu: `APPROVAL_API_RATE_LIMIT=3000`.
4. Satu API client aktif + secret-nya (lihat menu Master → API Client). IP k6 harus
   masuk `allowed_ip` client (atau kosongkan untuk uji).
5. Satu `document_type` milik source_app client tersebut + routing rule aktif.

## Menjalankan

```bash
k6 run \
  -e BASE_URL=http://10.50.0.4/approval_center/public \
  -e CLIENT_KEY=<client_key> \
  -e CLIENT_SECRET=<plain_secret> \
  -e DOC_TYPE=<idtbldocument_type> \
  -e RATE=1000 \
  -e DURATION=5m \
  tests/load/k6-approval-load.js
```

## Skenario yang diuji

| Skenario | Beban | Tujuan |
|---|---|---|
| `create_load` | 1000/menit, 5 menit | throughput & latency create (p95<300ms, p99<800ms) |
| `idempotency` | doc_ref tetap berulang | **tidak ada duplikat** (metrik `idempotency_duplicate_created==0`) + balasan idempotent |
| `status_checks` | ~20% | read path (p95<500ms) |

## Thresholds (lulus/gagal otomatis)

- `http_req_duration{scenario:create_load}` p95<300ms, p99<800ms
- `http_req_duration{scenario:status_checks}` p95<500ms
- `submit_errors` rate < 1%
- `idempotency_duplicate_created` **== 0** (tidak boleh ada request duplikat pada beban konkuren)

## Yang TIDAK diuji k6 (dan kenapa)

- **Approve/Reject & concurrent approve pada request yang sama** adalah aksi WEB
  (session + CSRF), bukan HMAC API. Korektnitas race-nya diuji di PHPUnit
  (`tests/Feature/Approval/ApprovalEngineTest.php`, `ApprovalApiTest.php`) via
  `lockForUpdate` pada task+instance dan guard status. Untuk load test approve,
  gunakan skenario terpisah dengan login web/cookie jar.
- **Callback retry simulation**: jalankan worker + matikan endpoint target sementara,
  amati transisi `tblcallback_outbox` PENDING→FAILED→DEAD via menu Audit.

## Validasi konkurensi lokal (correctness, bukan throughput)

k6 menguji throughput di staging. Untuk membuktikan **korektnitas di bawah koneksi paralel
nyata** tanpa staging, tersedia harness lokal (PHP `curl_multi`) yang sudah dijalankan:

```bash
# 1) seed fixture (dev-only) ke DB yang dipakai server
DB_DATABASE=approval_center_test php artisan loadtest:seed         # cetak CLIENT_KEY/SECRET/DOC_TYPE
DB_DATABASE=approval_center_test php artisan serve --port=8123 &   # server pakai DB yang sama

# 2a) submit duplikat paralel (idempotency_key sama) — harus 1 baris, tanpa 500
BASE_URL=http://127.0.0.1:8123 CLIENT_KEY=LOADTEST_KEY SECRET=loadtest-secret-12345678 \
  DOC_TYPE=<id> N=25 php tests/load/local_concurrency_check.php

# 2b) submit paralel doc_ref sama TANPA key (jalur uq_tbl_request_source_doc)
... NO_KEY=1 php tests/load/local_concurrency_check.php

# 2c) cancel paralel pada satu request berjalan
BASE_URL=... CLIENT_KEY=... SECRET=... REQUEST_ID=<id> M=10 php tests/load/local_cancel_concurrency_check.php
```

**Hasil yang sudah diverifikasi (MySQL lokal):**

| Skenario | Hasil | Membuktikan |
|---|---|---|
| 25× submit identik (idempotency_key) | 1×201 + 24×200; DB: 1 request / 1 instance / 1 task; 0×500 | idempotency, no duplicate, no deadlock |
| 15× submit doc_ref sama (no key) | 1×201 + 14×200; DB: 1 request; 0×500 | jalur 1062 `uq_tbl_request_source_doc` |
| 10× cancel paralel (1 request) | 1×200 + 9×409; 0×500; akhir `CANCELLED`+`CANCELLED` | lock instance→request, no corruption |

> Catatan: angka *throughput* lokal tidak representatif untuk produksi — tetap jalankan k6 di staging.
> Jalur approve×cancel (approve = aksi web/session) tidak di-curl; deadlock-freedom-nya dijamin oleh
> urutan lock instance→request yang sama dengan jalur cancel di atas.

## Membaca hasil

- `idempotent_hits` naik = dedup bekerja.
- `idempotency_duplicate_created` harus 0.
- Jika p95 create > 300ms: cek `EXPLAIN` slow query, jumlah worker, `pm.max_children`
  vs MySQL `max_connections`, dan volume `tblprocess_route_log` (lihat PERFORMANCE_DESIGN.md §4/§9).
