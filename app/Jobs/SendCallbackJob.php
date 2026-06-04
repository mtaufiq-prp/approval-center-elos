<?php

namespace App\Jobs;

use App\Models\TblCallbackOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SendCallbackJob
 *
 * Kirim callback outbox ke URL tujuan dengan retry logic.
 * Retry up to 5x dengan exponential backoff (1, 2, 4, 8, 16 menit).
 */
class SendCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $timeout = 30;

    public function __construct(private int $callbackId) {}

    public function handle(): void
    {
        $cb = TblCallbackOutbox::find($this->callbackId);
        if (! $cb || $cb->status === 'SUCCESS') return;

        // SSRF guard: tolak URL yang mengarah ke loopback
        $host = strtolower(parse_url($cb->callback_url, PHP_URL_HOST) ?? '');
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true) || str_ends_with($host, '.local')) {
            Log::error("SendCallback #{$this->callbackId}: callback_url mengarah ke loopback ({$host}), ditolak.");
            $cb->status     = 'FAILED';
            $cb->last_error = 'SSRF_BLOCKED: loopback URL tidak diizinkan.';
            $cb->save();
            return;
        }

        try {
            // Buat HMAC signature untuk callback agar penerima bisa verifikasi keaslian
            $timestamp   = (string) time();
            $bodyJson    = json_encode($cb->payload_json ?? [], JSON_UNESCAPED_UNICODE);
            $nonce       = bin2hex(random_bytes(8));
            $cbSecret    = config('app.callback_hmac_secret', config('app.key'));
            $signature   = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $bodyJson, $cbSecret);

            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type'       => 'application/json',
                    'X-Source'           => 'ApprovalCenter',
                    'X-Callback-Ts'      => $timestamp,
                    'X-Callback-Nonce'   => $nonce,
                    'X-Callback-Sig'     => $signature,
                ])
                ->post($cb->callback_url, $cb->payload_json ?? []);

            if ($response->successful()) {
                $cb->status         = 'SUCCESS';
                $cb->sent_at        = now();
                $cb->http_status    = $response->status();
                $cb->response_body  = mb_substr($response->body(), 0, 1000);
                $cb->save();
                return;
            }

            throw new \RuntimeException("HTTP {$response->status()}: " . mb_substr($response->body(), 0, 200));

        } catch (\Throwable $e) {
            $attempts = $this->attempts();
            Log::warning("SendCallback #{$this->callbackId} attempt {$attempts} failed: {$e->getMessage()}");

            $cb->status        = $attempts >= $this->tries ? 'FAILED' : 'RETRY';
            $cb->retry_count   = $attempts;
            $cb->last_error    = $e->getMessage();
            $cb->save();

            if ($attempts < $this->tries) {
                $this->release(60 * (2 ** ($attempts - 1))); // backoff: 1,2,4,8,16 menit
            } else {
                $this->fail($e);
            }
        }
    }
}
