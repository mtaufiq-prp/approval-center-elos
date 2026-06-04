# [Low][Security] completeCurrentTask tidak memverifikasi task berada di node aktif instance (defense-in-depth)

## Summary
`completeCurrentTask` hanya cek `task_status IN (OPEN,CLAIMED)` + ambil ACTIVE token, tidak memverifikasi `task->idtblflow_step === instance->idtblflow_step_current`. **POTENTIAL RISK.**

## Location
- `app/Http/Controllers/Web/InboxController.php` (`act`) + `app/Services/FlowEngineService.php:122-159`

## Problem
Pada flow dengan RETURN/loop, bisa ada task lama OPEN yang tak lagi di jalur aktif; menyelesaikannya memajukan token dari node salah.

## Impact
Defense-in-depth hilang; bila ada bug yang meninggalkan task OPEN di node lama, approver bisa memajukan token tak terduga.

## Risk Scenario
Setelah RETURN+resubmit, task lama node N masih OPEN → approve dari URL lama → token maju tak terduga.

## Recommended Fix
Tambah verifikasi `task->idtblflow_step == $instance->idtblflow_step_current` (atau cocok posisi token aktif) sebelum proses.

## Acceptance Criteria
- [ ] Menyelesaikan task bukan di node aktif instance ditolak
- [ ] Alur normal & RETURN tetap berfungsi

## Priority
P3 - Nice to have
