# [Critical][Integration] SendCallbackJob menulis/membaca kolom & status yang tidak ada di schema tblcallback_outbox

## Summary
`SendCallbackJob` memakai nama kolom dan nilai status yang tidak ada di tabel `tblcallback_outbox`, sehingga pengiriman callback gagal/stuck permanen — bahkan setelah outbox diisi.

## Location
- `app/Jobs/SendCallbackJob.php:35,38-39,60,63,65-66,77,79`
- Schema: `approval_center_schema_tbl.sql` tabel `tblcallback_outbox`

## Problem
Schema sebenarnya: `target_url`, `last_response_code`, `last_response_body`, `last_error_message`, `status ENUM('PENDING','SENT','FAILED','DEAD')`. Tapi job memakai:
- `$cb->callback_url` (seharusnya `target_url`) → `parse_url(null)` → host kosong → POST ke URL kosong
- `$cb->last_error` (seharusnya `last_error_message`)
- `$cb->http_status` (seharusnya `last_response_code`)
- `$cb->response_body` (seharusnya `last_response_body`)
- `status='SUCCESS'` dan `'RETRY'` → bukan anggota ENUM (seharusnya `SENT`/`FAILED`/`DEAD`)

Catatan: drift ini terbawa juga dari fix SSRF sebelumnya yang meng-carry nama kolom lama.

## Impact
Callback tidak pernah bisa ditandai SENT; URL kosong → langsung gagal; status invalid ditolak/truncate ENUM → retry tak berujung.

## Risk Scenario
Outbox terisi → job jalan → `parse_url(null)` host kosong → POST gagal → set `status='RETRY'` ditolak ENUM → exception → retry tak berujung.

## Recommended Fix
Selaraskan: `callback_url→target_url`, `last_error→last_error_message`, `http_status→last_response_code`, `response_body→last_response_body`; `'SUCCESS'→'SENT'`; `'RETRY'→'PENDING'`; tambah transisi ke `DEAD` saat `retry_count >= max_retry`.

## Acceptance Criteria
- [ ] Callback sukses → `status='SENT'`, `sent_at`, `last_response_code=200`
- [ ] Gagal terus → akhirnya `DEAD`, tidak retry lagi
- [ ] Tidak ada SQL "column not found" / ENUM truncation
- [ ] Unit test mapping kolom

## Priority
P0 - Must fix before production
