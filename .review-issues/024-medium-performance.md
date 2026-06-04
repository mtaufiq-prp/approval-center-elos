# [Medium][Performance] SlaEscalationJob memuat semua task overdue ke memori + N+1 query exists per task (risiko OOM)

## Summary
`->where('due_at','<',now())->get()` memuat semua task overdue tanpa chunk; lalu per task ada `exists()` + `create()` (N+1). Eager-load `candidates.user` tak terpakai.

## Location
- `app/Jobs/SlaEscalationJob.php:37-52,54-90`

## Problem
Backlog besar (Senin setelah libur, ribuan task) → muat semua ke memori + N query exists + N insert, tiap 30 menit.

## Impact
Risiko OOM & DB lambat saat backlog overdue besar.

## Recommended Fix
`->chunkById(500, ...)`; ambil set `(task_id, level)` ter-escalate dalam SATU query lalu cek di memori; hapus eager-load `candidates.user` yang tak dipakai.

## Acceptance Criteria
- [ ] Memproses 5.000 task overdue dengan jumlah query konstan kecil & memori stabil

## Priority
P2 - Should fix soon
