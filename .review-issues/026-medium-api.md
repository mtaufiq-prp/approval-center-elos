# [Medium][API] Tidak ada validasi struktur context_json/payload_json (hanya array) → salah-rute approval senyap

## Summary
Validasi submit hanya `array`. PayloadEnrichmentService mengakses `payload.detail[].value_retur_ori`, `header.idtblbranch`, dll; struktur menyimpang → `_computed` salah → routing ke flow salah tanpa error. **POTENTIAL RISK.**

## Location
- `app/Http/Controllers/Api/V1/ApprovalSubmitController.php:37-52`
- `app/Services/PayloadEnrichmentService.php`

## Problem
Tidak ada FormRequest; validasi inline lemah, tidak per-document-type. Payload cacat → `total_nilai_retur=0` → jalur "≤5jt/BMH saja".

## Impact
Request payload cacat diterima 201 lalu salah-rute (retur 30jt masuk jalur BMH-saja) — kesalahan approval senyap.

## Recommended Fix
Buat `SubmitApprovalRequest` (FormRequest) dengan validasi nested per `idtbldocument_type` (mis. SFA: `payload_json.detail` array berisi `value_retur_ori`,`idmsalasan`; `header.idtblbranch` required).

## Acceptance Criteria
- [ ] Payload tanpa field wajib → 422 dengan pesan field spesifik
- [ ] Test payload cacat ditolak

## Priority
P2 - Should fix soon
