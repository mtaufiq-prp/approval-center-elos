<?php

namespace App\Http\Controllers\Web\Workflow;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\FlowEdgeRequest;
use App\Models\TblFlowStep;
use App\Models\TblFlowTransition;
use App\Models\TblFlowVersion;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FlowEdgeController extends Controller
{
    public function __construct(private AuditTrailService $audit) {}

    public function index(TblFlowVersion $flow_version): View
    {
        $edges = $flow_version->edges()
            ->with(['stepFrom', 'stepTo'])
            ->orderBy('idtblflow_step_from')
            ->orderBy('priority_no')
            ->get();
        return view('workflow.flow_edge.index', [
            'version' => $flow_version->load('flowDefinition'),
            'edges'   => $edges,
        ]);
    }

    public function create(TblFlowVersion $flow_version): View
    {
        $this->abortIfLocked($flow_version);
        $nodes = TblFlowStep::where('idtblflow_version', $flow_version->idtblflow_version)
            ->orderBy('step_order')->get();
        return view('workflow.flow_edge.create', [
            'version' => $flow_version->load('flowDefinition'),
            'nodes'   => $nodes,
        ]);
    }

    public function store(FlowEdgeRequest $request, TblFlowVersion $flow_version): RedirectResponse
    {
        $this->abortIfLocked($flow_version);

        $data = $request->validated();
        $data['idtblflow_version'] = $flow_version->idtblflow_version;
        $data['condition_json']    = $this->parseJson($data['condition_json_raw'] ?? null);
        $data['is_default']        = (bool) ($data['is_default'] ?? false);
        $data['is_active']         = (bool) ($data['is_active'] ?? true);
        unset($data['condition_json_raw']);

        $edge = TblFlowTransition::create($data);
        $code = $edge->transition_code ?? "#{$edge->idtblflow_transition}";
        $this->audit->recordCreated($edge, "Edge '{$code}' dibuat di version #{$flow_version->idtblflow_version}.");
        $this->invalidateVersion($flow_version);

        return redirect()->route('workflow.flow-edge.index', $flow_version->idtblflow_version)
            ->with('status', "Edge '{$code}' dibuat.");
    }

    public function edit(TblFlowVersion $flow_version, TblFlowTransition $flow_transition): View
    {
        abort_if($flow_transition->idtblflow_version !== $flow_version->idtblflow_version, 404);
        $nodes = TblFlowStep::where('idtblflow_version', $flow_version->idtblflow_version)
            ->orderBy('step_order')->get();
        return view('workflow.flow_edge.edit', [
            'version'  => $flow_version->load('flowDefinition'),
            'item'     => $flow_transition,
            'nodes'    => $nodes,
            'isLocked' => $this->isLocked($flow_version),
        ]);
    }

    public function update(FlowEdgeRequest $request, TblFlowVersion $flow_version, TblFlowTransition $flow_transition): RedirectResponse
    {
        abort_if($flow_transition->idtblflow_version !== $flow_version->idtblflow_version, 404);
        $this->abortIfLocked($flow_version);

        $original = $flow_transition->getOriginal();
        $data = $request->validated();
        $data['condition_json'] = $this->parseJson($data['condition_json_raw'] ?? null);
        $data['is_default']     = (bool) ($data['is_default'] ?? false);
        $data['is_active']      = (bool) ($data['is_active'] ?? true);
        unset($data['condition_json_raw']);

        $flow_transition->fill($data);
        $flow_transition->save();

        if ($flow_transition->wasChanged()) {
            $this->audit->recordUpdated($flow_transition, $original, "Edge diubah.");
            $this->invalidateVersion($flow_version);
        }

        return redirect()->route('workflow.flow-edge.index', $flow_version->idtblflow_version)
            ->with('status', 'Edge diubah.');
    }

    public function destroy(TblFlowVersion $flow_version, TblFlowTransition $flow_transition): RedirectResponse
    {
        abort_if($flow_transition->idtblflow_version !== $flow_version->idtblflow_version, 404);
        $this->abortIfLocked($flow_version);

        $code = $flow_transition->transition_code ?? "#{$flow_transition->idtblflow_transition}";
        $flow_transition->delete();
        $this->audit->recordEvent('tblflow_transition', null, 'MASTER_DEACTIVATED',
            "Edge '{$code}' dihapus dari version #{$flow_version->idtblflow_version}.");
        $this->invalidateVersion($flow_version);

        return redirect()->route('workflow.flow-edge.index', $flow_version->idtblflow_version)
            ->with('status', "Edge '{$code}' dihapus.");
    }

    private function isLocked(TblFlowVersion $v): bool  { return $v->isActive() && $v->isInUse(); }
    private function abortIfLocked(TblFlowVersion $v): void
    {
        if ($this->isLocked($v)) {
            abort(403, "Version ini sudah dipakai approval request. Buat Clone untuk mengubah edge.");
        }
    }
    private function invalidateVersion(TblFlowVersion $v): void
    {
        if ($v->validation_status !== TblFlowVersion::VALIDATION_DRAFT) {
            $v->validation_status  = TblFlowVersion::VALIDATION_DRAFT;
            $v->validation_message = 'Flow diubah setelah validasi.';
            $v->validated_at       = null;
            $v->save();
        }
    }
    private function parseJson(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') return null;
        $d = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $d : null;
    }
}
