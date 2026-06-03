<?php

namespace App\Http\Controllers\Web\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\DelegationRequest;
use App\Models\TblDelegation;
use App\Models\TblDocumentType;
use App\Models\TblSourceApp;
use App\Models\TblUser;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DelegationController extends Controller
{
    public function __construct(private AuditTrailService $audit) {}

    public function index(Request $request): View
    {
        $q = TblDelegation::with(['delegator', 'delegate', 'sourceApp', 'documentType']);

        if ($s = trim((string) $request->input('search'))) {
            $q->whereHas('delegator', fn($qq) => $qq->where('user_ref', 'like', "%$s%")->orWhere('full_name', 'like', "%$s%"))
              ->orWhereHas('delegate', fn($qq) => $qq->where('user_ref', 'like', "%$s%")->orWhere('full_name', 'like', "%$s%"));
        }

        $now = now();
        if ($request->input('status') === 'active') {
            $q->where('is_active', 1)->where('start_at', '<=', $now)->where('end_at', '>=', $now);
        } elseif ($request->input('status') === 'expired') {
            $q->where('end_at', '<', $now);
        } elseif ($request->input('status') === 'future') {
            $q->where('start_at', '>', $now);
        }

        $items = $q->orderByDesc('idtbldelegation')->paginate(15)->withQueryString();
        return view('master.delegation.index', compact('items'));
    }

    public function create(): View
    {
        return view('master.delegation.create', $this->formData());
    }

    public function store(DelegationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['idtbluser_created_by'] = auth()->id();
        $row = TblDelegation::create($data);
        $this->audit->recordCreated($row, "Delegation #{$row->idtbldelegation} dibuat.");
        return redirect()->route('master.delegation.index')->with('status', "Delegation dibuat.");
    }

    public function edit(TblDelegation $delegation): View
    {
        return view('master.delegation.edit', array_merge($this->formData(), ['item' => $delegation]));
    }

    public function update(DelegationRequest $request, TblDelegation $delegation): RedirectResponse
    {
        $original = $delegation->getOriginal();
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? $delegation->is_active);
        $delegation->fill($data); $delegation->save();
        if ($delegation->wasChanged()) {
            $this->audit->recordUpdated($delegation, $original, "Delegation #{$delegation->idtbldelegation} diubah.");
        }
        return redirect()->route('master.delegation.index')->with('status', 'Delegation diubah.');
    }

    public function destroy(TblDelegation $delegation): RedirectResponse
    {
        if ($delegation->is_active) {
            $delegation->is_active = false; $delegation->save();
            $this->audit->recordDeactivated($delegation, "Delegation #{$delegation->idtbldelegation} dihentikan.");
        } else {
            $delegation->is_active = true; $delegation->save();
            $this->audit->recordActivated($delegation);
        }
        return back()->with('status', 'Status delegation diubah.');
    }

    private function formData(): array
    {
        return [
            'users'         => TblUser::where('is_active', 1)->orderBy('user_ref')->limit(500)->get(),
            'sourceApps'    => TblSourceApp::orderBy('app_code')->get(),
            'documentTypes' => TblDocumentType::with('sourceApp')->orderBy('doc_code')->get(),
        ];
    }
}
