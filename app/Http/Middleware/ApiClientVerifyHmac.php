<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ApiClientVerifyHmac — alias middleware untuk backward compat.
 * Implementasi penuh ada di ApiClientAuthenticate.
 * Middleware ini di-alias sebagai 'api_client_hmac' di bootstrap/app.php
 * namun tidak digunakan di routes aktif (routes pakai 'api_client_auth').
 * Jika dipanggil, teruskan saja ke next (tidak silent-fail maupun 501).
 */
class ApiClientVerifyHmac
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
