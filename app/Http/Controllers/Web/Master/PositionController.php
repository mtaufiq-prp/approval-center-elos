<?php

namespace App\Http\Controllers\Web\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\PositionRequest;
use App\Models\TblOrgUnit;
use App\Models\TblPosition;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PositionController extends Controller
{
    public function __construct(private AuditTrailService $audit) {}

    public function index(Request $request): View
    {
        $q = TblPosition::with('orgUnit');
        if ($s = trim((string) $request->input('search'))) {
            $q->where(fn($w) => $w->where('position_code', 'like', "%$s%")->orWhere('position_name', 'like', "%$s%"));
        }
        if ($request->filled('is_active') && $request->input('is_active') !== 'all') {
            $q->where('is_active', $request->input('is_active'));
        }
        $items = $q->orderBy('position_code')->paginate(15)->withQueryString();
        return view('master.position.index', compact('items'));
    }

    public function create(): View
    {
        return view('master.position.create', ['orgUnits' => TblOrgUnit::where('is_active', 1)->orderBy('org_code')->get()]);
    }

    public function store(PositionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $row = TblPosition::create($data);
        $this->audit->recordCreated($row, "Position {$row->position_code} dibuat.");
        return redirect()->route('master.position.index')->with('status', "Position {$row->position_code} dibuat.");
    }

    public function edit(TblPosition $position): View
    {
        return view('master.position.edit', [
            'item'     => $position,
            'orgUnits' => TblOrgUnit::orderBy('org_code')->get(),
        ]);
    }

    public function update(PositionRequest $request, TblPosition $position): RedirectResponse
    {
        $original = $position->getOriginal();
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? $position->is_active);
        $position->fill($data); $position->save();
        if ($position->wasChanged()) {
            $this->audit->recordUpdated($position, $original, "Position {$position->position_code} diubah.");
        }
        return redirect()->route('master.position.index')->with('status', "Position {$position->position_code} diubah.");
    }

    public function destroy(TblPosition $position): RedirectResponse
    {
        if ($position->is_active) {
            $position->is_active = false; $position->save();
            $this->audit->recordDeactivated($position);
        } else {
            $position->is_active = true; $position->save();
            $this->audit->recordActivated($position);
        }
        return back()->with('status', "Position {$position->position_code} status diubah.");
    }
}
