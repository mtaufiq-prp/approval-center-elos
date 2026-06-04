<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TblActionLog;
use App\Models\TblApprovalRequest;
use App\Models\TblProcessInstance;
use App\Models\TblTask; 
use App\Services\AuditTrailService;
use App\Services\FlowEngineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * InboxController
 *
 * Menampilkan task OPEN yang ditugaskan ke user yang login,
 * dan memproses keputusan (APPROVE / REJECT / RETURN).
 *
 * Alur:
 *  1. index() — daftar task open milik user ini (direct assign + candidate group)
 *  2. show()  — detail request + history + form keputusan
 *  3. act()   — proses keputusan → FlowEngineService::completeTask()
 */
class InboxController extends Controller
{
    public function __construct(
        private FlowEngineService $engine,
        private AuditTrailService $audit,
    ) {}

    /* ------------------------------------------------------------------ */
    /* index — daftar task milik user                                       */
    /* ------------------------------------------------------------------ */
    public function index(Request $request): View
    {
        $user = auth()->user();

        $q = TblTask::with(['approvalRequest.sourceApp', 'approvalRequest.documentType', 'flowStep'])
            ->where('task_status', 'OPEN')
            ->where(function ($qw) use ($user) {
                $qw->where('idtbluser_assigned', $user->idtbluser)
                   ->orWhereHas('candidates', fn($cq) =>
                       $cq->where('idtbluser', $user->idtbluser)->where('is_active', 1)
                   );
            });

        // Filter
        if ($s = trim((string) $request->input('search'))) {
            $q->whereHas('approvalRequest', fn($aq) =>
                $aq->where('source_request_no', 'like', "%$s%")
                   ->orWhere('title', 'like', "%$s%")
            );
        }
        if ($request->input('overdue') === '1') {
            $q->where('due_at', '<', now());
        }

        $items    = $q->orderBy('due_at')->orderByDesc('idtbltask')->paginate(20)->withQueryString();
        $inboxCount = $items->total();

        return view('inbox.index', compact('items', 'inboxCount'));
    }

    /* ------------------------------------------------------------------ */
    /* history — daftar task yang sudah diaksi oleh user ini                */
    /* ------------------------------------------------------------------ */
    public function history(Request $request): View
    {
        $user = auth()->user();
        $closedStatuses = ['APPROVED', 'REJECTED', 'RETURNED', 'CANCELLED', 'SKIPPED', 'EXPIRED'];

        $q = TblTask::with([
                'approvalRequest.sourceApp',
                'approvalRequest.documentType', 
                'approvalRequest.flowStepCurrent',
                'flowStep',
            ])
            ->whereIn('task_status', $closedStatuses)
            ->where('idtbluser_completed_by', $user->idtbluser);

        if ($s = trim((string) $request->input('search'))) {
            $q->whereHas('approvalRequest', fn($aq) =>
                $aq->where('source_request_no', 'like', "%$s%")
                   ->orWhere('title', 'like', "%$s%")
            );
        }
        if ($d = $request->input('decision')) {
            $q->where('task_status', $d);
        }

        $items = $q->orderByDesc('completed_at')->paginate(20)->withQueryString();

        $inboxCount = TblTask::where('task_status', 'OPEN')
            ->where(function ($qw) use ($user) {
                $qw->where('idtbluser_assigned', $user->idtbluser)
                   ->orWhereHas('candidates', fn($cq) =>
                       $cq->where('idtbluser', $user->idtbluser)->where('is_active', 1));
            })->count();

        return view('inbox.history', compact('items', 'inboxCount'));
    }

    /* ------------------------------------------------------------------ */
    /* show — detail task & form aksi                                       */
    /* ------------------------------------------------------------------ */
    public function show(TblTask $task): View|RedirectResponse
    {
        $this->authorizeView($task);

        $task->load([
            'approvalRequest.sourceApp',
            'approvalRequest.documentType',
            'approvalRequest.routeLogs.flowStep',
            'flowStep.activeAssigneeRules',
            'candidates.user',
            'completedBy',
        ]);

        // History action dari request ini
        $history = TblActionLog::where('idtblapproval_request', $task->idtblapproval_request)
            ->orderBy('created_at')
            ->get();

        // Alur persetujuan (sudah / sedang / akan approve)
        $approvalRoute = $this->buildApprovalRoute($task);

        // Payload request (context_json untuk ditampilkan)
        $request_model = $task->approvalRequest;
        $contextJson   = $request_model->context_json ?? [];

        $inboxCount = TblTask::where('task_status', 'OPEN')
            ->where(function ($q) { 
                $user = auth()->user();
                $q->where('idtbluser_assigned', $user->idtbluser)
                  ->orWhereHas('candidates', fn($cq) =>
                      $cq->where('idtbluser', $user->idtbluser)->where('is_active', 1));
            })->count();

        return view('inbox.show', compact('task', 'history', 'contextJson', 'inboxCount', 'approvalRoute'));
    }

