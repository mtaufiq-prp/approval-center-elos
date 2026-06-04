# [Low][Performance] Optimasi query list/audit: whereDate() mematikan index, index komposit monitoring hilang, distinct pluck dropdown per request

## Summary
Beberapa optimasi query pada halaman list/audit/monitoring yang dampaknya membesar saat data besar.

## Location
- `app/Http/Controllers/Web/MonitoringController.php:44,47` & `AuditController.php:43-47,73-77,104-108,128-132` (`whereDate(created_at)`)
- `MonitoringController.php:37-42` (filter `idtblsource_app`+`idtbldocument_type` tanpa index komposit)
- `AuditController.php:51,81-82` (`distinct()->pluck()` dropdown tiap request)
- `ProcessCallbackOutboxJob.php:22-27` (scan tak pakai `idx_tbl_callback_status` optimal)

## Problem
- `whereDate('created_at', ...)` membungkus kolom dalam `DATE()` → index tidak terpakai
- Filter monitoring app+status+tanggal tanpa composite index
- `DISTINCT action_code/event_code/entity_type` full-scan tiap render
- Outbox scan abaikan `next_retry_at`

## Impact
Query list/audit/monitoring lambat pada tabel besar.

## Recommended Fix
- Ganti `whereDate('created_at','>=',$from)` → `where('created_at','>=',$from.' 00:00:00')` & `< ($to + 1 day)`
- `ALTER TABLE tblapproval_request ADD KEY idx_req_monitor (idtblsource_app, request_status, created_at)`
- Cache daftar kode dropdown (`Cache::remember(...,3600)`) atau tabel referensi
- Outbox scan pakai scope `readyForDispatch()` agar selaras `idx_tbl_callback_status`

## Acceptance Criteria
- [ ] EXPLAIN filter tanggal memakai range index
- [ ] EXPLAIN monitoring app+status memakai `idx_req_monitor`
- [ ] Halaman audit tidak menjalankan DISTINCT full-scan tiap request

## Priority
P3 - Nice to have
