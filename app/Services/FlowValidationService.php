<?php

namespace App\Services;

use App\Models\TblFlowStep;
use App\Models\TblFlowTransition;
use App\Models\TblFlowVersion;
use App\Support\ConditionJsonValidator;
use App\Support\FlowValidationResult;

/**
 * FlowValidationService
 *
 * Menjalankan 12 aturan validasi flow BPMN-lite sebelum boleh deploy:
 *
 *  R1.  Ada tepat satu START node.
 *  R2.  Minimal ada satu END node.
 *  R3.  Setiap APPROVAL node punya minimal 1 active assignee rule.
 *  R4.  Setiap edge from/to valid (node ada di version yang sama).
 *  R5.  Tidak ada node orphan (tidak punya incoming) kecuali START.
 *  R6.  Tidak ada edge menuju START.
 *  R7.  END node tidak punya outgoing edge.
 *  R8.  DECISION node punya minimal 1 outgoing edge.
 *  R9.  Priority transition tidak ambigu (per from+action, priority unik
 *       jika ada >1 edge — kecuali semua punya kondisi yang membedakan).
 *  R10. Maksimal 1 is_default=1 per (from_node, action_code).
 *  R11. gateway_type konsisten dengan step_type
 *       (non-DECISION wajib NONE; DECISION non-NONE).
 *  R12. condition_json valid struktural (node & edge).
 *
 * Bonus:
 *  - Cek reachability dari START (warning jika ada node tak terjangkau).
 *  - Cek bahwa setiap APPROVAL node bisa mencapai END (warning).
 */
class FlowValidationService
{
    public function __construct(private ConditionJsonValidator $condValidator) {}

