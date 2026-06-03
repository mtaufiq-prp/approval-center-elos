<?php

namespace App\Http\Controllers\Web\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\OrgUnitRequest;
use App\Models\TblOrgUnit;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrgUnitController extends Controller
{
    public function __construct(private AuditTrailService $audit) {}

    public function index(Request $request): View
    {
        $q = TblOrgUnit::with('parent');
        if ($s = trim((string) $request->input('search'))) {
            $q->where(fn($w) => $w->where('org_code', 'like', "%$s%")->orWhere('org_name', 'like', "%$s%"));
        }
        if ($request->filled('is_active') && $request->input('is_active') !== 'all') {
            $q->where('is_active', $request->input('is_active'));
        }
        $items = $q->orderBy('org_code')->paginate(15)->withQueryString();
        return view('master.org_unit.index', compact('items'));
    }

    public function create(): View
    {
        return view('master.org_unit.create', ['parents' => TblOrgUnit::orderBy('org_code')->get()]);
    }

    public function store(OrgUnitRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $row = TblOrgUnit::create($data);
        $this->audit->recordCreated($row, "Org Unit {$row->org_code} dibuat.");
        return redirect()->route('master.org-unit.index')->with('status', "Org Unit {$row->org_code} dibuat.");
    }

    public function edit(TblOrgUnit $org_unit): View
    {
        return view('master.org_unit.edit', [
            'item'    => $org_unit,
            'parents' => TblOrgUnit::where('idtblorg_unit', '!=', $org_unit->idtblorg_unit)->orderBy('org_code')->get(),
        ]);
    }

    public function update(OrgUnitRequest $request, TblOrgUnit $org_unit): RedirectResponse
    {
        $original = $org_unit->getOriginal();
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? $org_unit->is_active);
        $org_unit->fill($data);
        $org_unit->save();
        if ($org_unit->wasChanged()) {
            $this->audit->recordUpdated($org_unit, $original, "Org Unit {$org_unit->org_code} diubah.");
        }
        return redirect()->route('master.org-unit.index')->with('status', "Org Unit {$org_unit->org_code} diubah.");
    }

    public function destroy(TblOrgUnit $org_unit): RedirectResponse
    {
        if ($org_unit->is_active) {
            $org_unit->is_active = false; $org_unit->save();
            $this->audit->recordDeactivated($org_unit);
        } else {
            $org_unit->is_active = true; $org_unit->save();
            $this->audit->recordActivated($org_unit);
        }
        return back()->with('status', "Org Unit {$org_unit->org_code} status diubah.");
    }
}
