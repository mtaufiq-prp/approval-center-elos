# [Critical][Architecture] Instance berjalan tidak punya snapshot flow; versi lama bisa diedit setelah deploy → jalur approval request lama bisa berubah/rusak

## Summary
Engine membaca konfigurasi flow LIVE setiap traversal (bukan snapshot saat request dibuat). Lebih parah: setelah deploy versi baru, versi lama berubah status menjadi INACTIVE sehingga `isLocked()` menjadi false → node/edge versi lama bisa diedit/dihapus padahal masih ada instance RUNNING yang di-pin ke versi itu.

## Location
- `app/Services/FlowEngineService.php` traversal query LIVE by `idtblflow_step_from` (mis. `findNextEligibleNode`)
- `app/Services/FlowBuilderDataService.php:158` (`isLocked = isActive() && isInUse()`)
- `app/Services/FlowVersionDeploymentService.php:78-82` (deploy set versi lain → INACTIVE)
- `app/Models/TblProcessInstance.php` (hanya FK `idtblflow_version`, tidak ada snapshot)

## Problem
Instance hanya menyimpan FK versi, tidak ada snapshot graf. `isLocked` butuh `isActive()` true; begitu versi lama jadi INACTIVE setelah deploy, lock lepas → builder mengizinkan mutasi node/edge/assignee versi lama. Instance RUNNING yang di-pin versi itu lalu membaca node/edge yang sudah berubah.

(Berbeda dari issue #16 yang hanya soal routing AWAL untuk pinned non-ACTIVE.)

## Impact
Request in-flight bisa berubah jalur di tengah proses atau node tujuan hilang → jatuh ERROR/COMPLETED prematur (APPROVED). Tidak ada isolasi config vs instance berjalan.

## Risk Scenario
1. Request A di flow v2 berhenti di node RRM.
2. Admin clone→v3, deploy v3 → v2 jadi INACTIVE.
3. Admin buka builder v2 (kini tak terkunci), hapus edge RRM→NRM.
4. RRM approve A → engine cari edge dari RRM → hilang → `completeProcess(COMPLETED)` → A APPROVED, padahal seharusnya lanjut ke NRM/CEO.

## Recommended Fix
- Snapshot saat `startProcess`: salin steps+transitions+assignee_rules ke kolom JSON `tblprocess_instance.definition_snapshot_json` (atau tabel anak), dan engine membaca dari snapshot.
- Sampai snapshot ada: `isLocked` true bila versi `isInUse()` ATAU punya instance non-terminal — tanpa memandang ACTIVE/INACTIVE.
- `deploy()` jangan men-INACTIVE-kan versi lama yang masih punya instance RUNNING (pakai status `SUPERSEDED` yang tetap locked).

## Acceptance Criteria
- [ ] Edit/hapus node/edge/assignee pada versi yang punya instance RUNNING ditolak (403)
- [ ] Setelah deploy versi baru, instance lama menyelesaikan jalur persis seperti saat dibuat
- [ ] Snapshot instance tidak berubah saat baris flow LIVE diubah

## Priority
P0 - Must fix before production
