# [High][Approval Logic] Fitur delegasi tidak operasional — TblDelegation & flag allow_delegate tidak pernah dipakai resolver

## Summary
`TblDelegation` (docstring mengklaim dipakai resolver) sama sekali tidak dirujuk `AssigneeResolverService`. Flag `allow_delegate` per node juga tidak pernah dibaca runtime.

## Location
- `app/Models/TblDelegation.php` (docstring klaim)
- `app/Services/AssigneeResolverService.php` (tidak ada referensi `TblDelegation`)
- `allow_delegate` hanya di-copy di clone/save, tak dibaca engine

## Problem
`grep` ke `app/` menunjukkan `TblDelegation` hanya dipakai di `DelegationController`/`DelegationRequest` (CRUD), tidak di resolver.

## Impact
Saat approver cuti & mendelegasikan, task tetap di-assign ke delegator. Bila delegator inactive saat itu, node bisa ERROR (lihat issue node-tanpa-kandidat).

## Risk Scenario
Approver set delegasi ke rekan saat cuti → request masuk → tetap di-assign ke approver yang cuti → menggantung/ERROR.

## Recommended Fix
Implementasi substitusi di `AssigneeResolverService::resolve()`: untuk tiap kandidat, cek `TblDelegation` aktif (scope source_app/doc_type), ganti/tambah delegate, dengan **cycle-guard** (visited set) agar A→B→A tidak loop. Hormati `step->allow_delegate`.

## Acceptance Criteria
- [ ] Delegasi aktif → task ter-assign ke delegate
- [ ] Rantai A→B→C resolve ke C tanpa loop; siklus terdeteksi
- [ ] `allow_delegate=false` menonaktifkan substitusi node tsb
- [ ] Test delegasi & cycle

## Priority
P1 - Important before production
