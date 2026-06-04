# [Medium][API] Semua exception submit dibungkus jadi HTTP 422 generic → source app tidak bisa membedakan error validasi vs transien

## Summary
`catch (\Throwable $e)` mengubah segala kegagalan (bug internal, DB down) menjadi 422 generic — semantik 422 = validation error → source app salah simpulkan & tidak retry.

## Location
- `app/Http/Controllers/Api/V1/ApprovalSubmitController.php:142-150`

## Problem
422 mengisyaratkan "payload salah, jangan kirim ulang" padahal bisa error transien server yang seharusnya 500 + retry.

## Impact
Source app tidak bisa membedakan "perbaiki payload" vs "retry nanti"; integrasi rapuh; request bisa hilang diam-diam.

## Recommended Fix
500 untuk error tak terduga, 422 hanya untuk validasi (idealnya via FormRequest sebelum try). Sertakan `error_code` stabil untuk branching.

## Acceptance Criteria
- [ ] Error transien → 500 (source retry)
- [ ] Validasi → 422 (source perbaiki payload)

## Priority
P2 - Should fix soon
