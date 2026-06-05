<?php

namespace Tests\Feature\Approval;

use App\Models\TblApprovalRequest;
use App\Models\TblCallbackOutbox;
use App\Models\TblTask;
use App\Services\FlowEngineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsApprovalFlow;
use Tests\TestCase;

/**
 * Fitur: callback per-node saat flow MASUK node yang dikonfigurasi
 * (node_config_json.callback_on_enter). Tujuan: callback_url source app; event TASK_CREATED;
 * dikirim ulang tiap masuk.
 */
class NodeCallbackTest extends TestCase
{
    use DatabaseTransactions, BuildsApprovalFlow;

    private function engine(): FlowEngineService
    {
        return app(FlowEngineService::class);
    }

    private function makeReq(array $fx, ?string $callbackUrl = 'http://10.20.30.40/cb'): TblApprovalRequest
    {
        return TblApprovalRequest::create([
            'idtblsource_app'            => $fx['app']->idtblsource_app,
            'idtbldocument_type'         => $fx['docType']->idtbldocument_type,
            'source_request_id'          => 'DOC-CB-' . uniqid(),
            'source_request_no'          => 'DOC-NO',
            'idtblflow_version_selected' => $fx['version']->idtblflow_version,
            'callback_url'               => $callbackUrl,
            'context_json'               => ['amount' => 100],
            'payload_json'               => ['header' => [['keterangan' => 'x']]],
            'request_status'             => 'SUBMITTED',
            'title'                      => 'Node cb test',
            'submitted_at'               => now(),
        ]);
    }

    private function nodeCbRows(int $reqId)
    {
        return TblCallbackOutbox::where('idtblapproval_request', $reqId)
            ->where('event_type', 'TASK_CREATED')->get();
    }

    public function test_node_callback_fires_on_enter_when_configured(): void
    {
        $fx = $this->buildMinimalFlow();
        $fx['bmh']->node_config_json = ['callback_on_enter' => true, 'callback_event_code' => 'STEP_BMH'];
        $fx['bmh']->save();

        $req = $this->makeReq($fx);
        $this->engine()->startProcess($req, $fx['version']);

        $rows = $this->nodeCbRows($req->idtblapproval_request);
        $this->assertCount(1, $rows, 'satu callback step-reached saat masuk BMH');
        $p = $rows->first()->payload_json;
        $this->assertSame('STEP_BMH', $p['event_code']);
        $this->assertSame('BMH', $p['node_code']);
        $this->assertSame('IN_PROGRESS', $p['request_status']);
        $this->assertArrayHasKey('reached_at', $p);
        $this->assertSame('x', $p['payload']['header'][0]['keterangan']);
        $this->assertSame('http://10.20.30.40/cb', $rows->first()->target_url);
    }

    public function test_event_code_defaults_to_node_code(): void
    {
        $fx = $this->buildMinimalFlow();
        $fx['bmh']->node_config_json = ['callback_on_enter' => true]; // tanpa event_code
        $fx['bmh']->save();

        $req = $this->makeReq($fx);
        $this->engine()->startProcess($req, $fx['version']);

        $this->assertSame('BMH', $this->nodeCbRows($req->idtblapproval_request)->first()->payload_json['event_code']);
    }

    public function test_no_node_callback_when_not_configured(): void
    {
        $fx = $this->buildMinimalFlow(); // BMH tanpa callback_on_enter
        $req = $this->makeReq($fx);
        $this->engine()->startProcess($req, $fx['version']);

        $this->assertCount(0, $this->nodeCbRows($req->idtblapproval_request),
            'tidak ada callback per-node bila tak dikonfigurasi');
    }

    public function test_no_node_callback_when_no_callback_url(): void
    {
        $fx = $this->buildMinimalFlow();
        $fx['bmh']->node_config_json = ['callback_on_enter' => true];
        $fx['bmh']->save();

        $req = $this->makeReq($fx, null); // tanpa callback_url
        $this->engine()->startProcess($req, $fx['version']);

        $this->assertCount(0, $this->nodeCbRows($req->idtblapproval_request));
    }

    public function test_end_node_does_not_emit_node_callback(): void
    {
        // Konfigurasi END dgn callback_on_enter — TIDAK boleh memicu node-callback (state akhir
        // dicakup callback final). Cegah callback ganda di END.
        $fx = $this->buildMinimalFlow();
        $fx['end']->node_config_json = ['callback_on_enter' => true];
        $fx['end']->save();

        $req = $this->makeReq($fx);
        $this->engine()->startProcess($req, $fx['version']);
        $task = TblTask::where('idtblapproval_request', $req->idtblapproval_request)->where('task_status', 'OPEN')->first();
        $this->engine()->completeTask($task, 'APPROVE', null, $fx['approver']); // → END → final APPROVED

        $this->assertCount(0, $this->nodeCbRows($req->idtblapproval_request), 'END tidak emit TASK_CREATED');
        $this->assertSame(1, TblCallbackOutbox::where('idtblapproval_request', $req->idtblapproval_request)
            ->where('event_type', 'APPROVED')->count(), 'hanya callback final APPROVED');
    }

    public function test_node_callback_refires_on_reentry(): void
    {
        $fx = $this->buildMinimalFlow();
        $fx['bmh']->node_config_json = ['callback_on_enter' => true];
        $fx['bmh']->save();

        $req = $this->makeReq($fx);
        $this->engine()->startProcess($req, $fx['version']);          // masuk BMH (row 1)

        $task = TblTask::where('idtblapproval_request', $req->idtblapproval_request)
            ->where('task_status', 'OPEN')->first();
        $this->engine()->completeTask($task, 'RETURN', 'perbaiki', $fx['approver']); // RETURNED

        $req->refresh();
        $this->assertSame('RETURNED', $req->request_status);

        $req->request_status = 'SUBMITTED';
        $req->save();
        $this->engine()->restartProcess($req, $fx['version']);        // masuk BMH lagi (row 2)

        $this->assertCount(2, $this->nodeCbRows($req->idtblapproval_request),
            'callback per-node dikirim ulang tiap node dimasuki');
    }
}
