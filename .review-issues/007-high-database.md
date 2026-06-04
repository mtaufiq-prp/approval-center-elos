# [High][Database] ApprovalSubmitController menulis kolom & status invalid ke tblintegration_message_log (payload inbound hilang / insert gagal)

## Summary
Submit controller menulis `message_type`, `payload_json`, dan status `RECEIVED`/`PROCESSED` ke `tblintegration_message_log` — kolom/nilai yang tidak ada di schema.

## Location
- `app/Http/Controllers/Api/V1/ApprovalSubmitController.php:72-74,131,144`
- Schema `tblintegration_message_log`: kolom `request_body_json`, `status ENUM('SUCCESS','FAILED','PENDING')`, `idempotency_key`

## Problem
- `message_type` → bukan kolom (tidak fillable) → di-drop
- `payload_json` → bukan kolom; yang benar `request_body_json` → payload inbound TIDAK tersimpan
- `status='RECEIVED'`/`'PROCESSED'`/`'ERROR'` → bukan anggota ENUM → insert gagal (strict) / truncate (non-strict)

## Impact
Payload inbound tidak pernah tercatat (audit/replay hilang). Insert log bisa gagal → submit valid bisa terlempar 422 palsu. Audit integrasi rusak.

## Risk Scenario
Source app submit valid → insert message log meledak (`message_type` tak dikenal / ENUM invalid) → controller catch → balas 422 → source retry.

## Recommended Fix
`payload_json → request_body_json`; status awal `PENDING`, sukses `SUCCESS`, gagal `FAILED`; hapus/relokasi `message_type`; isi `idempotency_key`.

## Acceptance Criteria
- [ ] Setiap submit → 1 baris log dengan `request_body_json` terisi & status valid
- [ ] Tidak ada SQL error pada path sukses & gagal

## Priority
P1 - Important before production
