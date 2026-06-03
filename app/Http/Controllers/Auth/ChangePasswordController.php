<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\TblUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Change Password.
 *
 * Halaman ini DAPAT diakses meskipun must_change_password = 1
 * (ForcePasswordChange middleware mengijinkan rute ini sebagai
 * pengecualian).
 *
 * Setelah berhasil ganti password:
 *  - must_change_password   = 0
 *  - password_changed_at    = now()
 *  - regenerate session token (mencegah session fixation)
 */
class ChangePasswordController extends Controller
{
    public function show(): View
    {
        return view('auth.change-password');
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        /** @var TblUser $user */
        $user = Auth::user();
        $newPassword = $request->validated()['password'];

        // Update via DB::table-style untuk menghindari double-hashing.
        // Karena $casts 'password' => 'hashed' di TblUser, set langsung
        // via Eloquent juga aman, namun update() di builder tidak melalui
        // cast — jadi kita hash manual untuk konsistensi & kejelasan.
        TblUser::where('idtbluser', $user->idtbluser)->update([
            'password'             => Hash::make($newPassword),
            'must_change_password' => 0,
            'password_changed_at'  => now(),
            'remember_token'       => null, // invalidasi remember token lama
        ]);

        // Regenerate session token agar session lama (jika ada di device
        // lain) tetap valid, tetapi token CSRF baru.
        $request->session()->regenerate();

        return redirect()->route('home')
            ->with('status', 'Password berhasil diganti.');
    }
}
