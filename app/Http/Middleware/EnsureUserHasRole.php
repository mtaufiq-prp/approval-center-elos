<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware role-based authorization.
 *
 * Dipakai di route:
 *   Route::middleware('role:ADMIN_APPROVAL')->...
 *   Route::middleware('role:ADMIN_APPROVAL,APPROVER')->...
 *
 * Logika: user lulus jika memiliki minimal SATU role yang disebut.
 * Role dibaca dari relasi tbluser → tbluser_role → tblrole
 * (lihat TblUser::hasAnyRole()).
 *
 * Aksesibilitas:
 *  - Tidak login → biarkan middleware 'auth' yang redirect ke /login.
 *    Middleware ini hanya mengembalikan 403 jika sudah login tapi
 *    role tidak match.
 *  - Untuk request yang expect JSON / API, balas 403 JSON.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        // Jika tidak login, lempar ke auth middleware (kalau ada di pipeline).
        // Defensive: tetap balas 401 supaya tidak silent-pass.
        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }
            return redirect()->route('login');
        }

        // Kosong → tidak ada role dipersyaratkan (defensive default deny).
        if (empty($roles)) {
            abort(403, 'No role configured for this route.');
        }

        if (! method_exists($user, 'hasAnyRole') || ! $user->hasAnyRole($roles)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden. Role required: ' . implode(', ', $roles),
                ], 403);
            }
            abort(403, 'Anda tidak memiliki akses untuk halaman ini.');
        }

        return $next($request);
    }
}
