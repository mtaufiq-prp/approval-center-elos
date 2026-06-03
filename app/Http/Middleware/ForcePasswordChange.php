<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memaksa user mengganti password jika must_change_password = 1.
 *
 * Aturan akses:
 *  - User tetap boleh akses:
 *      * GET / POST /change-password   (form & submit)
 *      * POST /logout
 *  - Selain itu, redirect ke /change-password.
 *
 * Middleware ini harus dipasang SETELAH 'auth' agar pasti ada user.
 */
class ForcePasswordChange
{
    /**
     * Nama route yang DIIJINKAN diakses meskipun flag aktif.
     */
    private const ALLOWED_ROUTE_NAMES = [
        'password.change', // GET form
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Tidak login → bukan tanggung jawab middleware ini.
        if (! $user) {
            return $next($request);
        }

        // Akun tidak wajib ganti → lanjut.
        if (! $user->must_change_password) {
            return $next($request);
        }

        // Izinkan route tertentu (logout, halaman change-password).
        $routeName = optional($request->route())->getName();
        if ($routeName && in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return $next($request);
        }

        // Izinkan juga POST update password (route 'password.change' juga
        // dipakai untuk POST; cek path sebagai jaring pengaman).
        if ($request->is('change-password')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'You must change your password before continuing.',
                'code'    => 'PASSWORD_CHANGE_REQUIRED',
            ], 403);
        }

        return redirect()->route('password.change')
            ->with('warning', 'Anda wajib mengganti password sebelum melanjutkan.');
    }
}
