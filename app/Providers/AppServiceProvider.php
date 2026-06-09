<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * AppServiceProvider
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // App pakai Bootstrap 5 (CDN). Default paginator Laravel = Tailwind →
        // panah SVG (w-5 h-5) tampil raksasa tanpa CSS Tailwind. Pakai view
        // pagination Bootstrap 5 agar ->links() di semua halaman benar.
        Paginator::useBootstrapFive();

        $this->configureRateLimiters();

        // @nodeLabel($x): format nama node/step untuk layar approval & monitoring
        // (mis. "dept_head" → "DEPT HEAD"). Hilangkan underscore + ALL CAPS agar
        // lebih mudah dibaca. Tidak dipakai di halaman setting/builder (pakai node_code asli).
        Blade::directive('nodeLabel', function ($expr) {
            return "<?php \$__nl = strtoupper(str_replace('_', ' ', trim((string) ({$expr})))); echo e(\$__nl !== '' ? \$__nl : '—'); ?>";
        });
    }

    /**
     * Rate limiter untuk endpoint /api/v1/* (review #3).
     *
     * Sebelumnya 'throttle:60,1' yang di-key per-IP (HMAC client tidak mengisi
     * $request->user()), sehingga target 1000 req/menit mustahil dan satu IP
     * proxy memblok seluruh sistem. Limiter 'api_client' di-key per API client
     * terautentikasi (di-inject oleh middleware api_client_auth yang berjalan
     * SEBELUM throttle), dengan plafon tinggi yang dapat dikonfigurasi.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('api_client', function (Request $request) {
            $perMinute = (int) config('approval_center.api_security.rate_limit_per_minute', 2000);

            $client = $request->attributes->get('api_client');
            $key = $client?->idtblapi_client
                ? 'apiclient:' . $client->idtblapi_client
                : 'apiip:' . $request->ip();

            return Limit::perMinute($perMinute)->by($key);
        });
    }
}
