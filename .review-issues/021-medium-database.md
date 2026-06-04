# [Medium][Database] Tidak ada unique constraint pencegah duplikasi task per (instance, step, user) — loop/RETURN bisa menumpuk task ganda

## Summary
`createApprovalTask` membuat task baru tiap node dimasuki dengan `task_no` selalu unik (microtime). Tidak ada guard DB untuk mencegah dua task OPEN simultan bagi user yang sama di node yang sama. **POTENTIAL RISK.**

## Location
- Schema `tbltask` (unik hanya `uq_tbl_task_no`)
- `app/Services/FlowEngineService.php` (`createApprovalTask`)

## Problem
Pada flow dengan RETURN/loop, node sama bisa dimasuki ulang → task duplikat OPEN untuk user sama tanpa pencegahan DB. Mode ANY membatalkan sibling hanya saat satu task selesai, bukan saat pembuatan.

## Impact
Approver melihat beberapa task OPEN node sama; double action-log; potensi double-advance.

## Risk Scenario
Request di-RETURN lalu resubmit melewati BMH lagi → BMH punya 2 task OPEN.

## Recommended Fix
Guard aplikasi (cek task OPEN existing `(instance, step, user)` sebelum create) + unique index pada status aktif (gunakan generated active-flag column untuk simulasi partial unique di MySQL).

## Acceptance Criteria
- [ ] Memasuki node APPROVAL dua kali untuk user sama tidak menghasilkan dua task OPEN simultan

## Priority
P2 - Should fix soon
