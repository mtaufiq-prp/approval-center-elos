<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * [STUB - Akan diimplementasi penuh di Tahap 7]
 *
 * ApiClientVerifyHmac.
 *
 * Verifikasi HMAC SHA256:
 *  1. Ambil X-Timestamp, X-Signature, raw body.
 *  2. Cek toleransi waktu (default 300 detik) vs server now().
 *  3. Decrypt client_secret_hash (AES via APP_KEY).
 *  4. Re-compute HMAC( X-Timestamp + "\n" + raw_body , secret ).
 *  5. Bandingkan timing-safe (hash_equals).
 *
 * Stub mengembalikan 501 agar tidak silent-pass.
 */
class ApiClientVerifyHmac
{
    public function handle(Request $request, Closure $next): Response
    {
        // TODO Tahap 7: verifikasi signature.
        return response()->json([
            'success' => false,
            'message' => 'HMAC verification is not yet implemented (Tahap 7).',
        ], 501);
    }
}
