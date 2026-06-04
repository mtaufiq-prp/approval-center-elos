# [Medium][Transaction] deploy() tanpa row-lock & tanpa constraint single-ACTIVE → dua deploy paralel bisa menghasilkan dua versi ACTIVE

## Summary
`FlowVersionDeploymentService::deploy()` men-supersede versi lain tanpa `lockForUpdate`, dan tidak ada constraint DB yang menjamin satu ACTIVE per definition.

## Location
- `app/Services/FlowVersionDeploymentService.php:70-101`
- Schema `tblflow_version`: hanya `uq (idtblflow_definition, version_no)`

## Problem
Dua deploy bersamaan (versi A & B di definition sama) bisa saling membaca state lama: masing-masing men-INACTIVE-kan "ACTIVE lain" yang bukan dirinya, keduanya commit ACTIVE. `runValidation()` (save) juga di luar transaksi deploy → window lebih lebar.

## Impact
Dua versi ACTIVE; `RoutingRuleService` (`orderByDesc(version_no)`) diam-diam pilih salah satu → sebagian request pakai versi tak diharapkan; audit deploy ambigu.

## Risk Scenario
Dua admin klik Deploy versi berbeda hampir bersamaan → 2 ACTIVE.

## Recommended Fix
Bungkus validasi+supersede satu transaksi, `lockForUpdate` semua versi di definition dulu; atau advisory lock `GET_LOCK("deploy:{$defId}")`; atau pointer `tblflow_definition.idtblflow_version_active` unik.

## Acceptance Criteria
- [ ] 2 deploy paralel pada 1 definition → tepat 1 ACTIVE
- [ ] Validasi & supersede dalam satu transaksi berlock

## Priority
P2 - Should fix soon
