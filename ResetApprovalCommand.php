<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\TblApprovalRequest;
use App\Models\TblProcessInstance;
use App\Models\TblFlowStep;
use App\Models\TblTask;
use App\Models\TblTaskCandidate;
use App\Models\TblProcessRouteLog;

/**
 * php artisan approval:reset {request_id} {node_code?}
 *
 * Reset approval request ke step tertentu untuk keperluan trial/testing.
 * HANYA untuk environment development/staging — JANGAN dipakai di production.
 *
 * Contoh:
 *   php artisan approval:reset 5           → reset ke node BMH (step pertama APPROVAL)
 *   php artisan approval:reset 5 RRM       → reset ke node RRM
 *   php artisan approval:reset 5 --list    → tampilkan state saat ini tanpa reset
 */
class ResetApprovalCommand extends Command
{
    protected $signature = 'approval:reset
                            {request_id : ID tblapproval_request}
                            {node_code? : Node tujuan reset (default: node APPROVAL pertama)}
                            {--list : Hanya tampilkan state, tidak reset}
                            {--force : Skip konfirmasi}';

    protected $description = '[DEV ONLY] Reset approval request ke step tertentu untuk trial ulang';

    public function handle(): int
    {
        $reqId    = (int) $this->argument('request_id');
        $nodeCode = strtoupper($this->argument('node_code') ?? '');
        $listOnly = $this->option('list');

        // ── Load request ─────────────────────────────────────────────────
        $req = TblApprovalRequest::find($reqId);
        if (! $req) {
            $this->error("Approval request #{$reqId} tidak ditemukan.");
            return 1;
        }

        $instance = $req->processInstance;
        if (! $instance) {
            $this->error("Process instance tidak ditemukan untuk request #{$reqId}.");
            return 1;
        }

        // ── Tampilkan state saat ini ──────────────────────────────────────
        $this->info('');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("  Request #{$reqId}: {$req->title}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line("  request_status   : {$req->request_status}");
        $this->line("  instance_status  : {$instance->instance_status}");

        $currentStep = TblFlowStep::find($req->idtblflow_step_current);
        $this->line("  current_step     : " . ($currentStep ? "{$currentStep->node_code} — {$currentStep->step_name}" : 'NULL'));

        $this->info('');
        $this->info('  Daftar Task:');

        $tasks = TblTask::where('idtblapproval_request', $reqId)
            ->orderBy('created_at')
            ->get();

        $headers = ['ID', 'Node', 'Task No', 'Status', 'Decision', 'Completed By', 'Created'];
        $rows = $tasks->map(function ($t) {
            $step = TblFlowStep::find($t->idtblflow_step);
            return [
                $t->idtbltask,
                $step?->node_code ?? '?',
                $t->task_no,
                $t->task_status,
                $t->decision_code ?? '—',
                $t->idtbluser_completed_by ?? '—',
                $t->created_at?->format('d/m H:i'),
            ];
        })->toArray();

        $this->table($headers, $rows);

        $this->info('');
        $this->info('  Route Log (5 terbaru):');

        $logs = TblProcessRouteLog::where('idtblapproval_request', $reqId)
            ->orderByDesc('created_at')->limit(5)->get()->reverse();
        foreach ($logs as $log) {
            $step = TblFlowStep::find($log->idtblflow_step);
            $this->line("  [{$log->created_at?->format('d/m H:i:s')}] "
                . ($step?->node_code ?? 'SYSTEM') . " | {$log->route_event}"
                . ($log->action_code ? " | {$log->action_code}" : '')
                . " | " . \Illuminate\Support\Str::limit($log->message, 60));
        }

        if ($listOnly) {
            $this->info('');
            $this->info('  (--list mode: tidak ada perubahan)');
            return 0;
        }

        // ── Tentukan target node ──────────────────────────────────────────
        $flowVersionId = $instance->idtblflow_version;

        if ($nodeCode) {
            $targetStep = TblFlowStep::where('idtblflow_version', $flowVersionId)
                ->where('node_code', $nodeCode)->first();
            if (! $targetStep) {
                $this->error("Node '{$nodeCode}' tidak ditemukan di flow version #{$flowVersionId}.");
                $this->showAvailableNodes($flowVersionId);
                return 1;
            }
        } else {
            // Default: node APPROVAL pertama
            $targetStep = TblFlowStep::where('idtblflow_version', $flowVersionId)
                ->where('step_type', 'APPROVAL')
                ->orderBy('step_order')
                ->first();
            if (! $targetStep) {
                $this->error("Tidak ada APPROVAL node di flow ini.");
                return 1;
            }
        }

        $this->info('');
        $this->warn("  ⚠️  RESET ke node: [{$targetStep->node_code}] {$targetStep->step_name}");
        $this->warn("  Yang akan terjadi:");
        $this->warn("    1. Semua task di node {$targetStep->node_code} dan sesudahnya → DIHAPUS");
        $this->warn("    2. Task {$targetStep->node_code} baru dibuat dengan status OPEN");
        $this->warn("    3. idtblflow_step_current di-reset ke {$targetStep->node_code}");
        $this->warn("    4. request_status → IN_PROGRESS, instance_status → RUNNING");
        $this->warn("    5. Route log dari node ini ke atas → DIHAPUS");

        if (! $this->option('force') && ! $this->confirm('  Lanjutkan reset?')) {
            $this->info('  Dibatalkan.');
            return 0;
        }

        // ── Eksekusi reset ────────────────────────────────────────────────
        DB::transaction(function () use ($req, $instance, $targetStep, $tasks) {
            $targetStepOrder = $targetStep->step_order;

            // 1. Tentukan node mana yang step_order >= target → hapus tasknya
            $stepsToReset = TblFlowStep::where('idtblflow_version', $instance->idtblflow_version)
                ->where('step_order', '>=', $targetStepOrder)
                ->pluck('idtblflow_step');

            // 2. Hapus task candidates dan task untuk step yang direset
            $taskIdsToDelete = TblTask::where('idtblapproval_request', $req->idtblapproval_request)
                ->whereIn('idtblflow_step', $stepsToReset)
                ->pluck('idtbltask');

            if ($taskIdsToDelete->isNotEmpty()) {
                TblTaskCandidate::whereIn('task_id', $taskIdsToDelete)->delete();
                TblTask::whereIn('idtbltask', $taskIdsToDelete)->delete();
                $this->line("  → {$taskIdsToDelete->count()} task dihapus");
            }

            // 3. Hapus route log dari step ini ke atas
            $routeLogDeleted = TblProcessRouteLog::where('idtblapproval_request', $req->idtblapproval_request)
                ->whereIn('idtblflow_step', $stepsToReset)
                ->delete();
            $this->line("  → {$routeLogDeleted} route log dihapus");

            // 4. Reset instance
            $instance->instance_status        = 'RUNNING';
            $instance->idtblflow_step_current = $targetStep->idtblflow_step;
            $instance->ended_at               = null;
            $instance->save();

            // 5. Reset request
            $req->request_status        = 'IN_PROGRESS';
            $req->idtblflow_step_current = $targetStep->idtblflow_step;
            $req->completed_at          = null;
            $req->save();

            // 6. Buat task baru yang OPEN di target node
            // Resolve assignee dari assignee rules
            $assigneeRules = \App\Models\TblStepAssigneeRule::where('idtblflow_step', $targetStep->idtblflow_step)
                ->where('is_active', 1)->get();

            $assigneeUser = null;
            $context = $req->context_json ?? [];

            foreach ($assigneeRules as $rule) {
                if ($rule->assignee_type === 'USER') {
                    $assigneeUser = \App\Models\TblUser::where('user_ref', $rule->assignee_value)->first();
                    break;
                } elseif ($rule->assignee_type === 'FIELD_USER') {
                    $fieldPath = $rule->assignee_value;
                    $val = data_get($context, $fieldPath);
                    if ($val) {
                        $assigneeUser = \App\Models\TblUser::where('user_ref', $val)->first();
                        break;
                    }
                } elseif ($rule->assignee_type === 'JOBTITLE') {
                    $rows = DB::select(
                        "SELECT employeeno FROM db_master.tbemployeeit WHERE jobtitleid = ? AND activestatus = 1 LIMIT 1",
                        [$rule->assignee_value]
                    );
                    if (! empty($rows)) {
                        $assigneeUser = \App\Models\TblUser::where('user_ref', $rows[0]->employeeno)->first();
                    }
                    break;
                }
            }

            $taskNo = 'TSK-' . $targetStep->node_code . '-' . $req->idtblapproval_request . '-RST-' . now()->format('His');
            while (TblTask::where('task_no', $taskNo)->exists()) {
                $taskNo .= '-' . rand(1, 9);
            }

            $newTask = TblTask::create([
                'idtblprocess_instance' => $instance->idtblprocess_instance,
                'idtblapproval_request' => $req->idtblapproval_request,
                'idtblflow_step'        => $targetStep->idtblflow_step,
                'task_no'               => $taskNo,
                'task_status'           => 'OPEN',
                'idtbluser_assigned'    => $assigneeUser?->idtbluser,
                'due_at'                => $targetStep->sla_hours
                    ? now()->addHours($targetStep->sla_hours)
                    : now()->addDays(3),
            ]);

            // Buat candidates untuk BMH (bisa multiple)
            if ($targetStep->node_code === 'BMH') {
                $bmhRefs = data_get($context, '_computed.bmh_user_refs', []);
                if (empty($bmhRefs)) {
                    $bmhRefs = [data_get($context, '_computed.bmh_user_ref')];
                }
                foreach (array_filter($bmhRefs) as $idx => $ref) {
                    $u = \App\Models\TblUser::where('user_ref', $ref)->first();
                    if ($u) {
                        \App\Models\TblTaskCandidate::firstOrCreate(
                            ['task_id' => $newTask->idtbltask, 'idtbluser' => $u->idtbluser],
                            ['candidate_source' => 'DIRECT', 'priority_no' => $idx + 1, 'is_active' => 1]
                        );
                    }
                }
            } elseif ($assigneeUser) {
                \App\Models\TblTaskCandidate::firstOrCreate(
                    ['task_id' => $newTask->idtbltask, 'idtbluser' => $assigneeUser->idtbluser],
                    ['candidate_source' => 'DIRECT', 'priority_no' => 1, 'is_active' => 1]
                );
            }

            // 7. Catat ke route log
            TblProcessRouteLog::create([
                'idtblapproval_request'  => $req->idtblapproval_request,
                'idtblprocess_instance'  => $instance->idtblprocess_instance,
                'idtblflow_step'         => $targetStep->idtblflow_step,
                'route_event'            => 'TASK_CREATED',
                'node_type'              => 'APPROVAL',
                'action_code'            => null,
                'message'                => "[DEV RESET] Task direset ke {$targetStep->node_code} untuk keperluan trial.",
                'created_by'             => 'DEV_RESET',
                'created_at'             => now(),
            ]);
        });

        $this->info('');
        $this->info('  ✅  Reset berhasil!');
        $this->info("  Request #{$reqId} sekarang menunggu approval di node: [{$targetStep->node_code}] {$targetStep->step_name}");
        $this->info('  Buka Inbox untuk melanjutkan trial.');
        $this->info('');

        return 0;
    }

    private function showAvailableNodes(int $flowVersionId): void
    {
        $this->info('  Node yang tersedia:');
        TblFlowStep::where('idtblflow_version', $flowVersionId)
            ->orderBy('step_order')
            ->get()
            ->each(function ($s) {
                $this->line("    {$s->node_code} [{$s->step_type}] — {$s->step_name}");
            });
    }
}
