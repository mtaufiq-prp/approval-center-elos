# [High][Reliability] retryCallback men-dispatch model ke SendCallbackJob yang menerima int → TypeError, retry manual gagal

## Summary
`AuditController::retryCallback` memanggil `SendCallbackJob::dispatch($outbox)` (model), padahal konstruktor job `__construct(private int $callbackId)`.

## Location
- `app/Http/Controllers/Web/AuditController.php:157`
- `app/Jobs/SendCallbackJob.php:27`

## Problem
Job mengharapkan int id; dikirim objek Model → `find($model)` rapuh/`TypeError` saat handle. `ProcessCallbackOutboxJob` sudah benar (`$cb->idtblcallback_outbox`).

## Impact
Tombol "Retry" admin untuk callback FAILED/DEAD melempar TypeError → retry manual tidak berfungsi.

## Risk Scenario
Admin klik retry pada item DEAD → job gagal unserialize/handle → tidak ada efek.

## Recommended Fix
`SendCallbackJob::dispatch($outbox->idtblcallback_outbox);`

## Acceptance Criteria
- [ ] Klik retry → job jalan tanpa TypeError
- [ ] Test dispatch dengan id integer

## Priority
P1 - Important before production
