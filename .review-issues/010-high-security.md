# [High][Security] token_expired_at API Client tidak pernah diperiksa — kredensial kadaluarsa tetap diterima

## Summary
Kolom `token_expired_at` bisa diisi admin namun middleware HMAC tidak pernah memeriksanya.

## Location
- `app/Http/Middleware/ApiClientAuthenticate.php` (`handle` — tidak ada cek expiry)
- `app/Models/TblApiClient.php:39,55` (kolom ada + cast datetime), `ApiClientRequest.php` (input)

## Problem
Middleware hanya cek `is_active`, IP, timestamp freshness, nonce, signature. `token_expired_at` tidak pernah dibaca.

## Impact
Kredensial berumur terbatas (integrasi sementara/vendor) berlaku selamanya. Rasa aman palsu.

## Risk Scenario
Vendor diberi client key `token_expired_at`=30 hari. Setelah kontrak berakhir, key tetap bisa submit/cancel/status.

## Recommended Fix
```php
if ($client->token_expired_at && now()->greaterThan($client->token_expired_at)) {
    return $this->deny('CLIENT_EXPIRED', 'Kredensial API Client sudah kadaluarsa.', $request);
}
```

## Acceptance Criteria
- [ ] Request dengan client expired → 401 CLIENT_EXPIRED
- [ ] Client tanpa `token_expired_at` (NULL) normal
- [ ] Test batas waktu

## Priority
P1 - Important before production
