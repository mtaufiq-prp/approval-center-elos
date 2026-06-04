# Security Notes — Approval Center

Catatan keputusan keamanan & model akses sistem. Lihat juga riwayat code review issues di GitHub.

## Model Akses Antar-Program (Multi-Source-App)

**Keputusan saat ini (by design):** Role `ADMIN_APPROVAL` dan `AUDITOR` bersifat **global lintas program**.
Pemegang role ini dapat melihat seluruh data approval dari semua `source_app` (PR Online, SFA, BSKB, dll)
melalui menu Monitoring dan Audit, termasuk `context_json`/`payload_json`, integration log, dan callback payload.

Implikasi: jangan berikan role `AUDITOR`/`ADMIN_APPROVAL` ke user yang hanya berhak atas satu program.

**Isolasi per-program (belum diimplementasi).** Bila ke depan dibutuhkan pembatasan auditor/admin
hanya ke program tertentu:
1. Tambah tabel pemetaan `tbluser_source_app_scope (idtbluser, idtblsource_app)`.
2. Terapkan global scope pada query `TblApprovalRequest` dan tabel audit untuk membatasi
   non-super-admin ke `source_app` yang menjadi haknya (`MonitoringController`, `AuditController`).
3. Approver biasa sudah otomatis ter-scope: hanya melihat task yang di-assign / di-kandidatkan kepadanya
   (lihat `InboxController::authorizeView`/`authorizeAction`).

Ref: review issue #106.

## Approver Authorization (Inbox)

- Aksi approve/reject (`InboxController::act`) wajib: task berstatus `OPEN` DAN user adalah
  assignee / kandidat **aktif** (`is_active=1`) / `ADMIN_APPROVAL`.
- View detail (`authorizeView`) memakai cek kandidat `is_active=1` (paritas dengan aksi) — review #107.
- Engine (`completeCurrentTask`) me-lock task & instance (`lockForUpdate`), menolak bila instance
  bukan `RUNNING` (cegah revive instance final) — review #1, #84.

## API (Hub Inbound)

- Semua route `/api/v1/*` dilindungi `api_client_auth` (HMAC SHA256 + timestamp ±300s + nonce anti-replay)
  dan `throttle:60,1`.
- Idempotency di-scope per `source_app` (review #92); retry `source_request_id` yang sama
  mengembalikan balasan idempoten (review #93).
- `document_type` divalidasi milik `source_app` pemanggil (review #104).
- Alias middleware `api_client_hmac` (dulu pass-through fail-open) telah dihapus; gunakan
  `api_client_auth` (review #105, #22).

## Callback Keluar (Hub Outbound)

- Pola transactional outbox (`tblcallback_outbox`) diproduksi saat proses final (review #81).
- Setiap callback ditandatangani HMAC (`X-Callback-Sig` + `X-Callback-Nonce` + `X-Callback-Ts`).
- SSRF guard menolak loopback + rentang privat/link-local/metadata (review #8, #109).
- Pengiriman idempoten (`ShouldBeUnique`) + backoff via `next_retry_at`; gagal permanen → `DEAD` (review #86, #98).

## Audit Trail

- `tblaudit_event` dan `tblaction_log` bersifat **append-only**: trigger DB `BEFORE UPDATE/DELETE`
  menolak mutasi (review #90). Pastikan migration `audit_append_only_triggers` dijalankan, atau
  batasi privilege DB user ke `INSERT`/`SELECT` pada kedua tabel.
- Perubahan konfigurasi flow dicatat dengan diff `diagram_json` before/after (review #101).
