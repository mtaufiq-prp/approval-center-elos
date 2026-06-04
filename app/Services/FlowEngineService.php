<?php

namespace App\Services;

use App\Models\TblActionLog;
use App\Models\TblApprovalRequest;
use App\Models\TblFlowStep;
use App\Models\TblFlowTransition;
use App\Models\TblFlowVersion;
use App\Models\TblProcessInstance;
use App\Models\TblProcessRouteLog;
use App\Models\TblProcessToken;
use App\Models\TblTask;
use App\Models\TblTaskCandidate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** 
 * FlowEngineService
 *
 * Runtime BPMN-lite engine. Menggunakan graph traversal, BUKAN step_order+1.
 *
 * Alur utama:
 *  1. startProcess()           → temukan START node, masuk enterNode()
 *  2. enterNode(APPROVAL)      → createApprovalTask()
 *     enterNode(DECISION)      → executeDecisionNode() → teruskan ke next
 *     enterNode(END)           → completeProcess()
 *     enterNode(START)         → langsung findNextEligibleNode()
 *  3. completeCurrentTask()    → dipanggil approver saat approve/reject/return
 *                                 → findOutgoingTransitions() → evaluateTransitionCondition()
 *                                 → enterNode() berikutnya
 *  4. Gateway EXCLUSIVE        → ambil 1 transition match dengan priority_no terkecil
 *     Gateway INCLUSIVE/PARALLEL → TODO Tahap 7+
 *  5. Jika tidak ada transition match → fallback ke default transition atau completeProcess(ERROR)
 *
 * TIDAK menangani: assignment SLA escalation (Tahap 8),
 * callback outbox (Tahap 7), notification (Tahap 8).
 * Komponen tersebut hook ke event setelah engine selesai di Tahap 7–8.
 */
class FlowEngineService
{
    public function __construct(
        private ConditionEvaluatorService $condEval,
        private AssigneeResolverService   $assigneeResolver,
    ) {}

    // ======================================================================
    // PUBLIC API
    // ======================================================================

    /**
     * Mulai proses baru. Dipanggil oleh ApprovalSubmitService setelah
     * routing rule berhasil menentukan flow_version.
     *
     * @return TblProcessInstance
     */
    public function startProcess(TblApprovalRequest $request, TblFlowVersion $version): TblProcessInstance
    {
        return DB::transaction(function () use ($request, $version) {
            // Buat process instance
            $instance = TblProcessInstance::create([
                'idtblapproval_request' => $request->idtblapproval_request,
                'idtblflow_version'     => $version->idtblflow_version,
                'instance_status'       => 'RUNNING',
                'started_at'            => now(),
            ]);

            // Buat token utama
            $token = TblProcessToken::create([
                'idtblprocess_instance' => $instance->idtblprocess_instance,
                'idtblapproval_request' => $request->idtblapproval_request,
                'token_status'          => TblProcessToken::STATUS_ACTIVE,
                'token_key'             => 'main-' . $instance->idtblprocess_instance,
            ]);

            // Temukan START node
            $startNode = $this->findStartStep($version);
            if (! $startNode) {
                throw new RuntimeException("Flow version #{$version->idtblflow_version} tidak punya START node.");
            }

            $this->logRoute($request->idtblapproval_request, $instance->idtblprocess_instance,
                $startNode->idtblflow_step, null,
                TblProcessRouteLog::EV_ENTER_NODE, 'START', null, null,
                'Proses dimulai');

            // Langsung teruskan dari START (tidak buat task untuk START)
            $this->traverseFromNode($startNode, $instance, $request, $token, null);

            return $instance->fresh();
        });
    }

    /**
     * Selesaikan task saat ini (approve/reject/return/etc).
     * Dipanggil TaskService setelah approver submit action.
     *
     * @param TblTask   $task        Task yang diselesaikan
     * @param string    $actionCode  APPROVE / REJECT / RETURN / ...
     * @param string|null $comment
     * @param int       $actorId     idtbluser yang mengambil aksi
     */
    /**
     * completeTask() — dipanggil dari InboxController.
     * Entry point utama untuk menyelesaikan task approval.
     */
    public function completeTask(
        TblTask $task,
        string  $decisionCode,
        ?string $decisionNote,
        \App\Models\TblUser $actor
    ): void {
        $this->completeCurrentTask(
            task:       $task,
            actionCode: $decisionCode,
            comment:    $decisionNote,
            actorId:    $actor->idtbluser,
        );
    }

