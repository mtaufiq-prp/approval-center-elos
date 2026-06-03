<?php

namespace App\Providers;

use App\Models\TblUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * AuthServiceProvider
 *
 * Mendaftarkan 4 Gate berbasis role_code dari schema:
 *   - role.admin     (ADMIN_APPROVAL)
 *   - role.approver  (APPROVER)
 *   - role.requester (REQUESTER)
 *   - role.auditor   (AUDITOR)
 *
 * Dipakai oleh Blade (@can('role.admin')) untuk show/hide menu, atau
 * controller (Gate::authorize). Authorization yang LEBIH KETAT untuk
 * route group tetap memakai middleware 'role:KODE_ROLE'.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $map = config('approval_center.roles', []);

        Gate::define('role.admin', function (TblUser $user) use ($map) {
            return $user->hasAnyRole($map['admin'] ?? 'ADMIN_APPROVAL');
        });

        Gate::define('role.approver', function (TblUser $user) use ($map) {
            return $user->hasAnyRole($map['approver'] ?? 'APPROVER');
        });

        Gate::define('role.requester', function (TblUser $user) use ($map) {
            return $user->hasAnyRole($map['requester'] ?? 'REQUESTER');
        });

        Gate::define('role.auditor', function (TblUser $user) use ($map) {
            return $user->hasAnyRole($map['auditor'] ?? 'AUDITOR');
        });
    }
}
