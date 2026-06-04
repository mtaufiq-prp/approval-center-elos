# [Critical][API] ApprovalStatusController memuat relasi yang tidak ada (processInstances/pendingTasks/assignee) → HTTP 500 selalu

## Summary
Endpoint `GET /api/v1/approval/status` selalu melempar 500 karena memuat relasi Eloquent yang tidak ada.

## Location
- `app/Http/Controllers/Api/V1/ApprovalStatusController.php:21,39,42`

## Problem
```php
->with(['processInstances.routeLogs', 'pendingTasks.assignee']); // baris 21
$req->pendingTasks->map(fn($t) => [... optional($t->assignee)->user_ref ...]);
```
- `TblApprovalRequest` punya `processInstance` (singular HasOne) — `processInstances` TIDAK ADA
- `pendingTasks` TIDAK ADA — yang ada `tasks`
- `TblTask` punya `assignedUser` — `assignee` TIDAK ADA

(Catatan: berbeda dari issue #17 yang soal kolom `doc_ref/status`; ini soal nama RELASI.)

## Impact
Endpoint status mati total. Setiap source app yang polling status mendapat 500 (`BadMethodCallException: undefined relationship [processInstances]`).

## Risk Scenario
SFA polling `GET /api/v1/approval/status/{id}` → 500 setiap kali → tidak bisa cek status approval sama sekali.

## Recommended Fix
Ganti ke `processInstance.routeLogs`; tambahkan relasi `pendingTasks()` (hasMany Task where task_status OPEN) atau gunakan `tasks` + filter; ganti `$t->assignee` → `$t->assignedUser`. Eager-load `pendingTasks.assignedUser`, `pendingTasks.flowStep`.

## Acceptance Criteria
- [ ] Endpoint mengembalikan 200 dengan `pending_tasks` terisi
- [ ] Tidak ada N+1
- [ ] Test feature untuk endpoint status

## Priority
P0 - Must fix before production