    public function completeCurrentTask(TblTask $task, string $actionCode, ?string $comment, int $actorId): void
    {
        DB::transaction(function () use ($task, $actionCode, $comment, $actorId) {
            // Re-fetch dengan row lock agar concurrent request tidak double-advance
            $task = TblTask::where('idtbltask', $task->idtbltask)->lockForUpdate()->firstOrFail();

            // Task yang bisa diproses: OPEN atau CLAIMED
            if (! in_array($task->task_status, ['OPEN', 'CLAIMED'])) {
                throw new \RuntimeException("Task #{$task->idtbltask} sudah dalam status {$task->task_status}.");
            }

            // Map actionCode ke task_status sesuai ENUM schema
            $taskStatus = match(strtoupper($actionCode)) {
                'APPROVE', 'AUTO_APPROVE' => 'APPROVED',
                'REJECT'                  => 'REJECTED',
                'RETURN'                  => 'RETURNED',
                'CANCEL'                  => 'CANCELLED',
                default                   => throw new \RuntimeException("Action code tidak dikenal: {$actionCode}"),
            };

            $task->task_status            = $taskStatus;
            $task->decision_code          = strtoupper($actionCode);
            $task->decision_note          = $comment;
            $task->idtbluser_completed_by = $actorId;
            $task->completed_at           = now();
            $task->save();

            $instance = TblProcessInstance::where('idtblprocess_instance', $task->idtblprocess_instance)
                            ->lockForUpdate()->firstOrFail();
            $request  = TblApprovalRequest::findOrFail($instance->idtblapproval_request);
            $token = TblProcessToken::where('idtblprocess_instance', $instance->idtblprocess_instance)
                            ->where('token_status', TblProcessToken::STATUS_ACTIVE)
                            ->orderBy('idtblprocess_token')
                            ->first();
            if (! $token) {
                Log::warning("FlowEngine: tidak ada ACTIVE token untuk instance #{$instance->idtblprocess_instance}.");
            }
            $currentNode = TblFlowStep::findOrFail($task->idtblflow_step);

            // Catat ke tblaction_log untuk audit trail keputusan per request
            $actor = \App\Models\TblUser::find($actorId);
            TblActionLog::create([
                'idtblapproval_request'  => $request->idtblapproval_request,
                'idtblprocess_instance'  => $instance->idtblprocess_instance,
                'task_id'                => $task->idtbltask,
                'idtbluser_actor'        => $actorId,
                'actor_ref'              => $actor?->user_ref,
                'action_code'            => strtoupper($actionCode),
                'action_note'            => $comment,
                'before_status'          => $task->getOriginal('task_status') ?? $task->task_status,
                'after_status'           => $taskStatus,
                'idtblflow_step_before'  => $currentNode->idtblflow_step,
                'client_ip'              => request()?->ip(),
                'user_agent'             => substr((string) request()?->userAgent(), 0, 255),
            ]);

            $this->logRoute($request->idtblapproval_request, $instance->idtblprocess_instance,
                $currentNode->idtblflow_step, null,
                TblProcessRouteLog::EV_EXIT_NODE, $currentNode->step_type, $actionCode, null,
                "Task #{$task->idtbltask} diselesaikan: {$actionCode}");

            // Cek approval_mode (ANY/ALL)
            if ($currentNode->approval_mode === 'ALL') {
                $stillOpen = TblTask::where('idtblprocess_instance', $instance->idtblprocess_instance)
                    ->where('idtblflow_step', $currentNode->idtblflow_step)
                    ->whereIn('task_status', ['OPEN', 'CLAIMED'])
                    ->where('idtbltask', '!=', $task->idtbltask)
                    ->count();
                if ($stillOpen > 0 && strtoupper($actionCode) === 'APPROVE') {
                    // Masih menunggu approver lain di node ini (mode ALL)
                    return;
                }
            }

            // ANY mode: cancel sibling task yang masih OPEN/CLAIMED di node yang sama
            if ($currentNode->approval_mode !== 'ALL') {
                TblTask::where('idtblprocess_instance', $instance->idtblprocess_instance)
                    ->where('idtblflow_step', $currentNode->idtblflow_step)
                    ->whereIn('task_status', ['OPEN', 'CLAIMED'])
                    ->where('idtbltask', '!=', $task->idtbltask)
                    ->update([
                        'task_status'  => 'CANCELLED',
                        'completed_at' => now(),
                    ]);
            }

            // Teruskan ke next node
            $this->traverseFromNode($currentNode, $instance, $request, $token, $actionCode);
        });
    }

