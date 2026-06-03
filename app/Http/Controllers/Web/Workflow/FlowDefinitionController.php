<?php

namespace App\Http\Controllers\Web\Workflow;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\FlowDefinitionRequest;
use App\Models\TblDocumentType;
use App\Models\TblFlowDefinition;
use App\Models\TblSourceApp;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FlowDefinitionController extends Controller
{
    public function __construct(private AuditTrailService $audit) {}

    public function index(Request $request): View
    {
        $q = TblFlowDefinition::with(['sourceApp', 'documentType'])
            ->withCount('versions');
        if ($s = trim((string) $request->input('search'))) {
            $q->where(fn($w) => $w->where('flow_code', 'like', "%$s%")->orWhere('flow_name', 'like', "%$s%"));
        }
        if ($request->filled('idtblsource_app')) {
            $q->where('idtblsource_app', (int) $request->input('idtblsource_app'));
        }
        $items = $q->orderBy('flow_code')->paginate(15)->withQueryString();
        $sourceApps = TblSourceApp::orderBy('app_code')->get();
        return view('workflow.flow_definition.index', compact('items', 'sourceApps'));
    }

    public function create(): View { return view('workflow.flow_definition.create', $this->formData()); }

    public function store(FlowDefinitionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $row = TblFlowDefinition::create($data);
        $this->audit->recordCreated($row, "Flow Definition {$row->flow_code} dibuat.");
        return redirect()->route('workflow.flow-version.index', $row->idtblflow_definition)
            ->with('status', "Flow Definition {$row->flow_code} dibuat. Tambahkan version pertama.");
    }

    public function edit(TblFlowDefinition $flow_definition): View
    {
        return view('workflow.flow_definition.edit', array_merge($this->formData(), ['item' => $flow_definition]));
    }

    public function update(FlowDefinitionRequest $request, TblFlowDefinition $flow_definition): RedirectResponse
    {
        $original = $flow_definition->getOriginal();
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? $flow_definition->is_active);
        $flow_definition->fill($data); $flow_definition->save();
        if ($flow_definition->wasChanged()) {
            $this->audit->recordUpdated($flow_definition, $original, "Flow Definition {$flow_definition->flow_code} diubah.");
        }
        return redirect()->route('workflow.flow-definition.index')->with('status', 'Flow Definition diubah.');
    }

    public function destroy(TblFlowDefinition $flow_definition): RedirectResponse
    {
        if ($flow_definition->is_active) {
            $flow_definition->is_active = false; $flow_definition->save();
            $this->audit->recordDeactivated($flow_definition);
        } else {
            $flow_definition->is_active = true; $flow_definition->save();
            $this->audit->recordActivated($flow_definition);
        }
        return back()->with('status', 'Status flow definition diubah.');
    }

    private function formData(): array
    {
        return [
            'sourceApps'    => TblSourceApp::where('is_active', 1)->orderBy('app_code')->get(),
            'documentTypes' => TblDocumentType::with('sourceApp')->where('is_active', 1)->orderBy('doc_code')->get(),
        ];
    }
}
