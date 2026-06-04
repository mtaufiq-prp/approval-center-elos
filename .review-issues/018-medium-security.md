# [Medium][Security] ApprovalSubmitController tidak memvalidasi idtbldocument_type milik source_app pemanggil

## Summary
`idtbldocument_type` hanya divalidasi `integer`, tanpa cek bahwa doc type terdaftar untuk `$client->idtblsource_app`.

## Location
- `app/Http/Controllers/Api/V1/ApprovalSubmitController.php:37-121`
- Mitigasi implisit: `app/Services/RoutingRuleService.php` filter `(source_app, doc_type)`

## Problem
Isolasi hanya kebetulan terjaga karena routing rule akan throw bila tak match — bukan kontrol akses eksplisit. Record `TblApprovalRequest` bisa merujuk doc_type milik program lain bila ada routing rule cross-listing. **POTENTIAL RISK.**

## Impact
Source app A bisa membuat request memakai doc type milik program B; konsistensi data lintas program rusak.

## Risk Scenario
Client SFA submit dengan `idtbldocument_type` milik BSKB; bila BSKB punya routing rule generik, alur program B terpicu oleh A.

## Recommended Fix
```php
Rule::exists('tbldocument_type','idtbldocument_type')->where('idtblsource_app', $client->idtblsource_app)
```

## Acceptance Criteria
- [ ] Submit dengan doc_type milik source_app lain → 422
- [ ] Submit dengan doc_type milik sendiri → sukses

## Priority
P2 - Should fix soon