    // ======================================================================
    // INTERNAL TRAVERSAL
    // ======================================================================

    private function traverseFromNode(
        TblFlowStep $node,
        TblProcessInstance $instance,
        TblApprovalRequest $request,
        ?TblProcessToken $token,
        ?string $actionCode
    ): void {
        // START: teruskan langsung (tidak buat task)
        if ($node->isStart()) {
            $nextNode = $this->findNextEligibleNode($node, $instance, $request, $actionCode);
            if ($nextNode) {
                $this->enterNode($nextNode, $instance, $request, $token, $actionCode);
            } else {
                $this->completeProcess($instance, $request, 'COMPLETED', 'Tidak ada next node dari START.');
            }
            return;
        }

        // APPROVAL: sudah ditangani startProcess/completeCurrentTask via createApprovalTask
        if ($node->isApproval()) {
            $nextNode = $this->findNextEligibleNode($node, $instance, $request, $actionCode);
            if ($nextNode) {
                $this->enterNode($nextNode, $instance, $request, $token, $actionCode);
            } else {
                // Tidak ada next edge yang match → gunakan final_status dari edge jika ada
                $this->completeProcess($instance, $request, 'COMPLETED',
                    "Tidak ada next node dari APPROVAL '{$node->node_code}' action {$actionCode}.");
            }
            return;
        }

        // DECISION: evaluasi langsung, tidak buat task
        if ($node->isDecision()) {
            $this->executeDecisionNode($node, $instance, $request, $token, $actionCode);
            return;
        }

        // END
        if ($node->isEnd()) {
            $this->completeProcess($instance, $request, 'COMPLETED',
                "Proses mencapai END node '{$node->node_code}'.");
            return;
        }

        // REVIEW/NOTIFICATION/SYSTEM: TODO Tahap 8 — untuk sementara auto-forward
        $nextNode = $this->findNextEligibleNode($node, $instance, $request, 'AUTO');
        if ($nextNode) {
            $this->enterNode($nextNode, $instance, $request, $token, 'AUTO');
        } else {
            $this->completeProcess($instance, $request, 'COMPLETED', "Auto-completed dari node {$node->step_type}.");
        }
    }

    private function enterNode(
        TblFlowStep $node,
        TblProcessInstance $instance,
        TblApprovalRequest $request,
        ?TblProcessToken $token,
        ?string $actionCode
    ): void {
        $this->logRoute($request->idtblapproval_request, $instance->idtblprocess_instance,
            $node->idtblflow_step, null,
            TblProcessRouteLog::EV_ENTER_NODE, $node->step_type, $actionCode, null,
            "Masuk node '{$node->node_code}'");

        // Update token
        if ($token) {
            $token->idtblflow_step_current = $node->idtblflow_step;
            $token->save();
        }

        // Update instance current step + pastikan RUNNING (in case ada dev reset)
        if ($instance->instance_status !== 'RUNNING') {
            $instance->instance_status = 'RUNNING';
        }
        $instance->idtblflow_step_current = $node->idtblflow_step;
        $instance->save();

        // APPROVAL: buat task & berhenti (tunggu keputusan approver). Tidak auto-forward.
        if ($node->isApproval()) {
            $this->createApprovalTask($node, $instance, $request);
            return;
        }

        $this->traverseFromNode($node, $instance, $request, $token, $actionCode);
    }

