<?php

namespace Tests\Feature\Approval;

use App\Models\TblApprovalRequest;
use App\Models\TblCallbackOutbox;
use App\Models\TblProcessInstance;
use App\Models\TblTask;
use App\Services\FlowEngineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsApprovalFlow;
use Tests\TestCase;

/**
 * Membuktikan korektnitas engine approval pada perbaikan iterasi lanjutan:
 *  - START tidak fail-open (membuat task, bukan langsung APPROVED)  (#4 asli)
 *  - APPROVE → APPROVED + 1 callback outbox (#1)
 *  - REJECT  → REJECTED (bukan APPROVED)                            (#5 asli)
 *  - RETURN  → RETURNED, lalu restartProcess membuka kembali        (#H2)
 *  - Instance final tidak bisa diaksi lagi                          (#84)
 *  - Self-approval ditolak                                          (#63)
 */
class ApprovalEngineTest extends TestCase
{
    use DatabaseTransactions, BuildsApprovalFlow;

    private function engine(): FlowEngineService
    {
        return app(FlowEngineService::class);
    }

    private function makeRequest(array $fx, ?int $submitterId = null): TblApprovalRequest
    {
        return $this->makeApprovalRequest($fx, $submitterId);
    }

    public function test_start_process_creates_task_and_does_not_auto_approve(): void
    {
        $fx  = $this->buildMinimalFlow();
        $req = $this->makeRequest($fx);

        $this->engine()->startProcess($req, $fx['version']);

        $req->refresh();
        $this->assertSame('IN_PROGRESS', $req->request_status, 'Request tidak boleh langsung APPROVED (fail-open).');

        $instance = TblProcessInstance::where('idtblapproval_request', $req->idtblapproval_request)->first();
        $this->assertNotNull($instance);
        $this->assertSame('RUNNING', $instance->instance_status);

        $openTasks = TblTask::where('idtblapproval_request', $req->idtblapproval_request)
            ->where('task_status', 'OPEN')->get();
        $this->assertCount(1, $openTasks, 'Harus ada tepat 1 task OPEN di node BMH.');
        $this->assertSame($fx['bmh']->idtblflow_step, (int) $openTasks->first()->idtblflow_step);
    }

    public function test_approve_finalizes_and_enqueues_callback(): void
    {
        $fx  = $this->buildMinimalFlow();
        $req = $this->makeRequest($fx);
        $this->engine()->startProcess($req, $fx['version']);

        $task = TblTask::where('idtblapproval_request', $req->idtblapproval_request)->where('task_status', 'OPEN')->first();
        $this->engine()->completeTask($task, 'APPROVE', 'ok', $fx['approver']);

        $req->refresh();
        $this->assertSame('APPROVED', $req->request_status);

        $instance = TblProcessInstance::where('idtblapproval_request', $req->idtblapproval_request)->first();
        $this->assertSame('COMPLETED', $instance->instance_status);

        $outbox = TblCallbackOutbox::where('idtblapproval_request', $req->idtblapproval_request)->get();
        $this->assertCount(1, $outbox, 'Harus tepat 1 baris callback outbox saat final.');
        $this->assertSame('APPROVED', $outbox->first()->event_type);
        $this->assertSame('PENDING', $outbox->first()->status);
    }

    public function test_reject_results_in_rejected_not_approved(): void
    {
        $fx  = $this->buildMinimalFlow();
        $req = $this->makeRequest($fx);
        $this->engine()->startProcess($req, $fx['version']);

        $task = TblTask::where('idtblapproval_request', $req->idtblapproval_request)->where('task_status', 'OPEN')->first();
        $this->engine()->completeTask($task, 'REJECT', 'tidak setuju', $fx['approver']);

        $req->refresh();
        $this->assertSame('REJECTED', $req->request_status, 'REJECT tidak boleh berakhir APPROVED.');
    }

    public function test_return_then_restart_reopens_with_new_task(): void
    {
        $fx  = $this->buildMinimalFlow();
        $req = $this->makeRequest($fx);
        $this->engine()->startProcess($req, $fx['version']);

        $task = TblTask::where('idtblapproval_request', $req->idtblapproval_request)->where('task_status', 'OPEN')->first();
        $this->engine()->completeTask($task, 'RETURN', 'lengkapi lampiran', $fx['approver']);

        $req->refresh();
        $this->assertSame('RETURNED', $req->request_status, 'RETURN harus RETURNED, bukan APPROVED.');

        // #H2: resubmit → restartProcess membuka kembali instance yang sama.
        $req->request_status = 'SUBMITTED';
        $req->save();
        $this->engine()->restartProcess($req, $fx['version']);

        $req->refresh();
        $this->assertSame('IN_PROGRESS', $req->request_status);

        $instance = TblProcessInstance::where('idtblapproval_request', $req->idtblapproval_request)->first();
        $this->assertSame('RUNNING', $instance->instance_status);
        $this->assertEquals(
            1,
            TblTask::where('idtblapproval_request', $req->idtblapproval_request)->where('task_status', 'OPEN')->count(),
            'Resubmit harus membuat 1 task OPEN baru.'
        );
        // Tetap satu instance (uq_tbl_instance_request).
        $this->assertEquals(1, TblProcessInstance::where('idtblapproval_request', $req->idtblapproval_request)->count());
    }

    public function test_cannot_act_on_already_finalized_task(): void
    {
        $fx  = $this->buildMinimalFlow();
        $req = $this->makeRequest($fx);
        $this->engine()->startProcess($req, $fx['version']);

        $task = TblTask::where('idtblapproval_request', $req->idtblapproval_request)->where('task_status', 'OPEN')->first();
        $this->engine()->completeTask($task, 'APPROVE', null, $fx['approver']);

        // Task sudah APPROVED & instance COMPLETED → aksi ulang harus ditolak.
        $this->expectException(\RuntimeException::class);
        $this->engine()->completeTask($task->fresh(), 'APPROVE', null, $fx['approver']);
    }

    public function test_self_approval_is_blocked(): void
    {
        $fx  = $this->buildMinimalFlow();
        // submitter == approver
        $req = $this->makeRequest($fx, $fx['approver']->idtbluser);
        $this->engine()->startProcess($req, $fx['version']);

        $task = TblTask::where('idtblapproval_request', $req->idtblapproval_request)->where('task_status', 'OPEN')->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/segregation of duties|sendiri/i');
        $this->engine()->completeTask($task, 'APPROVE', null, $fx['approver']);
    }
}
