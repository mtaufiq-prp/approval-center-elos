# [Medium][Audit] Audit trail tidak immutable secara teknis & tidak menyimpan snapshot payload awal request

## Summary
`tblaudit_event`/`tblaction_log` tabel InnoDB biasa tanpa proteksi UPDATE/DELETE; submit tidak menyimpan snapshot payload awal ke audit.

## Location
- Schema `tblaudit_event`, `tblaction_log`
- `app/Services/AuditTrailService.php` (hanya create)
- `app/Http/Controllers/Api/V1/ApprovalSubmitController.php` (tidak panggil AuditTrailService)

## Problem
Immutability hanya konvensi (tidak ada trigger/role-restriction menolak modifikasi). Tidak ada event "REQUEST_SUBMITTED" berisi snapshot `context_json/payload_json` awal; satu-satunya salinan ada di `tblapproval_request.payload_json` yang mutable (`allow_edit_payload`).

## Impact
Auditor tak punya jaminan teknis jejak tak diubah; tanpa snapshot awal immutable, isi asli tak bisa dibuktikan saat sengketa.

## Risk Scenario
Payload diubah pasca-submit; tanpa snapshot awal, tidak bisa dibuktikan isi asli.

## Recommended Fix
(1) Trigger `BEFORE UPDATE/DELETE ... SIGNAL SQLSTATE '45000'` pada tabel audit, atau cabut privilege UPDATE/DELETE untuk user app. (2) Pada submit, panggil `AuditTrailService::recordEvent('tblapproval_request', id, 'REQUEST_SUBMITTED', newValues:[snapshot])` dalam transaksi submit.

## Acceptance Criteria
- [ ] UPDATE/DELETE baris audit ditolak DB
- [ ] Setiap submit → 1 event audit berisi snapshot payload awal

## Priority
P2 - Should fix soon
