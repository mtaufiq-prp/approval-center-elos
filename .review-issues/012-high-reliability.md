# [High][Reliability] Node APPROVAL tanpa kandidat valid → seluruh proses ERROR permanen tanpa pemulihan; keputusan approver ter-rollback

## Summary
Bila resolusi assignee menghasilkan kandidat kosong, `createApprovalTask` memanggil `completeProcess(ERROR)` yang menutup instance secara terminal tanpa jalur resume. Untuk JOBTITLE DB-error, exception me-rollback transaksi keputusan → task approver yang sudah approve hilang.

## Location
- `app/Services/FlowEngineService.php:420` (kandidat kosong → `completeProcess(ERROR)`)
- `app/Services/AssigneeResolverService.php` (resolveJobTitle re-throw di dalam transaksi `completeCurrentTask`)

## Problem
`completeProcess('ERROR')` set instance & request terminal + token COMPLETED → tidak bisa resume. Semua resolver filter `is_active=1`. Jika pejabat node tidak aktif / JOBTITLE tanpa employee aktif / `_computed.*_user_ref` kosong, proses mati di tengah jalan. Re-throw JOBTITLE membatalkan transaksi keputusan → approver harus approve ulang tapi gagal lagi.

## Impact
Satu approver hilang/inactive = proses tewas permanen, kerja approval sebelumnya hangus, hanya bisa diperbaiki via DB manual.

## Risk Scenario
Retur >25jt: BMH→RRM→NRM approve → node CEO (JT0526) dengan `activestatus=0` → kandidat kosong → ERROR permanen.

## Recommended Fix
- Jangan terminal-ERROR: set instance `SUSPENDED`/`PENDING_ASSIGNEE` (non-terminal), token tetap ACTIVE; sediakan job/endpoint admin "re-drive node".
- Fallback assignee per node (role ADMIN_APPROVAL/superior) saat kandidat kosong.
- Pisahkan resolusi assignee dari commit keputusan (commit keputusan dulu, resolusi node berikutnya di langkah retry-able).

## Acceptance Criteria
- [ ] Node tanpa kandidat → instance non-terminal & dapat di-resume
- [ ] Keputusan approver tersimpan walau resolusi node berikutnya gagal
- [ ] Ada jalur admin reassign/re-drive

## Priority
P1 - Important before production
