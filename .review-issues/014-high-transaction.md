# [High][Transaction] Idempotency submit longgar: tanpa idempotency_key double-submit lolos & duplikat dibalas 422 (bukan idempotent)

## Summary
Dedup submit hanya jika `idempotency_key` dikirim (nullable) dan tidak scope ke source_app. Tanpa key, double-submit `source_request_id` baru ditolak unique constraint saat INSERT lalu dibalas 422 generic (bukan respons idempotent).

## Location
- `app/Http/Controllers/Api/V1/ApprovalSubmitController.php:43,55-66,101-121`
- Schema: `uq_tbl_request_source_doc (idtblsource_app, idtbldocument_type, source_request_id)`, `uq_tbl_request_idempotency (idtblsource_app, idempotency_key)`

## Problem
Dedup `where('idempotency_key',$k)->first()` tanpa `idtblsource_app` (unique komposit) → bisa salah-cocok antar app. Bila key tidak dikirim, INSERT kedua kena `uq_tbl_request_source_doc` → `QueryException` → catch generic → 422 "SUBMIT_ERROR".

## Impact
Retry/double-submit → UX error palsu; source app menandai gagal padahal request pertama sukses. Tanpa scope, dedup bisa kembalikan request milik app lain.

## Risk Scenario
SFA submit `R-123` tanpa key → timeout → retry → INSERT kedua kena unique → 422 → SFA tandai gagal padahal approval pertama jalan.

## Recommended Fix
(1) Scope dedup: `where('idtblsource_app',$client->idtblsource_app)->where('idempotency_key',$k)`. (2) Dedup berbasis dokumen sebelum insert (`forSourceDocument(app,docType,doc_ref)`) → balas idempotent bila ada. (3) Tangkap `QueryException` errno 1062 → balas idempotent 200, bukan 422.

## Acceptance Criteria
- [ ] Submit `source_request_id` sama dua kali → tepat 1 request + 1 instance
- [ ] Call kedua membalas `idempotent:true` dengan id yang sama (200)
- [ ] Test double-submit dengan & tanpa idempotency_key

## Priority
P1 - Important before production
