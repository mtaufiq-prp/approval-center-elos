# [Medium][Reliability] Callback outbox scanner: filter status 'RETRY' (bukan ENUM), abaikan next_retry_at, tanpa lock → double-send & item gagal tak diproses ulang

## Summary
`ProcessCallbackOutboxJob` memfilter `status IN ('PENDING','RETRY')` (RETRY bukan ENUM), tidak menghormati `next_retry_at`, tanpa locking, dan `retry_count < 5` (schema default `max_retry=10`).

## Location
- `app/Jobs/ProcessCallbackOutboxJob.php:22-27`
- Model `TblCallbackOutbox` punya scope `readyForDispatch()` (tak dipakai) + index `idx_tbl_callback_status (status, next_retry_at)`

## Problem
- `RETRY` bukan anggota ENUM → baris gagal (FAILED) tak pernah terambil retry otomatis
- `next_retry_at` diabaikan → backoff tak efektif, target di-hammer
- Tanpa lock (`FOR UPDATE SKIP LOCKED`) → scheduler + retry manual bisa dispatch row sama 2x → double callback
- `retry_count < 5` ≠ `max_retry=10`

## Impact
Callback gagal sementara tak pernah di-retry; sebagian anggaran retry tak terpakai; potensi double-send (efek ganda di source app).

## Risk Scenario
Callback gagal 2 menit (source down) → status FAILED → scanner tak ambil → callback hilang permanen tanpa DEAD-letter.

## Recommended Fix
Pakai scope `readyForDispatch()` (`status IN ('PENDING','FAILED') AND next_retry_at<=now()`), claim atomik (`lockForUpdate`/`UPDATE ... SET status='SENDING'` cek affected rows), `retry_count < max_retry`.

## Acceptance Criteria
- [ ] Hanya proses saat `next_retry_at<=now()`
- [ ] Satu row tidak dikirim dua worker
- [ ] Habis `max_retry` → DEAD
- [ ] Tidak ada referensi 'RETRY' kecuali ada di ENUM

## Priority
P2 - Should fix soon
