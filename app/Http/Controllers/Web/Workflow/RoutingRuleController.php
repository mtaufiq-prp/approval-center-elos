<?php

namespace App\Http\Controllers\Web\Workflow;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\RoutingRuleRequest;
use App\Models\TblDocumentType;
use App\Models\TblFlowDefinition;
use App\Models\TblFlowVersion;
use App\Models\TblRoutingRule;
use App\Models\TblSourceApp;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoutingRuleController extends Controller
{
    public function __construct(private AuditTrailService $audit) {}

    public function index(Request $request): View
    {
        $q = TblRoutingRule::with(['sourceApp', 'documentType', 'flowDefinition', 'flowVersion']);
        if ($s = trim((string) $request->input('search'))) {
            $q->where(fn($w) => $w->where('rule_code', 'like', "%$s%")->orWhere('rule_name', 'like', "%$s%"));
        }
        if ($request->filled('idtblsource_app')) {
            $q->where('idtblsource_app', (int) $request->input('idtblsource_app'));
        }
        $items = $q->orderBy('priority_no')->paginate(15)->withQueryString();
        $sourceApps = TblSourceApp::orderBy('app_code')->get();
        return view('workflow.routing_rule.index', compact('items', 'sourceApps'));
    }

    public function create(): View { return view('workflow.routing_rule.create', $this->formData()); }

    public function store(RoutingRuleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active']        = (bool) ($data['is_active'] ?? true);
        $data['condition_json']   = $this->parseJson($data['condition_json_raw'] ?? null) ?? [];
        unset($data['condition_json_raw']);
        $row = TblRoutingRule::create($data);
        $this->audit->recordCreated($row, "Routing Rule {$row->rule_code} dibuat.");
        return redirect()->route('workflow.routing-rule.index')->with('status', "Routing Rule {$row->rule_code} dibuat.");
    }

    public function edit(TblRoutingRule $routing_rule): View
    {
        return view('workflow.routing_rule.edit', array_merge($this->formData(), ['item' => $routing_rule]));
    }

    public function update(RoutingRuleRequest $request, TblRoutingRule $routing_rule): RedirectResponse
    {
        $original = $routing_rule->getOriginal();
        $data = $request->validated();
        $data['is_active']      = (bool) ($data['is_active'] ?? $routing_rule->is_active);
        $data['condition_json'] = $this->parseJson($data['condition_json_raw'] ?? null) ?? [];
        unset($data['condition_json_raw']);
        $routing_rule->fill($data); $routing_rule->save();
        if ($routing_rule->wasChanged()) {
            $this->audit->recordUpdated($routing_rule, $original, "Routing Rule {$routing_rule->rule_code} diubah.");
        }
        return redirect()->route('workflow.routing-rule.index')->with('status', 'Routing Rule diubah.');
    }

    public function destroy(TblRoutingRule $routing_rule): RedirectResponse
    {
        if ($routing_rule->is_active) {
            $routing_rule->is_active = false; $routing_rule->save();
            $this->audit->recordDeactivated($routing_rule);
        } else {
            $routing_rule->is_active = true; $routing_rule->save();
            $this->audit->recordActivated($routing_rule);
        }
        return back()->with('status', 'Status routing rule diubah.');
    }

    private function formData(): array
    {
        return [
            'sourceApps'       => TblSourceApp::where('is_active', 1)->orderBy('app_code')->get(),
            'documentTypes'    => TblDocumentType::with('sourceApp')->where('is_active', 1)->orderBy('doc_code')->get(),
            'flowDefinitions'  => TblFlowDefinition::where('is_active', 1)->orderBy('flow_code')->get(),
            'flowVersions'     => TblFlowVersion::where('status', TblFlowVersion::STATUS_ACTIVE)->orderBy('idtblflow_definition')->orderByDesc('version_no')->get(),
        ];
    }
    private function parseJson(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') return null;
        $d = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $d : null;
    }
}
