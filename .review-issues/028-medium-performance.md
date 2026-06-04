# [Medium][Performance] Inbox/History: orWhereHas('candidates') memicu full scan tbltask + inboxCount dihitung ulang tiap halaman

## Summary
Filter `idtbluser_assigned = ? OR EXISTS(candidates...)` membuat MySQL sulit pakai index (OR lintas tabel) → full scan `tbltask`. Blok sama diulang di index/history/show.

## Location
- `app/Http/Controllers/Web/InboxController.php:44-49,97-102,135-141`

## Problem
`orWhereHas` → `OR EXISTS(...)`; index `idx_tbl_task_inbox_user` hanya cover sisi kiri → outer scan mahal. `inboxCount` dihitung ulang tiap render show/history.

## Impact
Query inbox lambat saat `tbltask` besar.

## Recommended Fix
Pecah jadi UNION dua query (assigned + candidate) lalu paginate, atau materialisasi `task_id` kandidat (`whereIn`) untuk hindari OR lintas tabel. Cache `inboxCount` per-request. Index `tbltask (task_status, idtbluser_assigned, due_at)`.

## Acceptance Criteria
- [ ] EXPLAIN inbox tidak `type=ALL` pada `tbltask`
- [ ] Render show tidak menjalankan ulang count berat

## Priority
P2 - Should fix soon
