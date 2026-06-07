<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
|--------------------------------------------------------------------------
| Bootstrap Approval Center
|--------------------------------------------------------------------------
| Laravel 11 menggunakan bootstrap/app.php untuk konfigurasi yang dulu
| tersebar di Kernel.php & Handler.php.
|
| Di sini kita daftarkan:
|  - 4 file route (web, api, console, plus kelompok api/v1)
|  - middleware alias untuk role check & API client auth
|  - exception handler khusus API (selalu JSON)
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__.'/../routes/web.php',
        api:      __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health:   '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Middleware alias yang akan dipakai per route.
        // Implementasi class menyusul di Tahap 4 & Tahap 7.
        $middleware->alias([
            'role'                  => \App\Http\Middleware\EnsureUserHasRole::class,
            'force_password_change' => \App\Http\Middleware\ForcePasswordChange::class,
            'api_client_auth'       => \App\Http\Middleware\ApiClientAuthenticate::class,
            // 'api_client_hmac' dihapus (#105): dulu pass-through yang fail-open.
            // Gunakan 'api_client_auth' untuk autentikasi API.
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Semua error pada API harus JSON dengan format konsisten.
        $exceptions->shouldRenderJsonWhen(function ($request, $exception) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Graceful 419: CSRF token mismatch / sesi kedaluwarsa pada request web.
        // Daripada halaman "Page Expired" yang membingungkan, kembalikan user ke
        // halaman sebelumnya (atau login) dengan pesan jelas + token segar.
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PAGE_EXPIRED',
                    'message' => 'Sesi kedaluwarsa. Muat ulang lalu coba lagi.',
                ], 419);
            }
            return redirect()
                ->guest(route('login'))
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('error', 'Sesi Anda telah kedaluwarsa. Silakan login kembali.');
        });
    })
    ->create();
