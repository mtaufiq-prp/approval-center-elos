<?php

namespace App\Http\Middleware;

use App\Models\TblApiClient;
use App\Services\ApiClientSecretService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * ApiClientAuthenticate
 *
 * Verifikasi request API:
 *  1. Header X-Client-Key, X-Timestamp, X-Signature wajib ada.
 *  2. API Client terdaftar & is_active = 1.
 *  3. IP allowed_ip check.
 *  4. X-Timestamp freshness ±300 detik (anti-replay).
 *  5. HMAC-SHA256(X-Timestamp + "\n" + raw_body, plain_secret) cocok.
 *
 * Setelah lolos, ApiClient di-inject ke $request->attributes('api_client').
 */
class ApiClientAuthenticate
{
    private const TOLERANCE = 300;

    public function __construct(private ApiClientSecretService $secretService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $clientKey = $request->header('X-Client-Key');
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');

        if (! $clientKey || ! $timestamp || ! $signature) {
            return $this->deny('MISSING_HEADERS', 'X-Client-Key, X-Timestamp, X-Signature wajib ada.', $request);
        }

        $client = TblApiClient::where('client_key', $clientKey)->first();
        if (! $client)          return $this->deny('CLIENT_NOT_FOUND', 'Client key tidak dikenal.', $request);
        if (! $client->is_active) return $this->deny('CLIENT_REVOKED', 'API Client di-revoke.', $request);

        // IP check
        if ($client->allowed_ip && ! $this->checkIp($request->ip(), $client->allowed_ip)) {
            return $this->deny('IP_NOT_ALLOWED', "IP {$request->ip()} tidak diizinkan.", $request);
        }

        // Timestamp freshness
        if (abs(time() - (int) $timestamp) > self::TOLERANCE) {
            return $this->deny('TIMESTAMP_EXPIRED', 'Timestamp kadaluarsa (toleransi ±300s).', $request);
        }

        // HMAC verify
        try {
            $plain = $this->secretService->decrypt($client->client_secret_hash);
        } catch (\Throwable) {
            return $this->deny('SECRET_ERROR', 'Gagal memproses secret.', $request);
        }

        $expected = hash_hmac('sha256', $timestamp . "\n" . $request->getContent(), $plain);
        if (! hash_equals($expected, $signature)) {
            return $this->deny('INVALID_SIGNATURE', 'Signature tidak valid.', $request);
        }

        $client->last_used_at = now(); $client->saveQuietly();
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