    private function executeDecisionNode(
        TblFlowStep $node,
        TblProcessInstance $instance,
        TblApprovalRequest $request,
        ?TblProcessToken $token,
        ?string $actionCode
    ): void {
        $gatewayType = $node->gateway_type ?? TblFlowStep::GATEWAY_EXCLUSIVE;
        $context     = $request->context_json ?? [];

        $outgoing = TblFlowTransition::where('idtblflow_step_from', $node->idtblflow_step)
            ->where('is_active', 1)
            ->where(fn($q) => $q->whereNull('action_code')->orWhere(fn($q2) => $actionCode ? $q2->where('action_code', $actionCode) : $q2->whereRaw('0')))
            ->orderBy('priority_no')
            ->get();

        $matched = collect();
        foreach ($outgoing as $edge) {
            $result = empty($edge->condition_json) || $this->condEval->evaluate($edge->condition_json, $context);
            $this->logRoute($request->idtblapproval_request, $instance->idtblprocess_instance,
                $node->idtblflow_step, $edge->idtblflow_transition,
                $result ? TblProcessRouteLog::EV_TRANSITION_MATCH : TblProcessRouteLog::EV_TRANSITION_NOT_MATCH,
                'DECISION', $actionCode, $result,
                $result ? "Edge match: {$edge->transition_code}" : "Edge skip: {$edge->transition_code}");
            if ($result) $matched->push($edge);
        }

        // Pilih berdasarkan gateway_type
        if ($gatewayType === TblFlowStep::GATEWAY_EXCLUSIVE || $gatewayType === TblFlowStep::GATEWAY_NONE) {
            // Ambil priority_no terkecil yang match
            $chosen = $matched->sortBy('priority_no')->first();
            if ($chosen) {
                $nextNode = TblFlowStep::find($chosen->idtblflow_step_to);
                if ($nextNode) {
                    $this->enterNode($nextNode, $instance, $request, $token, $actionCode);
                    return;
                }
            }

            // Tidak ada match → cari default transition
            $default = $outgoing->where('is_default', true)->sortBy('priority_no')->first();
            if ($default) {
                $nextNode = TblFlowStep::find($default->idtblflow_step_to);
                if ($nextNode) {
                    $this->enterNode($nextNode, $instance, $request, $token, $actionCode);
                    return;
                }
            }

            // Tidak ada sama sekali → ERROR (fail-safe, bukan fail-open)
            Log::error("FlowEngine: DECISION node '{$node->node_code}' tidak punya transition match maupun default. Process dihentikan dengan ERROR.");
            $this->completeProcess($instance, $request, 'ERROR',
                "Tidak ada transition match dari DECISION node '{$node->node_code}'. Periksa konfigurasi flow.");
            return;
        }

        // INCLUSIVE/PARALLEL → TODO Tahap berikutnya
        // Untuk sementara: ambil EXCLUSIVE logic (paling aman)
        Log::warning("FlowEngine: INCLUSIVE/PARALLEL gateway belum diimplementasi. Fallback ke EXCLUSIVE untuk node '{$node->node_code}'.");
        $chosen = $matched->sortBy('priority_no')->first();
        if ($chosen) {
            $nextNode = TblFlowStep::find($chosen->idtblflow_step_to);
            if ($nextNode) $this->enterNode($nextNode, $instance, $request, $token, $actionCode);
        }
    }

    private function findNextEligibleNode(
        TblFlowStep $node,
        TblProcessInstance $instance,
        TblApprovalRequest $request,
        ?string $actionCode
    ): ?TblFlowStep {
        $context = $request->context_json ?? [];

        $outgoing = TblFlowTransition::where('idtblflow_step_from', $node->idtblflow_step)
            ->where('is_active', 1)
            ->where(fn($q) => $q->whereNull('action_code')->orWhere(fn($q2) => $actionCode ? $q2->where('action_code', $actionCode) : $q2->whereRaw('0')))
            ->orderBy('priority_no')
            ->get();

        // Pre-load semua step target sekaligus untuk hilangkan N+1 (#25)
        $stepIds   = $outgoing->pluck('idtblflow_step_to')->filter()->unique()->values();
        $stepCache = TblFlowStep::whereIn('idtblflow_step', $stepIds)->get()->keyBy('idtblflow_step');

        foreach ($outgoing as $edge) {
            $result = empty($edge->condition_json) || $this->condEval->evaluate($edge->condition_json, $context);
            $this->logRoute($request->idtblapproval_request, $instance->idtblprocess_instance,
                $node->idtblflow_step, $edge->idtblflow_transition,
                TblProcessRouteLog::EV_EVALUATE_TRANSITION,
                $node->step_type, $actionCode, $result,
                "Evaluasi edge {$edge->transition_code}: " . ($result ? 'MATCH' : 'SKIP'));
            if ($result) {
                return $edge->idtblflow_step_to ? $stepCache->get($edge->idtblflow_step_to) : null;
            }
        }

        // Default transition
        $default = $outgoing->where('is_default', true)->sortBy('priority_no')->first();
        if ($default) {
            return $default->idtblflow_step_to ? $stepCache->get($default->idtblflow_step_to) : null;
        }

        return null;
    }

