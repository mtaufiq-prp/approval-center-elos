<?php

namespace App\Http\Controllers\Web\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\RoleRequest;
use App\Models\TblRole;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    /**
     * Role inti yang TIDAK boleh dihapus/dinonaktifkan agar sistem
     * tetap fungsional.
     */
    private const PROTECTED_CODES = ['ADMIN_APPROVAL', 'APPROVER', 'REQUESTER', 'AUDITOR'];

    public function __construct(private AuditTrailService $audit) {}

    public function index(Request $request): View
    {
        $q = TblRole::query();

        if ($s = trim((string) $request->input('search'))) {
            $q->where(fn($w) => $w->where('role_code', 'like', "%$s%")->orWhere('role_name', 'like', "%$s%"));
        }
        if ($request->filled('is_active') && $request->input('is_active') !== 'all') {
            $q->where('is_active', $request->input('is_active'));
        }

        $items = $q->orderBy('role_code')->paginate(15)->withQueryString();
        $protected = self::PROTECTED_CODES;
        return view('master.role.index', compact('items', 'protected'));
    }

    public function create(): View
    {
        return view('master.role.create');
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $row = TblRole::create($data);
        $this->audit->recordCreated($row, "Role {$row->role_code} dibuat.");
        return redirect()->route('master.role.index')->with('status', "Role {$row->role_code} dibuat.");
    }

    public function edit(TblRole $role): View
    {
        return view('master.role.edit', ['item' => $role, 'protected' => self::PROTECTED_CODES]);
    }

    public function update(RoleRequest $request, TblRole $role): RedirectResponse
    {
        $original = $role->getOriginal();
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? $role->is_active);

        // Role inti tidak boleh dinonaktifkan
        if (in_array($role->role_code, self::PROTECTED_CODES, true) && empty($data['is_active'])) {
            return back()->withErrors(['is_active' => "Role inti {$role->role_code} tidak boleh dinonaktifkan."])->withInput();
        }

        // role_code role inti tidak boleh diubah
        if (in_array($role->role_code, self::PROTECTED_CODES, true) && $data['role_code'] !== $role->role_code) {
            return back()->withErrors(['role_code' => "Role inti {$role->role_code} tidak boleh diubah code-nya."])->withInput();
        }

        $role->fill($data);
        $role->save();

        if ($role->wasChanged()) {
            $this->audit->recordUpdated($role, $original, "Role {$role->role_code} diubah.");
        }

        return redirect()->route('master.role.index')->with('status', "Role {$role->role_code} diubah.");
    }

    public function destroy(TblRole $role): RedirectResponse
    {
        if (in_array($role->role_code, self::PROTECTED_CODES, true)) {
            return back()->with('error', "Role inti {$role->role_code} tidak boleh dinonaktifkan.");
        }

        if ($role->is_active) {
            $role->is_active = false;
            $role->save();
            $this->audit->recordDeactivated($role, "Role {$role->role_code} dinonaktifkan.");
            return back()->with('status', "Role {$role->role_code} dinonaktifkan.");
        }

        $role->is_active = true;
        $role->save();
        $this->audit->recordActivated($role, "Role {$role->role_code} diaktifkan.");
        return back()->with('status', "Role {$role->role_code} diaktifkan.");
    }
}
