# [Low][Approval Logic] Inkonsistensi guard status: web act() hanya izinkan OPEN, engine izinkan OPEN/CLAIMED

## Summary
`authorizeAction` menolak segala status selain OPEN, sedangkan engine menerima OPEN/CLAIMED; inbox index juga hanya menampilkan OPEN. Tidak ada celah keamanan terminal, tetapi task CLAIMED valid tak bisa di-act via web.

## Location
- `app/Http/Controllers/Web/InboxController.php:300-306` vs `app/Services/FlowEngineService.php:129`; inbox index filter OPEN (`:43`)

## Problem
Bila mekanisme CLAIM dipakai, task CLAIMED milik user hilang dari inbox & tak bisa diselesaikan (harus tetap OPEN).

## Impact
Terbatas (belum ada endpoint claim aktif). Tidak ada risiko reproses task final.

## Recommended Fix
Izinkan `in_array($task->task_status, ['OPEN','CLAIMED'])` di `authorizeAction`; tampilkan CLAIMED milik user di inbox. Tetap tolak semua status terminal.

## Acceptance Criteria
- [ ] Task CLAIMED milik user tampil & bisa di-act
- [ ] Semua status terminal tetap 403

## Priority
P3 - Nice to have
