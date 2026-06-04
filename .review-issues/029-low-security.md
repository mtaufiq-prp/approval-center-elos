# [Low][Security] Pesan error builder & jobtitle-search membocorkan detail exception internal ke client

## Summary
Beberapa endpoint builder mengembalikan `$e->getMessage()` mentah (bisa membocorkan nama DB/tabel/SQL cross-DB).

## Location
- `app/Http/Controllers/Web/Workflow/FlowBuilderController.php:63-68 (builderData), 230-236 (jobtitleSearch), 175-180 (builderClone)`

## Problem
`return response()->json(['success'=>false,'message'=>$e->getMessage()], 500);` dan `jobtitleSearch` echo `$e->getMessage()` → bisa bocorkan `db_master.ms_jobtitle`, struktur kolom, path koneksi. (Pola sama dengan exception-leak ApprovalSubmit yang sudah diperbaiki — di sini belum.)

## Impact
Pembocoran skema DB internal & jejak SQL ke panel admin/log proxy.

## Recommended Fix
Log via `Log::error()`, balas pesan generik (konsisten dengan ApprovalSubmitController).

## Acceptance Criteria
- [ ] Response error builder/jobtitle-search tidak memuat `$e->getMessage()`
- [ ] Detail tetap tercatat di log server

## Priority
P3 - Nice to have