    /**
     * Bangun alur persetujuan untuk ditampilkan: tiap node APPROVAL beserta
     * state-nya (done / current / future / rejected / returned) + info task.
     */
    private function buildApprovalRoute(TblTask $task): array
    {
        $instance = TblProcessInstance::find($task->idtblprocess_instance);
        if (! $instance) {
            return [];
        }

        $routeNodes = $this->engine->projectApprovalRoute($instance);
        if (empty($routeNodes)) {
            return [];
        }

        // Semua task instance ini, dikelompokkan per step
        $stepTasks = TblTask::with(['completedBy', 'assignedUser', 'candidates.user'])
            ->where('idtblprocess_instance', $instance->idtblprocess_instance)
            ->get()
            ->groupBy('idtblflow_step');

        $currentStepId  = $instance->idtblflow_step_current;
        $instanceClosed = in_array($instance->instance_status, ['COMPLETED', 'REJECTED', 'CANCELLED', 'ERROR']);
        $closedTaskSt   = ['APPROVED', 'REJECTED', 'RETURNED', 'CANCELLED', 'SKIPPED', 'EXPIRED'];

        $route = [];
        foreach ($routeNodes as $node) { 
            $tasksForStep = $stepTasks->get($node->idtblflow_step, collect());
            $doneTask     = $tasksForStep->first(fn($t) => in_array($t->task_status, $closedTaskSt));
            $openTasks    = $tasksForStep->filter(fn($t) => in_array($t->task_status, ['OPEN', 'CLAIMED']));

            if ($doneTask) {
                $state = match ($doneTask->task_status) {
                    'REJECTED'  => 'rejected',
                    'RETURNED'  => 'returned',
                    'CANCELLED' => 'rejected',
                    default     => 'done',
                };
            } elseif (! $instanceClosed && ($node->idtblflow_step == $currentStepId || $openTasks->isNotEmpty())) {
                $state = 'current';
            } else {
                $state = 'future';
            }

            // Siapa yang sedang/akan menyetujui
            $pending = collect();
            if ($state === 'current') {
                // dari task OPEN: assignee langsung + kandidat aktif
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
                // proyeksi calon approver dari assignee rule node
                $pending = $this->resolveProjectedApprovers($node, $instance);
            }

            $route[] = [
                'node'    => $node,
                'state'   => $state,
                'task'    => $doneTask,
                'pending' => $pending->unique('ref')->values()->all(),
            ];
        }

        return $route;
    }

    /**
     * Proyeksikan calon approver untuk node yang belum dijalankan (future),
     * tanpa membuat task. Best-effort: kalau resolver gagal, kembalikan kosong.
     */
    private function resolveProjectedApprovers(\App\Models\TblFlowStep $node, TblProcessInstance $instance)
    {
        try {
            $request    = $instance->approvalRequest ?? TblApprovalRequest::find($instance->idtblapproval_request);
            $context    = $request?->context_json ?? [];
            $submitter  = $request?->idtbluser_submitter ?? null;
            $candidates = app(\App\Services\AssigneeResolverService::class)->resolve($node, $context, $submitter);

            return collect($candidates)->map(fn($u) => [
                'ref'  => $u->user_ref ?? null,
                'name' => $u->full_name ?? null,
            ])->filter(fn($x) => $x['ref'] !== null);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /* ------------------------------------------------------------------ */
    /* act — proses keputusan (APPROVE / REJECT / RETURN / CANCEL)         */
    /* ------------------------------------------------------------------ */
    public function act(Request $request, TblTask $task): RedirectResponse
    {
        $this->authorizeAction($task);

        $validated = $request->validate([
            'decision_code' => ['required', 'in:APPROVE,REJECT,RETURN,CANCEL'],
            'decision_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($task, $validated) {
                $this->engine->completeTask(
                    task:         $task,
                    decisionCode: $validated['decision_code'],
                    decisionNote: $validated['decision_note'] ?? null,
                    actor:        auth()->user(),
                );
            });

            $msg = match($validated['decision_code']) {
                'APPROVE' => 'Request disetujui.',
                'REJECT'  => 'Request ditolak.',
                'RETURN'  => 'Request dikembalikan ke pemohon.',
                'CANCEL'  => 'Request dibatalkan.',
                default   => 'Keputusan disimpan.',
            };

            return redirect()->route('inbox.index')->with('status', $msg);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Inbox act task #{$task->idtbltask}: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Gagal memproses keputusan. Silakan coba lagi atau hubungi administrator.');
        }
    }

    /* ------------------------------------------------------------------ */
    /* Helper                                                               */
    /* ------------------------------------------------------------------ */

    /** Auth untuk melihat detail (boleh task OPEN maupun yang sudah selesai). */
    private function authorizeView(TblTask $task): void
    {
        $user = auth()->user();

        $isAssigned    = $task->idtbluser_assigned == $user->idtbluser;
        $isCompletedBy = $task->idtbluser_completed_by == $user->idtbluser;
        $isCandidate   = $task->candidates()
            ->where('idtbluser', $user->idtbluser)
            ->where('is_active', 1)   // #107: paritas dengan authorizeAction
            ->exists();

        if (! $isAssigned && ! $isCompletedBy && ! $isCandidate && ! $user->hasAnyRole('ADMIN_APPROVAL')) {
            abort(403, 'Anda tidak berwenang mengakses task ini.');
        }
    }

    /** Auth untuk melakukan keputusan: status WAJIB OPEN & user berwenang. */
    private function authorizeAction(TblTask $task): void
    {
        $user = auth()->user();

        if ($task->task_status !== 'OPEN') {
            abort(403, 'Task ini sudah tidak aktif.');
        }

        $isAssigned  = $task->idtbluser_assigned == $user->idtbluser;
        $isCandidate = $task->candidates()
            ->where('idtbluser', $user->idtbluser)
            ->where('is_active', 1)
            ->exists();

        if (! $isAssigned && ! $isCandidate && ! $user->hasAnyRole('ADMIN_APPROVAL')) {
            abort(403, 'Anda tidak berwenang mengakses task ini.');
        }
    }
}
