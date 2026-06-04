# [High][Database] Kolom idtbluser_submitter tidak ada di schema/fillable → submitter selalu hilang & resolver SUPERIOR rusak

## Summary
`idtbluser_submitter` ditulis & dibaca di kode tetapi tidak ada di schema maupun `$fillable`, sehingga selalu null.

## Location
- Tulis: `app/Http/Controllers/Api/V1/ApprovalSubmitController.php:118`
- Baca: `app/Services/FlowEngineService.php:415`, `app/Http/Controllers/Web/InboxController.php:230`
- Schema `tblapproval_request` (tidak ada kolom), `app/Models/TblApprovalRequest.php` (tidak di `$fillable`)

## Problem
`create([... 'idtbluser_submitter' => ...])` di-drop oleh mass-assignment guard (bukan fillable) dan kolomnya tidak ada. Pembacaan `$request->idtbluser_submitter` selalu null → `createApprovalTask` meneruskan `$submitter=null` ke resolver.

## Impact
Assignee rule `SUPERIOR` tidak punya basis user → kandidat kosong → node memicu `completeProcess(ERROR)`. Identitas pemohon juga hilang dari record (audit & self-approval check tak bisa).

## Risk Scenario
Flow apa pun yang memakai `SUPERIOR` langsung ERROR di node tsb; request macet tanpa task.

## Recommended Fix
ALTER `tblapproval_request ADD COLUMN idtbluser_submitter BIGINT UNSIGNED NULL` + FK ke `tbluser`; tambah ke `$fillable`. (Migration ALTER, sesuai konvensi.)

## Acceptance Criteria
- [ ] Setelah submit dengan submitter valid, `idtbluser_submitter` tersimpan
- [ ] Resolver SUPERIOR menghasilkan kandidat
- [ ] Tidak ada ERROR akibat submitter null

## Priority
P1 - Important before production
