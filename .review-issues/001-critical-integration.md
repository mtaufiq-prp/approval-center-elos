# [Critical][Integration] completeProcess() tidak pernah membuat tblcallback_outbox — source app tak pernah menerima hasil approval

## Summary
Tidak ada satu pun `TblCallbackOutbox::create()` di seluruh codebase. Saat proses approval selesai, hasil tidak pernah dikirim balik ke aplikasi sumber.

## Location
- `app/Services/FlowEngineService.php:461` (`completeProcess()`)
- Dikonfirmasi: `grep -rn "CallbackOutbox::create" app/` = 0 hasil. Yang ada hanya `find/where/count/dispatch` (retry manual) dan `ProcessCallbackOutboxJob` (scanner) — keduanya CONSUMER, tidak ada PRODUCER.

## Problem
`completeProcess()` hanya meng-update `instance_status`, `request_status`, dan menutup token. Tidak ada penulisan baris ke `tblcallback_outbox`. Karena tidak ada produsen, tabel selalu kosong dan scheduler tidak punya apa-apa untuk dikirim.

## Impact
Inti arsitektur hub-and-spoke (CLAUDE.md §3) putus secara diam-diam. Aplikasi sumber (SFA/PR/BSKB) tidak pernah tahu keputusan approval; status di sisi sumber menggantung selamanya. Tidak ada error — hanya tidak ada callback.

## Risk Scenario
1. Retur disetujui CEO → proses COMPLETED.
2. SFA tidak pernah menerima callback APPROVED.
3. Dokumen retur di SFA tetap "menunggu approval" abadi.

## Expected Behavior
Setiap kali request mencapai status final (APPROVED/REJECTED/CANCELLED/ERROR), satu baris callback outbox dibuat dalam transaksi yang sama dengan keputusan, lalu dikirim async oleh worker.

## Recommended Fix
Di dalam transaksi `completeProcess()`/`completeCurrentTask()`:
```php
TblCallbackOutbox::create([
  'idtblapproval_request' => $request->idtblapproval_request,
  'idtblsource_app'       => $request->idtblsource_app,
  'event_type'            => $request->request_status,
  'target_url'            => $request->callback_url,
  'payload_json'          => [...],
  'status'                => 'PENDING',
  'next_retry_at'         => now(),
]);
```
Tambah kolom + unique `(idtblapproval_request, event_type)` untuk cegah double-enqueue.

## Acceptance Criteria
- [ ] Setelah request final, ada tepat 1 baris `tblcallback_outbox` status PENDING
- [ ] Baris dibuat dalam transaksi yang sama dengan update `request_status` (rollback = rollback outbox)
- [ ] Worker memproses hingga SENT
- [ ] Test: submit → approve sampai END → outbox berisi 1 row

## Priority
P0 - Must fix before production