    private function createApprovalTask(
        TblFlowStep $node,
        TblProcessInstance $instance,
        TblApprovalRequest $request
    ): void {
        $context    = $request->context_json ?? [];
        $submitter  = $request->idtbluser_submitter ?? null;
        $candidates = $this->assigneeResolver->resolve($node, $context, $submitter);

        if ($candidates->isEmpty()) {
            Log::error("AssigneeResolver: Tidak ada kandidat approver untuk node '{$node->node_code}' request #{$request->idtblapproval_request}");
            $this->completeProcess($instance, $request, 'ERROR', "Tidak ada approver untuk node '{$node->node_code}'.");
            return;
        }

        // Buat satu task per kandidat (task_no WAJIB di-generate; kolom NOT NULL)
        $seq = 0;
        foreach ($candidates as $candidate) {
            $seq++;
            $taskNo = sprintf(
                'TSK-%s-%d-%d-%s',
                $node->node_code,
                $request->idtblapproval_request,
                $seq,
                substr(str_replace('.', '', (string) microtime(true)), -10)
            );
            $task = TblTask::create([
                'task_no'                => $taskNo,
                'idtblprocess_instance'  => $instance->idtblprocess_instance,
                'idtblapproval_request'  => $request->idtblapproval_request,
                'idtblflow_step'         => $node->idtblflow_step,
                'idtbluser_assigned'     => $candidate->idtbluser,
                'task_status'            => 'OPEN',
                'due_at'                 => $node->sla_hours ? now()->addHours($node->sla_hours) : null,
            ]);

            // Catat candidate (untuk "Pending di" / multi-approver ANY mode)
            TblTaskCandidate::create([
                'task_id'          => $task->idtbltask,
                'idtbluser'        => $candidate->idtbluser,
                'candidate_source' => 'DIRECT',
                'priority_no'      => $seq,
                'is_active'        => 1,
            ]);
        }

        $this->logRoute($request->idtblapproval_request, $instance->idtblprocess_instance,
            $node->idtblflow_step, null,
            TblProcessRouteLog::EV_TASK_CREATED, 'APPROVAL', null, null,
            count($candidates) . " task dibuat untuk node '{$node->node_code}'");
    }

    private function completeProcess(
        TblProcessInstance $instance,
        TblApprovalRequest $request,
        string $finalStatus,
        string $reason
    ): void {
        $instance->instance_status = $finalStatus;
        $instance->ended_at = now();
        $instance->save();

        // ENUM tblapproval_request.request_status tidak punya COMPLETED — map ke APPROVED.
        $request->request_status = match ($finalStatus) {
            'COMPLETED' => 'APPROVED',
            default     => $finalStatus, // REJECTED / CANCELLED / ERROR sama persis di kedua ENUM
        };
        $request->completed_at = now();
        $request->save();

        // Complete token
        TblProcessToken::where('idtblprocess_instance', $instance->idtblprocess_instance)
            ->where('token_status', TblProcessToken::STATUS_ACTIVE)
            ->update(['token_status' => TblProcessToken::STATUS_COMPLETED, 'completed_at' => now()]);

        $this->logRoute($request->idtblapproval_request, $instance->idtblprocess_instance,
            null, null,
            TblProcessRouteLog::EV_PROCESS_COMPLETED, null, $finalStatus, null,
            $reason);
    }

