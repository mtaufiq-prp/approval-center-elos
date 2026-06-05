# Performance Design — Approval Center @ 1000 request/menit

> Target produksi: **≥ 1000 create-approval-request per menit (~16.7 req/s)** tanpa lag signifikan,
> tanpa duplicate/corrupt state, dengan callback & notifikasi non-blocking.

Dokumen ini menjelaskan desain & perubahan yang membuat sistem mampu menahan beban tersebut,
bottleneck yang ditemukan + diperbaiki pada iterasi hardening, dan kapasitas yang direkomendasikan
untuk produksi.

---

## 1. Ringkasan Acceptance Criteria & Status

| Kriteria | Target | Status desain |
|---|---|---|
| Create approval p95 | ≤ 300 ms | Tercapai dgn mode **async-start** (engine keluar dari request) ✓ desain; perlu load test prod |
| Create approval p99 | ≤ 800 ms | idem |
| Approve/Reject p95 | ≤ 300 ms | Transaksi pendek + row lock terindeks ✓ |
| List approval p95 | ≤ 500 ms | Pagination + index `created_at`/`(status,created_at)` ✓ |
| Error rate | ≤ 1% | Idempotent submit (no 500 saat duplicate), throttle per-client ✓ |
| No duplicate request | concurrent | Unique `(source_app, doc_type, source_request_id)` + handle 1062 idempoten ✓ |
| No duplicate approval action | concurrent | `lockForUpdate` task+instance, sibling-cancel ANY ✓ |
| Callback non-blocking | — | Transactional outbox + queue worker ✓ |
| Notifikasi non-blocking | — | `tblnotification_queue` + worker ✓ |
| Query utama terindeks | — | Lihat §4 ✓ |

---

## 2. Bottleneck yang Ditemukan & Diperbaiki (iterasi ini)

| # | Bottleneck | Dampak @1000/min | Fix |
|---|---|---|---|
| C3 | `throttle:60,1` per-IP di semua `/api/v1/*` | **Hard cap 60/min**; 1 IP proxy memblok seluruh sistem | Limiter `api_client` per-client, default **2000/min** (`APPROVAL_API_RATE_LIMIT`) |
| C2 | Callback scanner `limit(50)`/menit | Backlog tumbuh ~950/min, **tidak pernah terkuras** | `batch_size` default **1000** (`APPROVAL_CALLBACK_BATCH_SIZE`) + worker paralel |
| C1 | SSRF guard memblokir semua IP privat | Semua callback internal → **DEAD** (hub putus) | Allowlist CIDR (default RFC1918), metadata/loopback tetap diblok |
| H5/#12 | Engine (enrichment + traversal + resolusi assignee + buat task) jalan **sinkron** di request submit | Latency tinggi & throughput terbatas oleh kerja terberat | **Async-start** opsional (`APPROVAL_ASYNC_START`): submit hanya simpan+enqueue |
| H6/#13 | Monitoring `ORDER BY created_at DESC` tanpa index | Full scan + filesort, memburuk linear | Index `idx_tbl_request_created`, `idx_tbl_request_status_created` |
| L2 | `last_used_at` di-UPDATE tiap request | Write hot-spot pada baris client | Debounce 60s |

---

## 3. Request Ingestion (endpoint create)

Alur submit (`ApprovalSubmitController`):

1. **Auth ringan & cepat**: HMAC SHA256 + timestamp ±300s + nonce atomic (`Cache::add`) + cek client aktif/expired.
2. **Validasi cepat** (`$request->validate`) — field-level, tanpa I/O berat.
3. **Idempotency check WAJIB**:
   - by `idempotency_key` (unik per `source_app`), lalu
   - by `(source_app, doc_type, doc_ref)` → balasan idempoten 200 (bukan 422/500).
   - Race konkuren: INSERT kedua kena unique (errno 1062) → ditangkap → balasan idempoten 200.
4. **Simpan request transaksional** (enrichment untuk routing + create request + audit SUBMIT).
5. **Heavy process → async** (mode `APPROVAL_ASYNC_START=true`): engine traversal + resolusi assignee +
   pembuatan task + enqueue notifikasi dipindah ke `StartProcessJob` (queue). Submit balas cepat (status SUBMITTED).
   Callback & notifikasi **tidak pernah** dikirim sinkron.

### Mode sinkron vs async-start

