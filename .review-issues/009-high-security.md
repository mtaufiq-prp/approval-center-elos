# [High][Security] Tidak ada isolasi data antar-program (source_app) di Monitoring & Audit — satu admin/auditor melihat semua program

## Summary
Role bersifat global tanpa scoping per source_app. Admin/auditor dapat membaca request & payload milik SEMUA program lewat enumerasi ID.

## Location
- `app/Http/Controllers/Web/MonitoringController.php:61` (`show(TblApprovalRequest $approval_request)` — route-model-binding tanpa filter)
- `app/Http/Controllers/Web/AuditController.php` (integration-log, callback-outbox lintas semua app)
- `app/Models/TblUser.php`/`TblRole`/`TblUserRole` — TIDAK ADA kolom `idtblsource_app`

## Problem
`MonitoringController::show` memuat request by PK tanpa cek program. `index` hanya menyediakan filter opsional `idtblsource_app` (bukan enforcement). Tidak ada konsep scoping program di model user/role.

## Impact
Di hub multi-program (SFA, PR, BSKB), auditor/admin satu program dapat membaca seluruh `context_json`/`payload_json` (nilai retur, customer, harga) milik program lain via `monitoring/request/{id}`.

## Risk Scenario
Auditor program A buka `/monitoring/request/1..N` → membaca payload bisnis program B/C.

## Recommended Fix
Tambah scoping program ke user/role (pivot `tbluser_source_app`). Di `show`/Audit: `abort_unless($user->canAccessSourceApp($req->idtblsource_app), 403)` + global scope `whereIn('idtblsource_app', $user->allowedSourceAppIds())`. Jika admin global memang diinginkan, pisahkan role `ADMIN_GLOBAL` vs `AUDITOR_<APP>` secara eksplisit & terdokumentasi.

## Acceptance Criteria
- [ ] User scope program A → 403 saat akses request program B
- [ ] List monitoring/audit ter-filter otomatis ke program user
- [ ] Test: auditor A tidak bisa enumerate request ID program B

## Priority
P1 - Important before production
