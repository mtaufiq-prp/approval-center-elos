<?php

/*
|--------------------------------------------------------------------------
| Authentication Configuration - Approval Center
|--------------------------------------------------------------------------
|
| Konfigurasi auth untuk Approval Center. Provider Eloquent diarahkan
| ke App\Models\TblUser (BUKAN App\Models\User default Laravel) agar
| menghormati schema utama dengan PK idtbluser dan nama table tbluser.
|
*/

return [

    'defaults' => [
        'guard'     => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    | Guard 'web' dipakai oleh middleware('auth'). Provider 'users'
    | didefinisikan di bawah.
    */
    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    | Provider 'users' memakai TblUser. Laravel akan membaca:
    |   - $primaryKey   = 'idtbluser' (dari model TblUser)
    |   - $hidden       = ['password', 'remember_token']
    |   - getAuthPassword() (di-override eksplisit di TblUser)
    |
    | Untuk login dengan kombinasi user_ref ATAU email, kita TIDAK
    | bergantung pada Auth::attempt(['email' => ...]) saja. Lihat
    | LoginController yang melakukan attempt ganda.
    */
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\TblUser::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset
    |--------------------------------------------------------------------------
    | Belum dipakai di Tahap 4 (reset link via email akan ditambahkan
    | terpisah setelah konfigurasi mailer). Konfigurasi di-include agar
    | ServiceProvider Laravel tidak crash.
    */
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */
    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