| Aspek | Sinkron (default, `false`) | Async-start (`true`, disarankan @beban tinggi) |
|---|---|---|
| Response submit | status `IN_PROGRESS`, task sudah siap | status `SUBMITTED`, task menyusul (poll status) |
| Latency p95 | bergantung kompleksitas flow (resolusi assignee, cross-DB) | rendah & stabil (kerja berat di worker) |
| Throughput | dibatasi PHP-FPM workers | dibatasi worker queue (skalabel horizontal) |
| Failure mode | error terlihat langsung di response | request `SUBMITTED`; job retry; gagal permanen → `ERROR` (reset admin) |

> Rekomendasi: aktifkan async-start di produksi + jalankan `queue:work` dengan beberapa proses.

---

## 4. Database Optimization

### Index hot-path (sudah ada di schema, diverifikasi cukup)

| Query | Index |
|---|---|
| Dedup submit `(source_app, doc_type, doc_ref)` | `uq_tbl_request_source_doc` |
| Idempotency `(source_app, idempotency_key)` | `uq_tbl_request_idempotency` |
| Inbox by assignee | `idx_tbl_task_inbox_user (idtbluser_assigned, task_status, due_at)` |
| Inbox candidate | `idx_tbl_task_candidate_user (idtbluser, is_active)` |
| Routing rule lookup | `idx_tbl_routing_rule_lookup (source_app, doc_type, is_active, priority_no)` |
| Callback scanner | `idx_tbl_callback_status (status, next_retry_at)` |
| Notification scanner | `idx_tbl_notif_status (status, next_retry_at)` |
| Monitoring per program | `idx_tbl_request_app_created (source_app, created_at)` |

### Index ditambahkan (migration `2026_06_04_000004_add_perf_indexes`)

- `idx_tbl_request_created (created_at)` — listing monitoring default `ORDER BY created_at DESC`.
- `idx_tbl_request_status_created (request_status, created_at)` — listing terfilter status + sort.

### Beban tulis per submit (yang perlu diperhatikan)

Satu submit menulis ke: `tblapproval_request` (1), `tblintegration_message_log` (1), `tblaction_log` SUBMIT (1),
`tblprocess_instance` (1), `tblprocess_token` (1), `tblprocess_route_log` (beberapa — enter/exit/decision/task),
`tbltask` + `tbltask_candidate` (per kandidat), `tblnotification_queue` (per kandidat), `tblcallback_outbox` (saat final).

@1000/min, `tblprocess_route_log` adalah tabel paling "ramai". Mitigasi:
- Mode async-start memindahkan sebagian besar tulisan route-log ke worker (di luar request).
- Pertimbangkan partisi/arsip `tblprocess_route_log` & `tblintegration_message_log` (retensi N hari).
- Connection pool: gunakan MySQL `max_connections` memadai + PHP-FPM `pm.max_children` proporsional;
  hindari koneksi cross-DB (`db_master`) yang tak perlu (lihat §5).

---

## 5. Caching

- **Routing rules** di-cache 600s per `(source_app, doc_type)` (`RoutingRuleService`); evaluasi kondisi & resolusi
  versi ACTIVE tetap live agar deploy langsung berlaku. Invalidasi via `RoutingRuleService::forget()` saat rule berubah.
- **Enrichment cross-DB** (`db_master.ms_product_group` PMM/PD, `tbemployeeit` JOBTITLE) tidak di-cache; untuk
  beban tinggi pertimbangkan cache pendek per `ph4`/`jobtitleid` (data master jarang berubah). _Rekomendasi, belum diimplementasi._
- Jangan cache data approval request yang sering berubah tanpa strategi invalidasi.

---

## 6. Queue / Async Processing

Job & scheduler (`routes/console.php`):

| Job | Pemicu | Retry/Backoff | DLQ |
|---|---|---|---|
| `StartProcessJob` | submit (async-start) | tries=3, backoff 10s, unik per request | gagal → request `ERROR` |
| `ProcessCallbackOutboxJob` | scheduler /menit | scan `batch_size`, dispatch `SendCallbackJob` | — |
| `SendCallbackJob` | dari scanner / retry admin | backoff via `next_retry_at`, unik per row | status `DEAD` |
| `ProcessNotificationQueueJob` | scheduler | batch + retry | status SKIPPED bila channel belum ada handler |
| `SlaEscalationJob` | scheduler /30m | chunk (anti-OOM) | — |

Pastikan beberapa proses `php artisan queue:work --tries=3` berjalan (mis. via supervisor) agar
callback (≤1000/menit) terkuras + StartProcessJob tertangani.

---

