<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\TblUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Login & Logout untuk Approval Center.
 *
 * Aturan login:
 *  1. Field 'login' boleh user_ref ATAU email.
 *  2. Akun WAJIB is_active = 1; akun nonaktif tidak boleh login.
 *  3. Password kosong (NULL) di DB tidak boleh login (akun belum di-set).
 *  4. Rate limit 5 percobaan per IP+login dalam 60 detik untuk mitigasi
 *     brute force sederhana (tidak butuh package eksternal).
 *  5. Setelah berhasil login → set last_login_at = now().
 *  6. Jika must_change_password = 1, redirect ke /change-password.
 */
class LoginController extends Controller
{
    /** Maksimal percobaan login per (IP + login) sebelum diblokir sesaat. */
    private const MAX_ATTEMPTS  = 5;
    private const DECAY_SECONDS = 60;

    /**
     * Tampilkan halaman login.
     */
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }
        return view('auth.login');
    }

    /**
     * Proses login.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            return back()
                ->withInput($request->only('login'))
                ->withErrors([
                    'login' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
                ]);
        }

        $credentials = $request->validated();
        $loginInput  = trim($credentials['login']);
        $password    = $credentials['password'];
        $remember    = (bool) ($credentials['remember'] ?? false);

        // Cari user by email atau user_ref (case-insensitive untuk email).
        $user = TblUser::query()
            ->where(function ($q) use ($loginInput) {
                $q->where('email', $loginInput)
                  ->orWhere('user_ref', $loginInput);
            })
            ->first();

        // Validasi akun
        if (! $user
            || ! $user->is_active
            || empty($user->password)
            || ! Hash::check($password, $user->password)
        ) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return back()
                ->withInput($request->only('login'))
                ->withErrors([
                    'login' => 'User Ref / Email atau Password tidak valid, atau akun tidak aktif.',
                ]);
        }

        // Login sukses
        RateLimiter::clear($key);
        Auth::login($user, $remember);
        $request->session()->regenerate();

        // Catat last_login_at — tanpa memicu password re-hash dengan
        // memakai DB update langsung (bukan ->save()) agar 'hashed' cast
        // tidak mencoba menghash ulang password.
        TblUser::where('idtbluser', $user->idtbluser)
            ->update(['last_login_at' => now()]);

        // Redirect berbasis flag must_change_password.
        if ($user->must_change_password) {
            return redirect()
                ->route('password.change')
                ->with('warning', 'Anda wajib mengganti password sebelum melanjutkan.');
        }

        return redirect()->intended(route('home'));
    }

    /**
     * Logout.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda telah logout.');
    }

    /**
     * Key untuk rate limiting login.
     */
    private function throttleKey(LoginRequest $request): string
    {
        return Str::lower($request->input('login', '')) . '|' . $request->ip();
    }
}
