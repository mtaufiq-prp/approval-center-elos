<?php

namespace App\Services;

use App\Models\TblFlowStep;
use App\Models\TblFlowTransition;
use App\Models\TblFlowVersion;
use App\Models\TblStepAssigneeRule;

/**
 * FlowBuilderDataService
 *
 * Menyiapkan data dari database ke format JSON yang siap dipakai
 * oleh React Flow canvas di frontend.
 *
 * Format node mengikuti React Flow node spec:
 *   { id, type, position: {x,y}, data: {...}, ... }
 *
 * Format edge mengikuti React Flow edge spec:
 *   { id, source, target, label, data: {...}, ... }
 */
class FlowBuilderDataService
{
    /**
     * Load semua data builder untuk satu flow version.
     * Return array siap di-json_encode.
     */
    public function load(TblFlowVersion $version): array
    {
        $version->load('flowDefinition');

        $steps = TblFlowStep::where('idtblflow_version', $version->idtblflow_version)
            ->orderBy('step_order')
            ->get();

        $stepIds = $steps->pluck('idtblflow_step')->toArray();

        // Load assignee rules untuk semua APPROVAL node sekaligus
        $allRules = TblStepAssigneeRule::whereIn('idtblflow_step', $stepIds)
            ->where('is_active', 1)
            ->orderBy('priority_no')
            ->get()
            ->groupBy('idtblflow_step');

        // Load semua transitions
        $transitions = TblFlowTransition::where('idtblflow_version', $version->idtblflow_version)
            ->get();

        // Build node map: idtblflow_step → frontend node id
        // Format: node_{idtblflow_step}
        $nodeMap = [];
        foreach ($steps as $step) {
            $nodeMap[$step->idtblflow_step] = 'node_' . $step->idtblflow_step;
        }

        // Build nodes array
        $nodes = [];
        foreach ($steps as $step) {
            $rules = $allRules->get($step->idtblflow_step, collect());

            $nodes[] = [
                'id'              => 'node_' . $step->idtblflow_step,
                'idtblflow_step'  => $step->idtblflow_step,
                'type'            => $step->step_type,   // START|APPROVAL|DECISION|END
                'node_code'       => $step->node_code,
                'label'           => $step->step_name,
                'position'        => [
                    'x' => $step->pos_x !== null ? (int) $step->pos_x : $this->defaultX($step->step_order),
                    'y' => $step->pos_y !== null ? (int) $step->pos_y : 100,
                ],
                'validation_errors'   => [],
                'validation_warnings' => [],
                'data' => [
                    'step_name'       => $step->step_name,
                    'step_type'       => $step->step_type,
                    'gateway_type'    => $step->gateway_type ?? 'NONE',
                    'approval_mode'   => $step->approval_mode,
                    'sla_hours'       => $step->sla_hours,
                    'instruction'     => $step->instruction,
                    'condition_json'  => $step->condition_json,
                    'node_config_json'=> $step->node_config_json,
                    'node_style_json' => $step->node_style_json,
                    'assignee_rules'  => $rules->map(fn($r) => [
                        'idtblstep_assignee_rule' => $r->idtblstep_assignee_rule,
                        'assignee_type'  => $r->assignee_type,
                        'assignee_value' => $r->assignee_value,
                        'priority_no'    => $r->priority_no,
                        'is_required'    => (bool) $r->is_required,
                        'is_active'      => (bool) $r->is_active,
                        'condition_json' => $r->condition_json,
                    ])->values()->toArray(),
                ],
            ];
        }

        // Build edges array
        $edges = [];
        foreach ($transitions as $tr) {
            $sourceId = $nodeMap[$tr->idtblflow_step_from] ?? null;
            $targetId = $tr->idtblflow_step_to ? ($nodeMap[$tr->idtblflow_step_to] ?? null) : null;

            if (! $sourceId) continue; // edge invalid, skip

            $edges[] = [
                'id'                    => 'edge_' . $tr->idtblflow_transition,
                'idtblflow_transition'  => $tr->idtblflow_transition,
                'source'                => $sourceId,
                'target'                => $targetId ?? 'END_VIRTUAL',
                'label'                 => $tr->action_code,
                'validation_errors'     => [],
                'validation_warnings'   => [],
                'data' => [
                    'transition_code'   => $tr->transition_code,
                    'transition_name'   => $tr->transition_name,
                    'transition_type'   => $tr->transition_type ?? 'NORMAL',
                    'action_code'       => $tr->action_code,
                    'priority_no'       => (int) $tr->priority_no,
                    'is_default'        => (bool) $tr->is_default,
                    'is_active'         => (bool) ($tr->is_active ?? true),
                    'final_status'      => $tr->final_status,
                    'condition_json'    => $tr->condition_json,
                    'transition_config_json' => $tr->transition_config_json,
                ],
            ];
        }

        // Viewport dari diagram_json yang tersimpan
        $diagramJson = $version->diagram_json ?? [];
        $viewport    = $diagramJson['viewport'] ?? ['x' => 0, 'y' => 0, 'zoom' => 0.8];

        return [
            'flow_version' => [
                'idtblflow_version' => $version->idtblflow_version,
                'version_no'        => $version->version_no,
                'version_name'      => $version->version_name,
                'status'            => $version->status,
                'validation_status' => $version->validation_status ?? 'DRAFT',
                'validation_message'=> $version->validation_message,
                'validated_at'      => optional($version->validated_at)?->toIso8601String(),
                'is_locked'         => $this->isLocked($version),
                'lock_reason'       => $this->isLocked($version)
                    ? 'Flow version ini sudah ACTIVE dan pernah digunakan oleh approval request. Gunakan Clone untuk perubahan.'
                    : null,
            ],
            'flow_definition' => [
                'idtblflow_definition' => $version->idtblflow_definition,
                'flow_code'  => optional($version->flowDefinition)->flow_code,
                'flow_name'  => optional($version->flowDefinition)->flow_name,
            ],
            'nodes'    => $nodes,
            'edges'    => $edges,
            'viewport' => $viewport,
        ];
    }

    public function isLocked(TblFlowVersion $version): bool
    {
        // #94/#16: delegasi ke sumber kebenaran tunggal di model agar semua jalur
        // (builder, controller legacy node/edge/assignee) memakai aturan lock identik.
        return $version->isLocked();
    }

    /** Posisi X default berdasarkan step_order jika pos_x belum diset */
    private function defaultX(int $stepOrder): int
    {
        return max(80, (int)(($stepOrder / 10) * 220));
    }

    /** Posisi Y default — beri variasi agar tidak semua y=100 */
    private function defaultY(int $stepOrder): int
    {
        // Variasi kecil agar tidak tumpuk
        return 100 + (($stepOrder % 3) * 60);
    }
}