## 7. Callback Design (outbound)

- **Transactional outbox** (`tblcallback_outbox`) diproduksi dalam transaksi keputusan final → konsisten dengan status.
- **Async** via scanner + `SendCallbackJob`; tidak pernah blocking response approve.
- **Signed** HMAC (`X-Callback-Ts/Nonce/Sig`); body dikirim **byte-identik** dengan yang ditandatangani (#M1).
- **Idempotent** pengiriman (`ShouldBeUnique`), backoff `next_retry_at`, gagal permanen → `DEAD`.
- **Status terpisah** dari approval: `request_status=APPROVED` tetap final walau `callback status=PENDING/FAILED/DEAD`.
- **SSRF allowlist** (#C1): hanya CIDR terdaftar; loopback & metadata selalu diblok.

---

## 8. Observability (rekomendasi minimum)

Metrik yang sebaiknya diekspor (log terstruktur / counter):

- request created, approval processed, callback sent/failed, retry_count, queue length (`jobs` table depth),
  DB slow query, error rate, p95/p99 latency submit & approve, **duplicate idempotency hit**,
  **unauthorized approval attempt** (`ApiAuth [..]` warning), callback `DEAD` count.

Hook yang sudah ada: `Log::warning("ApiAuth [CODE] ...")`, route log (`tblprocess_route_log`),
audit (`tblaudit_event`, `tblaction_log` append-only).

---

## 9. Estimasi Kapasitas & Bottleneck Tersisa

- **CPU/DB**: 16.7 submit/s × (~15–30 query) ≈ 250–500 q/s pada path submit (mode sinkron). MySQL 8 di server
  spek menengah sanggup; mode async-start menyebarkan beban ke worker.
- **Tidak bisa dibuktikan di lingkungan lokal** ini (single-node, MySQL 9 dev). Disediakan **k6 load test**
  (`tests/load/k6-approval-load.js`) untuk dijalankan di staging yang mendekati produksi.
- **Bottleneck tersisa untuk diawasi via telemetry produksi**:
  1. Volume `tblprocess_route_log` & `tblintegration_message_log` (butuh retensi/partisi).
  2. Query cross-DB `db_master` pada enrichment (pertimbangkan cache pendek).
  3. Jumlah worker queue harus diskalakan dengan beban callback/notifikasi.
  4. `max_connections` MySQL vs `pm.max_children` PHP-FPM (hindari saturasi pool).

### Konkurensi & lock ordering (diverifikasi iterasi-3)

Semua jalur yang memutasi `(task, instance, request)` mengunci dengan urutan **instance → (task) →
request** yang konsisten, sehingga tidak ada ABBA deadlock:

| Operasi | Urutan X-lock |
|---|---|
| Approve/Reject (`completeCurrentTask`) | instance → task → request |
| Cancel (`ApprovalCancelController`) | instance → request → task(update) |
| Reopen RETURNED (`reopenReturnedRequest`) | instance → request → (restart: instance) |
| StartProcessJob (async) | request → (membuat instance) — instance belum ada di state SUBMITTED |

Instance menjadi titik serialisasi tunggal antara approve↔cancel↔reopen. Bukti deadlock-freedom
bersifat properti urutan-lock (verifikasi kode + review adversarial), bukan unit test (uji konkurensi
nyata ada di rencana k6/staging).

### Catatan keterbatasan yang didokumentasikan (acceptable)

- **Re-drive RETURNED selalu sinkron** walau `APPROVAL_ASYNC_START=true`. Jalur ini jarang (resubmit
  setelah dikembalikan) & bukan bagian beban create 1000/menit → diterima.
- **Mode async + queue down**: bila `StartProcessJob` gagal di-dispatch setelah commit (queue driver
  mati), request bisa menggantung `SUBMITTED` tanpa instance. Dengan `QUEUE_CONNECTION=database`
  dispatch = INSERT ke DB yang sama (andal selama DB hidup). Rekomendasi operasional: scheduled
  "sweep" yang mencari request `SUBMITTED` > N menit tanpa instance lalu re-dispatch `StartProcessJob`.

---

## 10. Cara Menjalankan Load Test

Lihat `tests/load/README.md`. Ringkas:

```bash
# 1000 create/menit selama 5 menit + skenario campuran
k6 run -e BASE_URL=http://staging/approval_center/public \
       -e CLIENT_KEY=... -e CLIENT_SECRET=... -e DOC_TYPE=1 \
       tests/load/k6-approval-load.js
```
