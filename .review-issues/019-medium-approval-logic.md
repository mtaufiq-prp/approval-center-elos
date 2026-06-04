# [Medium][Approval Logic] Tidak ada pencegahan self-approval (segregation of duties) — submitter bisa menyetujui request-nya sendiri

## Summary
`authorizeAction` hanya cek user=assignee/kandidat aktif; tidak ada cek actor ≠ submitter. Approver bisa ter-resolve sama dengan pembuat request.

## Location
- `app/Http/Controllers/Web/InboxController.php` (`authorizeAction`)
- `app/Services/FlowEngineService.php` (`completeCurrentTask`, resolusi kandidat)

## Problem
Tidak ada penolakan bila `$actorId === $request->idtbluser_submitter` (catatan: kolom submitter saat ini broken — lihat issue terkait; harus diperbaiki dulu). Assignee FIELD_USER/JOBTITLE/USER bisa sama dengan submitter.

## Impact
User dapat menyetujui sendiri request yang ia ajukan — melanggar SoD; kritikal untuk approval finansial jutaan rupiah.

## Risk Scenario
User dengan jobtitle = node approval mengajukan request, sistem menugaskan ke dirinya, ia approve sendiri.

## Recommended Fix
Tambah kebijakan konfigurable: tolak bila `actor === submitter` kecuali node `allow_self_approval`. Idealnya skip submitter saat resolve kandidat.

## Acceptance Criteria
- [ ] Submitter tidak bisa approve task atas request-nya sendiri (403) bila node tidak mengizinkan
- [ ] Flag per-node untuk pengecualian terkontrol
- [ ] Test self-approval ditolak

## Priority
P2 - Should fix soon
