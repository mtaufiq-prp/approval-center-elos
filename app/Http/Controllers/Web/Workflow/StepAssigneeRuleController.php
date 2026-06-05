<?php

namespace App\Http\Controllers\Web\Workflow;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\StepAssigneeRuleRequest;
use App\Models\TblFlowStep;
use App\Models\TblFlowVersion;
use App\Models\TblStepAssigneeRule;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Assignee Rule hanya berlaku untuk node APPROVAL.
 * Mencegah tambah rule ke non-APPROVAL node.
 */
class StepAssigneeRuleController extends Controller
{
    public function __construct(private AuditTrailService $audit) {}

    public function create(TblFlowVersion $flow_version, TblFlowStep $flow_step): View
    {
        $this->assertApprovalNode($flow_step, $flow_version);
        $this->abortIfLocked($flow_version);
        return view('workflow.assignee_rule.create', [
            'version' => $flow_version->load('flowDefinition'),
            'node'    => $flow_step,
            'types'   => StepAssigneeRuleRequest::ASSIGNEE_TYPES,
        ]);
    }

    public function store(StepAssigneeRuleRequest $request, TblFlowVersion $flow_version, TblFlowStep $flow_step): RedirectResponse
    {
        $this->assertApprovalNode($flow_step, $flow_version);
        $this->abortIfLocked($flow_version);

        $data = $request->validated();
        $data['idtblflow_step'] = $flow_step->idtblflow_step;
        $data['is_required']    = (bool) ($data['is_required'] ?? true);
        $data['is_active']      = (bool) ($data['is_active'] ?? true);
        $data['condition_json'] = $this->parseJson($data['condition_json_raw'] ?? null);
        unset($data['condition_json_raw']);

        $rule = TblStepAssigneeRule::create($data);
        $this->audit->recordCreated($rule, "Assignee Rule untuk node '{$flow_step->node_code}' dibuat.");
        $this->invalidateVersion($flow_version);

        return redirect()->route('workflow.flow-node.edit', [$flow_version->idtblflow_version, $flow_step->idtblflow_step])
            ->with('status', 'Assignee Rule ditambahkan.');
    }

    public function edit(TblFlowVersion $flow_version, TblFlowStep $flow_step, TblStepAssigneeRule $assignee_rule): View
    {
        abort_if($assignee_rule->idtblflow_step !== $flow_step->idtblflow_step, 404);
        return view('workflow.assignee_rule.edit', [
            'version'  => $flow_version->load('flowDefinition'),
            'node'     => $flow_step,
            'item'     => $assignee_rule,
            'types'    => StepAssigneeRuleRequest::ASSIGNEE_TYPES,
            'isLocked' => $this->isLocked($flow_version),
        ]);
    }

    public function update(StepAssigneeRuleRequest $request, TblFlowVersion $flow_version, TblFlowStep $flow_step, TblStepAssigneeRule $assignee_rule): RedirectResponse
    {
        abort_if($assignee_rule->idtblflow_step !== $flow_step->idtblflow_step, 404);
        $this->abortIfLocked($flow_version);

        $original = $assignee_rule->getOriginal();
        $data = $request->validated();
        $data['condition_json'] = $this->parseJson($data['condition_json_raw'] ?? null);
        unset($data['condition_json_raw']);

        $assignee_rule->fill($data);
        $assignee_rule->save();
        if ($assignee_rule->wasChanged()) {
            $this->audit->recordUpdated($assignee_rule, $original, 'Assignee Rule diubah.');
            $this->invalidateVersion($flow_version);
        }

        return redirect()->route('workflow.flow-node.edit', [$flow_version->idtblflow_version, $flow_step->idtblflow_step])
            ->with('status', 'Assignee Rule diubah.');
    }

    public function destroy(TblFlowVersion $flow_version, TblFlowStep $flow_step, TblStepAssigneeRule $assignee_rule): RedirectResponse
    {
        abort_if($assignee_rule->idtblflow_step !== $flow_step->idtblflow_step, 404);
        $this->abortIfLocked($flow_version);

        $assignee_rule->delete();
        $this->audit->recordEvent('tblstep_assignee_rule', null, 'MASTER_DEACTIVATED',
            "Assignee Rule #{$assignee_rule->idtblstep_assignee_rule} di node '{$flow_step->node_code}' dihapus.");
        $this->invalidateVersion($flow_version);

        return redirect()->route('workflow.flow-node.edit', [$flow_version->idtblflow_version, $flow_step->idtblflow_step])
            ->with('status', 'Assignee Rule dihapus.');
    }

    private function assertApprovalNode(TblFlowStep $node, TblFlowVersion $version): void
    {
        abort_if($node->idtblflow_version !== $version->idtblflow_version, 404);
        if (! $node->isApproval()) {
            abort(422, "Assignee Rule hanya berlaku untuk node APPROVAL, bukan {$node->step_type}.");
        }
    }

    // #16: lock otoritatif (ACTIVE OR in-use OR instance RUNNING) — cegah edit assignee
    // rule pada version INACTIVE yang masih punya instance berjalan.
    private function isLocked(TblFlowVersion $v): bool  { return $v->isLocked(); }
    private function abortIfLocked(TblFlowVersion $v): void
    {
        if ($this->isLocked($v)) abort(403, 'Version ini sudah dipakai. Buat Clone.');
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
