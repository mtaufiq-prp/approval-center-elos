<?php

namespace App\Http\Controllers\Web\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\UserRequest;
use App\Models\TblActionLog;
use App\Models\TblOrgUnit;
use App\Models\TblPosition;
use App\Models\TblRole;
use App\Models\TblUser;
use App\Models\TblUserRole;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * CRUD User.
 *
 * Fitur khusus:
 *  - Assign role (multi)
 *  - Reset password → password sementara di-flash sekali; must_change_password = 1
 *  - User TIDAK dihapus jika sudah jadi actor di tblaction_log.
 *    Untuk nonaktif gunakan is_active = 0.
 */
class UserController extends Controller
{
    public function __construct(private AuditTrailService $audit) {}

    public function index(Request $request): View
    {
        $q = TblUser::with('orgUnit', 'position', 'roles');

        if ($s = trim((string) $request->input('search'))) {
            $q->where(fn($w) => $w->where('user_ref', 'like', "%$s%")
                                  ->orWhere('full_name', 'like', "%$s%")
                                  ->orWhere('email', 'like', "%$s%"));
        }
        if ($request->filled('is_active') && $request->input('is_active') !== 'all') {
            $q->where('is_active', $request->input('is_active'));
        }
        if ($request->filled('role_id')) {
            $q->whereHas('roles', fn($rq) => $rq->where('tblrole.idtblrole', (int) $request->input('role_id')));
        }

        $items = $q->orderBy('user_ref')->paginate(15)->withQueryString();
        $roles = TblRole::where('is_active', 1)->orderBy('role_code')->get();

        return view('master.user.index', compact('items', 'roles'));
    }

    public function create(): View
    {
        return view('master.user.create', $this->formData());
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $roleIds = $data['role_ids'] ?? [];
        unset($data['role_ids']);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        // Saat create, password default kosong (NULL). Admin wajib panggil
        // tombol "Reset Password" untuk set password awal. Atau bisa
        // ditambah field password initial di form (Tahap 5B kalau perlu).
        $row = DB::transaction(function () use ($data, $roleIds) {
            $u = TblUser::create($data);
            $this->syncRoles($u, $roleIds);
            return $u;
        });

        $this->audit->recordCreated($row, "User {$row->user_ref} dibuat.", extraRedact: ['password']);
        if (! empty($roleIds)) {
            $this->audit->recordEvent('tbluser', $row->idtbluser, 'ROLE_ASSIGNED',
                "Role assigned saat create: " . implode(',', $this->roleCodesFor($roleIds)),
                ['role_ids' => $roleIds]);
        }

        return redirect()->route('master.user.index')
            ->with('status', "User {$row->user_ref} dibuat. Gunakan tombol Reset Password untuk set password awal.");
    }

    public function edit(TblUser $user): View
    {
        return view('master.user.edit', array_merge($this->formData(), ['item' => $user]));
    }

    public function update(UserRequest $request, TblUser $user): RedirectResponse
    {
        $original = $user->getOriginal();
        $data = $request->validated();
        $roleIds = $data['role_ids'] ?? [];
        unset($data['role_ids']);
        $data['is_active'] = (bool) ($data['is_active'] ?? $user->is_active);

        DB::transaction(function () use ($user, $data, $roleIds, $original) {
            $user->fill($data);
            $user->save();

            // Diff role
            $beforeRoleIds = $user->roles()->pluck('tblrole.idtblrole')->toArray();
            $afterRoleIds  = array_values(array_unique(array_map('intval', $roleIds)));

            $added   = array_diff($afterRoleIds, $beforeRoleIds);
            $removed = array_diff($beforeRoleIds, $afterRoleIds);

            $this->syncRoles($user, $afterRoleIds);

            if ($user->wasChanged()) {
                $this->audit->recordUpdated($user, $original, "User {$user->user_ref} diubah.", extraRedact: ['password']);
            }
            if ($added) {
                $this->audit->recordEvent('tbluser', $user->idtbluser, 'ROLE_ASSIGNED',
                    "Roles assigned: " . implode(',', $this->roleCodesFor(array_values($added))));
            }
            if ($removed) {
                $this->audit->recordEvent('tbluser', $user->idtbluser, 'ROLE_REMOVED',
                    "Roles removed: " . implode(',', $this->roleCodesFor(array_values($removed))));
            }
        });

        return redirect()->route('master.user.index')->with('status', "User {$user->user_ref} diubah.");
    }

    /**
     * Reset password.
     * Generate password sementara, set must_change_password = 1.
     */
    public function resetPassword(TblUser $user): RedirectResponse
    {
        $temp = Str::random(12);

        TblUser::where('idtbluser', $user->idtbluser)->update([
            'password'             => Hash::make($temp),
            'must_change_password' => 1,
            'password_changed_at'  => now(),
            'remember_token'       => null,
        ]);

        $this->audit->recordEvent(
            entityType: 'tbluser',
            entityId:   $user->idtbluser,
            eventCode:  'USER_PASSWORD_RESET',
            message:    "Password user {$user->user_ref} di-reset.",
        );

        // Flash one-time
        return redirect()
            ->route('master.user.edit', $user->idtbluser)
            ->with('temp_password', $temp)
            ->with('reset_user_ref', $user->user_ref)
            ->with('status', 'Password sementara berhasil dibuat. Sampaikan ke user via channel aman; tidak akan ditampilkan ulang.');
    }

    /**
     * Soft deactivate. Tidak hard delete kalau user sudah punya audit/action.
     */
    public function destroy(TblUser $user): RedirectResponse
    {
        // Tidak boleh nonaktifkan diri sendiri
        if ($user->idtbluser === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        if ($user->is_active) {
            $user->is_active = false;
            $user->save();
            $this->audit->recordDeactivated($user, "User {$user->user_ref} dinonaktifkan.");
            return back()->with('status', "User {$user->user_ref} dinonaktifkan.");
        }

        $user->is_active = true;
        $user->save();
        $this->audit->recordActivated($user, "User {$user->user_ref} diaktifkan.");
        return back()->with('status', "User {$user->user_ref} diaktifkan.");
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function formData(): array
    {
        return [
            'orgUnits'  => TblOrgUnit::where('is_active', 1)->orderBy('org_code')->get(),
            'positions' => TblPosition::where('is_active', 1)->orderBy('position_code')->get(),
            'roles'     => TblRole::where('is_active', 1)->orderBy('role_code')->get(),
            'superiors' => TblUser::where('is_active', 1)->orderBy('user_ref')->limit(500)->get(),
        ];
    }

    private function syncRoles(TblUser $user, array $roleIds): void
    {
        // Hard delete pivot (Anda izinkan untuk mapping pivot).
        TblUserRole::where('idtbluser', $user->idtbluser)->delete();
        foreach (array_unique($roleIds) as $rid) {
            TblUserRole::create([
                'idtbluser' => $user->idtbluser,
                'idtblrole' => (int) $rid,
            ]);
        }
    }

    private function roleCodesFor(array $roleIds): array
    {
        return TblRole::whereIn('idtblrole', $roleIds)->pluck('role_code')->toArray();
    }
}
