# [Medium][Security] inbox.show authorizeView tidak memfilter is_active → kandidat nonaktif masih bisa melihat payload penuh

## Summary
`authorizeView` mengizinkan kandidat tanpa cek `is_active` (berbeda dari `authorizeAction` yang menambah `is_active=1`).

## Location
- `app/Http/Controllers/Web/InboxController.php` (`authorizeView` ~284-297 vs `authorizeAction` ~300-317)
- `resources/views/inbox/show.blade.php`

## Problem
```php
$isCandidate = $task->candidates()->where('idtbluser', $user->idtbluser)->exists(); // tanpa is_active
```
Kandidat yang sudah dinonaktifkan tetap bisa membuka detail & melihat `payload_json`/`context_json`.

## Impact
Approver yang haknya dicabut masih membaca data sensitif request. Inkonsistensi guard view vs action.

## Risk Scenario
User X kandidat lalu di-nonaktifkan (delegasi berakhir); simpan URL `/inbox/task/{id}` → tetap lihat payload.

## Recommended Fix
Tambahkan `->where('is_active', 1)` pada cek kandidat di `authorizeView` (samakan dengan `authorizeAction`), atau batasi view-only kandidat nonaktif hanya bila `completed_by`.

## Acceptance Criteria
- [ ] Kandidat `is_active=0` (bukan assignee/completed_by) → 403 di inbox.show
- [ ] Approver yang menyelesaikan task tetap bisa lihat history

## Priority
P2 - Should fix soon
