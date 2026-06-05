<?php

namespace App\Jobs;

use App\Models\TblCallbackOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SendCallbackJob
 *
 * Mengirim satu baris outbox callback ke target_url source app.
 * - ShouldBeUnique per callbackId → cegah dua job paralel untuk baris yang sama (#86).
 * - Status & kolom selaras schema tblcallback_outbox (#82).
 * - Backoff via next_retry_at; scheduler (ProcessCallbackOutboxJob) yang men-dispatch
 *   ulang saat next_retry_at <= now. Job ini TIDAK self-release agar tidak dobel (#86).
 * - failed() / habis retry → status DEAD untuk tindakan manual admin (#98).
 * - SSRF guard menolak loopback + rentang privat/link-local/metadata (#109).
 */
class SendCallbackJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;   // retry diatur via outbox.next_retry_at, bukan queue retry
    public int $timeout = 30;

    public function __construct(private int $callbackId) {}

    /** Kunci unik agar tidak ada dua job untuk baris outbox yang sama. */
    public function uniqueId(): string
    {
        return (string) $this->callbackId;
    }

    public function uniqueFor(): int
    {
        return 120;
    }

    public function handle(): void
    {
        $cb = TblCallbackOutbox::find($this->callbackId);
        if (! $cb || in_array($cb->status, [TblCallbackOutbox::STATUS_SENT, TblCallbackOutbox::STATUS_DEAD], true)) {
            return; // sudah selesai atau menyerah
        }

        // SSRF guard: tolak loopback, rentang privat, link-local, metadata.
        if ($blocked = $this->blockedReason($cb->target_url)) {
            Log::error("SendCallback #{$this->callbackId}: target_url ditolak ({$blocked}).");
            $cb->status             = TblCallbackOutbox::STATUS_DEAD;
            $cb->last_error_message = "SSRF_BLOCKED: {$blocked}";
            $cb->save();
            return;
        }

        try {
            // HMAC signature agar penerima bisa memverifikasi keaslian callback.
            $timestamp = (string) time();
            $nonce     = bin2hex(random_bytes(8));
            $bodyJson  = json_encode($cb->payload_json ?? [], JSON_UNESCAPED_UNICODE);
            $secret    = config('app.callback_hmac_secret') ?: config('app.key');
            $signature = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $bodyJson, $secret);

            // #M1: kirim BYTE-IDENTIK dengan yang ditandatangani. Sebelumnya body
            // di-encode ulang oleh Http::post($array) tanpa JSON_UNESCAPED_UNICODE,
            // sehingga byte berbeda dari $bodyJson → HMAC penerima tidak cocok untuk
            // payload non-ASCII. withBody() mengirim string yang persis ditandatangani.
            $response = Http::timeout((int) config('approval_center.callback.http_timeout_seconds', 15))
                ->withHeaders([
                    'X-Source'         => 'ApprovalCenter',
                    'X-Callback-Ts'    => $timestamp,
                    'X-Callback-Nonce' => $nonce,
                    'X-Callback-Sig'   => $signature,
                ])
                ->withBody($bodyJson, 'application/json')
                ->post($cb->target_url);

            if ($response->successful()) {
                $cb->status             = TblCallbackOutbox::STATUS_SENT;
                $cb->sent_at            = now();
                $cb->last_response_code = $response->status();
                $cb->last_response_body = mb_substr($response->body(), 0, 1000);
                $cb->last_error_message = null;
                $cb->save();
                return;
            }

            $cb->last_response_code = $response->status();
            $cb->last_response_body = mb_substr($response->body(), 0, 1000);
            $this->scheduleRetryOrDead($cb, "HTTP {$response->status()}");

        } catch (\Throwable $e) {
            $this->scheduleRetryOrDead($cb, $e->getMessage());
        }
    }

    /**
     * Tandai FAILED + jadwalkan retry via next_retry_at (exponential backoff),
     * atau DEAD bila sudah mencapai max_retry.
     */
    private function scheduleRetryOrDead(TblCallbackOutbox $cb, string $error): void
    {
        $cb->retry_count        = (int) $cb->retry_count + 1;
        $cb->last_error_message = mb_substr($error, 0, 1000);

        if ($cb->retry_count >= (int) ($cb->max_retry ?: 10)) {
            $cb->status        = TblCallbackOutbox::STATUS_DEAD;
            $cb->next_retry_at = null;
            Log::warning("SendCallback #{$this->callbackId} DEAD setelah {$cb->retry_count} percobaan: {$error}");
        } else {
            $cb->status        = TblCallbackOutbox::STATUS_FAILED;
            // backoff: 1,2,4,8,... menit (cap 60 menit)
            $delayMin          = min(60, 2 ** max(0, $cb->retry_count - 1));
            $cb->next_retry_at = now()->addMinutes($delayMin);
            Log::warning("SendCallback #{$this->callbackId} gagal (retry {$cb->retry_count}, +{$delayMin}m): {$error}");
        }
        $cb->save();
    }

    /** Dipanggil Laravel saat job benar-benar gagal (exception tak tertangani). */
    public function failed(\Throwable $e): void
    {
        $cb = TblCallbackOutbox::find($this->callbackId);
        if ($cb && ! in_array($cb->status, [TblCallbackOutbox::STATUS_SENT, TblCallbackOutbox::STATUS_DEAD], true)) {
            $cb->status             = TblCallbackOutbox::STATUS_DEAD;
            $cb->last_error_message = mb_substr('JOB_FAILED: ' . $e->getMessage(), 0, 1000);
            $cb->save();
        }
    }

    /**
     * Kembalikan alasan blokir bila host target tidak aman, atau null bila aman.
     *
     * MODEL ALLOWLIST (review #8/#109/#11): sistem ini internal-only — aplikasi
     * sumber hidup di jaringan privat (10.x). Guard lama (denylist rentang privat)
     * memblokir SEMUA callback sah → hub-and-spoke putus. Sekarang:
     *   - loopback & metadata/link-local SELALU diblokir (tak pernah target sah),
     *   - bila allowlist CIDR dikonfigurasi, resolved IP HARUS masuk salah satunya,
     *   - host yang tak bisa di-resolve dibiarkan (HTTP akan gagal & retry; tak ada
     *     SSRF karena tak mencapai apa pun) — hindari DEAD karena DNS blip.
     */
    private function blockedReason(?string $url): ?string
    {
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
        if ($host === '') return 'invalid host';
        if (in_array($host, ['localhost', '0.0.0.0', '::1'], true)) return 'loopback';

        // Resolve hostname → IPv4 (gethostbyname mengembalikan input bila gagal).
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            // Tak ter-resolve → tak bisa mencapai target apa pun; biarkan HTTP gagal & retry.
            return null;
        }

        // IPv6 belum didukung deployment internal (IPv4-only). Tolak demi keamanan.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return "IPv6 target tidak didukung ({$ip})";
        }

        // SELALU blokir loopback & metadata/link-local, walau ada di allowlist.
        if (str_starts_with($ip, '127.'))     return 'loopback IP';
        if (str_starts_with($ip, '169.254.')) return 'link-local/metadata IP';

        // Allowlist: bila dikonfigurasi, IP target HARUS masuk salah satu CIDR.
        $allowed = (array) config('approval_center.callback.allowed_cidrs', []);
        if (! empty($allowed)) {
            foreach ($allowed as $cidr) {
                if (self::ipInCidr($ip, (string) $cidr)) {
                    return null; // diizinkan
                }
            }
            return "IP {$ip} di luar allowlist callback (set APPROVAL_CALLBACK_ALLOWED_CIDRS)";
        }

        // Allowlist kosong (dev): izinkan semua kecuali loopback/metadata (sudah diblokir).
        return null;
    }

    /** Cek apakah IPv4 berada dalam CIDR (atau cocok persis bila tanpa /bits). */
    public static function ipInCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) return false;
        if ($bits <= 0)  return true;
        if ($bits > 32)  $bits = 32;
        $mask = -1 << (32 - $bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