    private function findStartStep(TblFlowVersion $version): ?TblFlowStep
    {
        return TblFlowStep::where('idtblflow_version', $version->idtblflow_version)
            ->where('step_type', TblFlowStep::TYPE_START)
            ->first();
    } 

    // ======================================================================
    // PROYEKSI ROUTE (read-only — untuk tampilan alur persetujuan)
    // ======================================================================

    /**
     * Proyeksikan urutan node APPROVAL untuk sebuah instance dengan menelusuri
     * graph + mengevaluasi condition_json memakai context_json request.
     * READ-ONLY: tidak menulis ke route log / tidak mengubah state apa pun.
     *
     * @return TblFlowStep[] urutan node APPROVAL dari awal hingga akhir jalur
     */
    public function projectApprovalRoute(TblProcessInstance $instance): array
    {
        $request = $instance->approvalRequest
            ?? TblApprovalRequest::find($instance->idtblapproval_request);
        $context = $request?->context_json ?? [];

        $start = TblFlowStep::where('idtblflow_version', $instance->idtblflow_version)
            ->where('step_type', TblFlowStep::TYPE_START)
            ->first();
        if (! $start) {
            return [];
        }

        $route   = [];
        $visited = [];
        $node    = $start;
        $guard   = 0;

        while ($node && $guard++ < 100) {
            if (isset($visited[$node->idtblflow_step])) {
                break; // proteksi loop
            }
            $visited[$node->idtblflow_step] = true;

            if ($node->isApproval()) {
                $route[] = $node;
            } 
            if ($node->isEnd()) {
                break;
            }

            $node = $this->peekNextNode($node, $context);
        }

        return $route; 
    } 

    /**
     * Versi read-only untuk proyeksi happy-path: ambil semua edge aktif,
     * abaikan jalur negatif (REJECT/RETURN/CANCEL), lalu pilih edge pertama
     * (urut priority_no) yang kondisinya match; default dievaluasi terakhir.
     * TIDAK memfilter action_code spesifik — flow ini memakai SUBMIT/
     * AUTO_APPROVE/APPROVE pada edge maju, tidak ada yang null.
     */
    private function peekNextNode(TblFlowStep $node, array $context): ?TblFlowStep
    {
        $negative = ['REJECT', 'RETURN', 'CANCEL'];

        $outgoing = TblFlowTransition::where('idtblflow_step_from', $node->idtblflow_step)
            ->where('is_active', 1)
            ->orderBy('priority_no')
            ->get()
            ->reject(fn($e) => in_array(strtoupper((string) $e->action_code), $negative, true));

        // Edge non-default lebih dulu (urut prioritas)
        foreach ($outgoing as $edge) {
            if ($edge->is_default) {
                continue;
            }
            $match = empty($edge->condition_json) || $this->condEval->evaluate($edge->condition_json, $context);
            if ($match) {
                return $edge->idtblflow_step_to ? TblFlowStep::find($edge->idtblflow_step_to) : null;
            }
        }

        // Default transition (maju) sebagai fallback
        $default = $outgoing->firstWhere('is_default', true);
        if ($default) {
            return $default->idtblflow_step_to ? TblFlowStep::find($default->idtblflow_step_to) : null;
        }

        return null;
    }

    private function logRoute(
        int $requestId, int $instanceId,
        ?int $stepId, ?int $transitionId,
        string $event, ?string $nodeType,
        ?string $actionCode, ?bool $condResult,
        string $message
    ): void {
        try {
            $createdBy = request()->user()?->user_ref ?? 'SYSTEM';

            TblProcessRouteLog::create([
                'idtblapproval_request' => $requestId,
                'idtblprocess_instance' => $instanceId,
                'idtblflow_step'        => $stepId,
                'idtblflow_transition'  => $transitionId,
                'route_event'           => $event,
                'node_type'             => $nodeType,
                'action_code'           => $actionCode,
                'condition_result'      => $condResult,
                'message'               => mb_substr($message, 0, 500),
                'created_by'            => $createdBy,
            ]);
        } catch (\Throwable $e) {
            Log::error("FlowEngine route log failed: {$e->getMessage()}");
        }
    }
}