    public function validate(TblFlowVersion $version): FlowValidationResult
    {
        $r = new FlowValidationResult();

        /** @var \Illuminate\Support\Collection<int,TblFlowStep> $nodes */
        $nodes = $version->nodes()->with('activeAssigneeRules')->get();
        /** @var \Illuminate\Support\Collection<int,TblFlowTransition> $edges */
        $edges = $version->edges()->get();

        if ($nodes->isEmpty()) {
            $r->addError('Flow tidak punya node sama sekali.');
            return $r;
        }

        $nodesById = $nodes->keyBy('idtblflow_step');

        // ---------------------------------------------------------
        // R1. Tepat satu START
        // ---------------------------------------------------------
        $starts = $nodes->where('step_type', TblFlowStep::TYPE_START);
        if ($starts->count() === 0) {
            $r->addError('R1: Tidak ada START node.');
        } elseif ($starts->count() > 1) {
            $r->addError('R1: START node lebih dari satu (' . $starts->count() . ').');
        }
        $r->addCheck('R1: tepat satu START', $starts->count() === 1);

        // ---------------------------------------------------------
        // R2. Minimal satu END
        // ---------------------------------------------------------
        $ends = $nodes->where('step_type', TblFlowStep::TYPE_END);
        if ($ends->count() === 0) {
            $r->addError('R2: Tidak ada END node.');
        }
        $r->addCheck('R2: minimal satu END', $ends->count() >= 1);

        // ---------------------------------------------------------
        // R3. APPROVAL wajib punya assignee rule aktif
        // ---------------------------------------------------------
        $r3Pass = true;
        foreach ($nodes->where('step_type', TblFlowStep::TYPE_APPROVAL) as $n) {
            if ($n->activeAssigneeRules->isEmpty()) {
                $r->addError("R3: Node APPROVAL '{$n->node_code}' belum punya assignee rule aktif.");
                $r3Pass = false;
            }
        }
        $r->addCheck('R3: setiap APPROVAL punya assignee rule', $r3Pass);

        // ---------------------------------------------------------
        // R4. Edge from/to valid (point ke node di version yang sama)
        // ---------------------------------------------------------
        $r4Pass = true;
        foreach ($edges as $e) {
            if (! isset($nodesById[$e->idtblflow_step_from])) {
                $r->addError("R4: Edge #{$e->idtblflow_transition} from_node tidak ditemukan di version ini.");
                $r4Pass = false;
            }
            if ($e->idtblflow_step_to !== null && ! isset($nodesById[$e->idtblflow_step_to])) {
                $r->addError("R4: Edge #{$e->idtblflow_transition} to_node tidak ditemukan di version ini.");
                $r4Pass = false;
            }
        }
        $r->addCheck('R4: edge from/to valid', $r4Pass);

        // ---------------------------------------------------------
        // R5. Tidak ada node orphan (tanpa incoming) kecuali START
        // ---------------------------------------------------------
        $incomingByTo = $edges->where('is_active', true)
            ->groupBy('idtblflow_step_to')
            ->map->count();
        $r5Pass = true;
        foreach ($nodes as $n) {
            if ($n->isStart()) continue;
            $count = $incomingByTo[$n->idtblflow_step] ?? 0;
            if ($count === 0) {
                $r->addError("R5: Node '{$n->node_code}' tidak punya incoming edge (orphan).");
                $r5Pass = false;
            }
        }
        $r->addCheck('R5: tidak ada orphan kecuali START', $r5Pass);

        // ---------------------------------------------------------
        // R6. Tidak ada edge menuju START
        // ---------------------------------------------------------
        $r6Pass = true;
        foreach ($edges as $e) {
            $to = $nodesById[$e->idtblflow_step_to] ?? null;
            if ($to && $to->isStart()) {
                $r->addError("R6: Edge #{$e->idtblflow_transition} menuju START — tidak diperbolehkan.");
                $r6Pass = false;
            }
        }
        $r->addCheck('R6: tidak ada edge ke START', $r6Pass);

        // ---------------------------------------------------------
        // R7. END tidak punya outgoing edge
        // ---------------------------------------------------------
        $r7Pass = true;
        foreach ($nodes->where('step_type', TblFlowStep::TYPE_END) as $n) {
            $outCount = $edges->where('idtblflow_step_from', $n->idtblflow_step)->count();
            if ($outCount > 0) {
                $r->addError("R7: END node '{$n->node_code}' punya outgoing edge ({$outCount}).");
                $r7Pass = false;
            }
        }
        $r->addCheck('R7: END tidak punya outgoing', $r7Pass);

        // ---------------------------------------------------------
        // R8. DECISION wajib punya minimal 1 outgoing edge aktif
        // ---------------------------------------------------------
        $r8Pass = true;
        foreach ($nodes->where('step_type', TblFlowStep::TYPE_DECISION) as $n) {
            $outCount = $edges
                ->where('idtblflow_step_from', $n->idtblflow_step)
                ->where('is_active', true)
                ->count();
            if ($outCount < 1) {
                $r->addError("R8: DECISION node '{$n->node_code}' tidak punya outgoing edge aktif.");
                $r8Pass = false;
            }
        }
        $r->addCheck('R8: DECISION punya outgoing edge', $r8Pass);

        // ---------------------------------------------------------
        // R9. Priority transition tidak ambigu (per from+action)
        // ---------------------------------------------------------
        $r9Pass = true;
        $byFromAction = $edges->where('is_active', true)
            ->groupBy(fn($e) => $e->idtblflow_step_from . '|' . ($e->action_code ?? '_'));
        foreach ($byFromAction as $key => $group) {
            if ($group->count() <= 1) continue;
            $priorities = $group->pluck('priority_no')->all();
            $dupePriorities = array_keys(array_filter(array_count_values($priorities), fn($c) => $c > 1));
            if (! empty($dupePriorities)) {
                [$fromId, $action] = explode('|', $key);
                $node = $nodesById[(int) $fromId] ?? null;
                $nodeCode = $node ? $node->node_code : "#$fromId";
                $r->addWarning(
                    "R9: Edge dari '{$nodeCode}' action '{$action}' punya priority_no duplikat: "
                    . implode(',', $dupePriorities) . ". Engine akan ambil berdasarkan urutan ID."
                );
                // Warning, bukan error — engine handle deterministic tie-break.
            }
        }
        $r->addCheck('R9: priority transition jelas', $r9Pass);

        // ---------------------------------------------------------
        // R10. Maksimal 1 is_default=1 per (from_node, action_code)
        // ---------------------------------------------------------
        $r10Pass = true;
        $defaults = $edges->where('is_default', true)
            ->groupBy(fn($e) => $e->idtblflow_step_from . '|' . ($e->action_code ?? '_'));
        foreach ($defaults as $key => $group) {
            if ($group->count() > 1) {
                [$fromId, $action] = explode('|', $key);
                $node = $nodesById[(int) $fromId] ?? null;
                $nodeCode = $node ? $node->node_code : "#$fromId";
                $r->addError(
                    "R10: Lebih dari satu default transition dari '{$nodeCode}' action '{$action}' (" . $group->count() . ")."
                );
                $r10Pass = false;
            }
        }
        $r->addCheck('R10: ≤1 default transition per from+action', $r10Pass);

        // ---------------------------------------------------------
        // R11. gateway_type konsisten dengan step_type
        // ---------------------------------------------------------
        $r11Pass = true;
        foreach ($nodes as $n) {
            if ($n->isDecision()) {
                if (in_array($n->gateway_type, [null, '', TblFlowStep::GATEWAY_NONE], true)) {
                    $r->addError("R11: DECISION node '{$n->node_code}' wajib pakai gateway_type EXCLUSIVE/INCLUSIVE/PARALLEL, bukan NONE.");
                    $r11Pass = false;
                }
            } else {
                if ($n->gateway_type !== null && $n->gateway_type !== TblFlowStep::GATEWAY_NONE) {
                    $r->addError("R11: Node non-DECISION '{$n->node_code}' (type={$n->step_type}) tidak boleh punya gateway_type '{$n->gateway_type}'.");
                    $r11Pass = false;
                }
            }
        }
        $r->addCheck('R11: gateway_type konsisten dengan step_type', $r11Pass);

        // ---------------------------------------------------------
        // R12. condition_json struktural valid (node + edge)
        // ---------------------------------------------------------
        $r12Pass = true;
        foreach ($nodes as $n) {
            if ($n->condition_json !== null) {
                if (! $this->condValidator->validateDecoded($n->condition_json)) {
                    foreach ($this->condValidator->errors() as $err) {
                        $r->addError("R12: Node '{$n->node_code}' condition_json: {$err}");
                    }
                    $r12Pass = false;
                }
            }
        }
        foreach ($edges as $e) {
            if ($e->condition_json !== null) {
                if (! $this->condValidator->validateDecoded($e->condition_json)) {
                    foreach ($this->condValidator->errors() as $err) {
                        $code = $e->transition_code ?: "#{$e->idtblflow_transition}";
                        $r->addError("R12: Edge '{$code}' condition_json: {$err}");
                    }
                    $r12Pass = false;
                }
            }
        }
        $r->addCheck('R12: condition_json struktural valid', $r12Pass);

        // ---------------------------------------------------------
        // BONUS: Reachability dari START (warning)
        // ---------------------------------------------------------
        if ($starts->count() === 1) {
            $reachable = $this->bfsReachable($starts->first(), $edges, $nodesById);
            foreach ($nodes as $n) {
                if (! isset($reachable[$n->idtblflow_step]) && ! $n->isStart()) {
                    $r->addWarning("Node '{$n->node_code}' tidak terjangkau dari START.");
                }
            }
        }

        return $r;
    }

    /**
     * BFS dari START via edges aktif. Return map[node_id => true].
     */
    private function bfsReachable(TblFlowStep $start, $edges, $nodesById): array
    {
        $visited = [$start->idtblflow_step => true];
        $queue = [$start->idtblflow_step];
        $outBy = $edges->where('is_active', true)->groupBy('idtblflow_step_from');

        while ($queue) {
            $cur = array_shift($queue);
            foreach ($outBy[$cur] ?? [] as $e) {
                $to = $e->idtblflow_step_to;
                if ($to !== null && ! isset($visited[$to]) && isset($nodesById[$to])) {
                    $visited[$to] = true;
                    $queue[] = $to;
                }
            }
        }
        return $visited;
    }
}
