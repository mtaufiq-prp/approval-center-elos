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
            'api_client_hmac'       => \App\Http\Middleware\ApiClientVerifyHmac::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Semua error pada API harus JSON dengan format konsisten.
        // Detail handler akan diisi di Tahap 7.
        $exceptions->shouldRenderJsonWhen(function ($request, $exception) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })
    ->create();
