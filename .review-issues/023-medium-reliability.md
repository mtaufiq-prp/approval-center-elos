# [Medium][Reliability] SLA escalation hanya mencatat log — tidak reassign/notifikasi & tidak ada target eskalasi

## Summary
`SlaEscalationJob::processTask` hanya `TblSlaEscalationLog::create` + `Log::warning`. Tidak ada reassign, penambahan kandidat atasan, atau notifikasi.

## Location
- `app/Jobs/SlaEscalationJob.php:54-90`

## Problem
`escalation_level=ceil(hoursOverdue/24)` tapi tidak menentukan target eskalasi siapa pun. Tidak ada tindakan nyata.

## Impact
Task overdue menggantung tanpa tindakan; SLA tidak menggerakkan approval. "Escalation" hanya pencatatan pasif.

## Recommended Fix
Setelah SLA log: tambahkan superior approver sebagai kandidat (atau reassign) dan/atau kirim notifikasi. Target dari hirarki (`idtbluser_superior`) atau konfigurasi node. Idempoten per level.

## Acceptance Criteria
- [ ] Eskalasi level naik → perubahan nyata pada penugasan/notifikasi
- [ ] Target eskalasi deterministik per level

## Priority
P2 - Should fix soon
