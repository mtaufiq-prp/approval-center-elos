<?php

namespace Tests\Feature\Approval;

use App\Jobs\StartProcessJob;
use App\Models\TblProcessInstance;
use App\Models\TblTask;
use App\Services\FlowEngineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsApprovalFlow;
use Tests\TestCase;

/**
 * #H5/#R3: StartProcessJob (mode async-start) harus:
 *  - menjalankan engine untuk request SUBMITTED,
 *  - idempoten (tidak membuat instance ganda bila dijalankan dua kali),
 *  - TIDAK menjalankan engine untuk request yang sudah CANCELLED (cegah un-cancel /
 *    state corruption akibat balapan cancel vs job).
 */
class StartProcessJobTest extends TestCase
{
    use DatabaseTransactions, BuildsApprovalFlow;

    private function runJob(int $id): void
    {
        (new StartProcessJob($id))->handle(app(FlowEngineService::class));
    }

    public function test_job_starts_engine_for_submitted_request(): void
    {
        $fx  = $this->buildMinimalFlow();
        $req = $this->makeApprovalRequest($fx);

        $this->runJob($req->idtblapproval_request);

        $this->assertSame('IN_PROGRESS', $req->fresh()->request_status);
        $this->assertEquals(1, TblProcessInstance::where('idtblapproval_request', $req->idtblapproval_request)->count());
        $this->assertEquals(1, TblTask::where('idtblapproval_request', $req->idtblapproval_request)->where('task_status', 'OPEN')->count());
    }

    public function test_job_is_idempotent_on_double_run(): void
    {
        $fx  = $this->buildMinimalFlow();
        $req = $this->makeApprovalRequest($fx);

        $this->runJob($req->idtblapproval_request);
        $this->runJob($req->idtblapproval_request); // run kedua harus no-op

        $this->assertEquals(1, TblProcessInstance::where('idtblapproval_request', $req->idtblapproval_request)->count(),
            'Job kedua tidak boleh membuat instance ganda.');
    }

    public function test_job_skips_cancelled_request_no_uncancel(): void
    {
        $fx  = $this->buildMinimalFlow();
        $req = $this->makeApprovalRequest($fx);

        // Simulasi: source app cancel sebelum job sempat jalan.
        $req->request_status = 'CANCELLED';
        $req->completed_at   = now();
        $req->save();

        $this->runJob($req->idtblapproval_request);

        $this->assertSame('CANCELLED', $req->fresh()->request_status, 'Job tidak boleh meng-un-cancel request.');
        $this->assertEquals(0, TblProcessInstance::where('idtblapproval_request', $req->idtblapproval_request)->count(),
            'Tidak boleh ada instance untuk request yang sudah CANCELLED.');
        $this->assertEquals(0, TblTask::where('idtblapproval_request', $req->idtblapproval_request)->count());
    }
}
