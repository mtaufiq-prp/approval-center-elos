<?php

namespace App\Services;

use App\Models\TblActionLog;
use App\Models\TblApprovalRequest;
use App\Models\TblFlowStep;
use App\Models\TblProcessInstance;
use App\Models\TblTask;

/**
 * Proyeksi alur persetujuan (read-only) untuk tampilan: tiap node APPROVAL
 * beserta state-nya (done / current / future / rejected / returned), info task,
 * node auto-approve (auto-skip), dan calon approver. Dipakai bersama oleh
 * InboxController (approver) dan TrackController (tracking publik read-only).
 */
class ApprovalRouteService
{
    public function __construct(private FlowEngineService $engine) {}

    /**
     * @return array<int,array{node:TblFlowStep,state:string,task:?TblTask,auto:?array,pending:array}>
     */
    public function build(TblProcessInstance $instance): array
    {
        $routeNodes = $this->engine->projectApprovalRoute($instance);
        if (empty($routeNodes)) {
            return [];
        }

        $stepTasks = TblTask::with(['completedBy', 'assignedUser', 'candidates.user'])
            ->where('idtblprocess_instance', $instance->idtblprocess_instance)
            ->get()
            ->groupBy('idtblflow_step');

        // Node yang di-AUTO_APPROVE (auto-skip approver sama) tidak punya task;
        // dikenali dari action_log AUTO_APPROVE (idtblflow_step_before = node).
        $autoLogs = TblActionLog::where('idtblapproval_request', $instance->idtblapproval_request)
            ->where('action_code', 'AUTO_APPROVE')
            ->get()->keyBy('idtblflow_step_before');

        $currentStepId  = $instance->idtblflow_step_current;
        $instanceClosed = in_array($instance->instance_status, ['COMPLETED', 'REJECTED', 'CANCELLED', 'ERROR']);
        $closedTaskSt   = ['APPROVED', 'REJECTED', 'RETURNED', 'CANCELLED', 'SKIPPED', 'EXPIRED'];

        $route = [];
        foreach ($routeNodes as $node) {
            $tasksForStep = $stepTasks->get($node->idtblflow_step, collect());
            $doneTask     = $tasksForStep->first(fn($t) => in_array($t->task_status, $closedTaskSt));
            $openTasks    = $tasksForStep->filter(fn($t) => in_array($t->task_status, ['OPEN', 'CLAIMED']));
            $autoLog      = $autoLogs->get($node->idtblflow_step);

            if ($doneTask) {
                $state = match ($doneTask->task_status) {
                    'REJECTED'  => 'rejected',
                    'RETURNED'  => 'returned',
                    'CANCELLED' => 'rejected',
                    default     => 'done',
                };
            } elseif ($autoLog) {
                $state = 'done';
            } elseif (! $instanceClosed && ($node->idtblflow_step == $currentStepId || $openTasks->isNotEmpty())) {
                $state = 'current';
            } else {
                $state = 'future';
            }

            $pending = collect();
            if ($state === 'current') {
                foreach ($openTasks as $ot) {
                    if ($ot->assignedUser) {
                        $pending->push(['ref' => $ot->assignedUser->user_ref, 'name' => $ot->assignedUser->full_name]);
                    }
                    foreach ($ot->candidates->where('is_active', 1) as $c) {
                        if ($c->user) {
                            $pending->push(['ref' => $c->user->user_ref, 'name' => $c->user->full_name]);
                        }
                    }
                }
            } elseif ($state === 'future') {
                $pending = $this->resolveProjectedApprovers($node, $instance);
            }

            $auto = ($autoLog && ! $doneTask) ? [
                'name' => $autoLog->actor_name ?: $autoLog->actor_ref,
                'at'   => $autoLog->created_at,
                'note' => $autoLog->action_note,
            ] : null;

            $route[] = [
                'node'    => $node,
                'state'   => $state,
                'task'    => $doneTask,
                'auto'    => $auto,
                'pending' => $pending->unique('ref')->values()->all(),
            ];
        }

        return $route;
    }

    /**
     * Proyeksikan calon approver untuk node yang belum dijalankan (future),
     * tanpa membuat task. Best-effort: kalau resolver gagal, kembalikan kosong.
     */
    private function resolveProjectedApprovers(TblFlowStep $node, TblProcessInstance $instance)
    {
        try {
            $request    = $instance->approvalRequest ?? TblApprovalRequest::find($instance->idtblapproval_request);
            $context    = $request?->context_json ?? [];
            $submitter  = $request?->idtbluser_submitter ?? null;
            $candidates = app(AssigneeResolverService::class)->resolve($node, $context, $submitter);

            return collect($candidates)->map(fn($u) => [
                'ref'  => $u->user_ref ?? null,
                'name' => $u->full_name ?? null,
            ])->filter(fn($x) => $x['ref'] !== null);
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
