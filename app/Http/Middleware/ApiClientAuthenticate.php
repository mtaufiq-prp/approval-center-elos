<?php

namespace App\Http\Middleware;

use App\Models\TblApiClient;
use App\Services\ApiClientSecretService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * ApiClientAuthenticate
 *
 * Verifikasi request API:
 *  1. Header X-Client-Key, X-Timestamp, X-Signature wajib ada.
 *  2. API Client terdaftar & is_active = 1.
 *  3. IP allowed_ip check.
 *  4. X-Timestamp freshness (config approval_center.api_security.time_tolerance_seconds,
 *     default ±300 detik) + X-Nonce anti-replay.
 *  5. HMAC-SHA256(X-Timestamp + "\n" + X-Nonce + "\n" + raw_body, plain_secret) cocok.
 *
 * Setelah lolos, ApiClient di-inject ke $request->attributes('api_client').
 */
class ApiClientAuthenticate
{
    public function __construct(private ApiClientSecretService $secretService) {}

    /** Toleransi waktu (detik) dari config; default 300. */
    private function tolerance(): int
    {
        return (int) config('approval_center.api_security.time_tolerance_seconds', 300);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $clientKey = $request->header('X-Client-Key');
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');
        $nonce     = $request->header('X-Nonce');

        if (! $clientKey || ! $timestamp || ! $signature || ! $nonce) {
            return $this->deny('MISSING_HEADERS', 'X-Client-Key, X-Timestamp, X-Signature, X-Nonce wajib ada.', $request);
        }

        $client = TblApiClient::where('client_key', $clientKey)->first();
        if (! $client)          return $this->deny('CLIENT_NOT_FOUND', 'Client key tidak dikenal.', $request);
        if (! $client->is_active) return $this->deny('CLIENT_REVOKED', 'API Client di-revoke.', $request);

        // #54: tolak kredensial yang sudah kadaluarsa
        if ($client->token_expired_at && $client->token_expired_at->isPast()) {
            return $this->deny('CLIENT_EXPIRED', 'Kredensial API Client sudah kadaluarsa.', $request);
        }

        // IP check
        if ($client->allowed_ip && ! $this->checkIp($request->ip(), $client->allowed_ip)) {
            return $this->deny('IP_NOT_ALLOWED', "IP {$request->ip()} tidak diizinkan.", $request);
        }

        // Timestamp freshness (toleransi dari config)
        $tolerance = $this->tolerance();
        if (abs(time() - (int) $timestamp) > $tolerance) {
            return $this->deny('TIMESTAMP_EXPIRED', "Timestamp kadaluarsa (toleransi ±{$tolerance}s).", $request);
        }

        // Nonce replay check — reservasi ATOMIC (#4). Cache::add() = insert-if-absent
        // dalam satu operasi; mengembalikan false bila key sudah ada. Sebelumnya
        // has()+put() terpisah (TOCTOU): dua request dengan nonce sama yang tiba
        // bersamaan bisa lolos keduanya pada driver database. Disimpan 2× TOLERANCE.
        $nonceCacheKey = 'hmac_nonce:' . $clientKey . ':' . $nonce;
        if (! Cache::add($nonceCacheKey, 1, $tolerance * 2)) {
            return $this->deny('REPLAY_DETECTED', 'Nonce sudah digunakan (replay attack terdeteksi).', $request);
        }

        // HMAC verify — sertakan nonce dalam signed string
        try {
            $plain = $this->secretService->decrypt($client->client_secret_hash);
        } catch (\Throwable) {
            return $this->deny('SECRET_ERROR', 'Gagal memproses secret.', $request);
        }

        $expected = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $request->getContent(), $plain);
        if (! hash_equals($expected, $signature)) {
            return $this->deny('INVALID_SIGNATURE', 'Signature tidak valid.', $request);
        }

        // Debounce penulisan last_used_at (review #L2): pada 1000 req/menit, meng-UPDATE
        // baris tblapi_client yang sama tiap request menciptakan write hot-spot/lock churn.
        // Cukup update bila sudah > 60 detik sejak terakhir.
        if (! $client->last_used_at || $client->last_used_at->lt(now()->subSeconds(60))) {
            $client->last_used_at = now();
            $client->saveQuietly();
        }
        $request->attributes->set('api_client', $client);

        return $next($request);
    }

    private function checkIp(string $ip, string $allowed): bool
    {
        foreach (array_map('trim', explode(',', $allowed)) as $a) {
            if ($a === $ip) return true;
            if (str_contains($a, '/')) {
                [$sub, $bits] = explode('/', $a);
                $mask = -1 << (32 - (int) $bits);
                if ((ip2long($ip) & $mask) === (ip2long($sub) & $mask)) return true;
            }
        }
        return false;
    }

    private function deny(string $code, string $msg, Request $request): Response
    {
        Log::warning("ApiAuth [{$code}] {$request->ip()}: {$msg}");
        return response()->json(['success' => false, 'error' => $code, 'message' => $msg], 401);
    }
}
