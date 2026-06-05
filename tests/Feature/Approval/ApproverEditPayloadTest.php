<?php

namespace Tests\Feature\Approval;

use App\Models\TblApprovalRequest;
use App\Models\TblCallbackOutbox;
use App\Models\TblRole;
use App\Models\TblTask;
use App\Models\TblUserRole;
use App\Services\ApproverPayloadEditService;
use App\Services\FlowEngineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsApprovalFlow;
use Tests\TestCase;

/**
 * Fitur: approver boleh mengedit SEBAGIAN field (whitelist per-node), hanya field
 * non-routing, dan hasil edit ikut di callback final.
 */
class ApproverEditPayloadTest extends TestCase
{
    use DatabaseTransactions, BuildsApprovalFlow;

    /** Build flow + request dengan node BMH yang mengizinkan edit field tertentu. */
    private function setupEditableFlow(array $editable = ['header.keterangan'], ?int $submitterId = null, ?array $payload = null): array
    {
        $fx = $this->buildMinimalFlow();
        // Node BMH: whitelist field editable.
        $fx['bmh']->node_config_json = ['editable_fields' => $editable];
        $fx['bmh']->allow_edit_payload = true;
        $fx['bmh']->save();

        $req = TblApprovalRequest::create([
            'idtblsource_app'            => $fx['app']->idtblsource_app,
            'idtbldocument_type'         => $fx['docType']->idtbldocument_type,
            'source_request_id'          => 'DOC-EDIT-' . uniqid(),
            'source_request_no'          => 'DOC-NO',
            'idtblflow_version_selected' => $fx['version']->idtblflow_version,
            'callback_url'               => 'http://10.20.30.40/cb',
            'context_json'               => ['amount' => 1000],
            'payload_json'               => $payload ?? ['header' => [['keterangan' => 'lama', 'nilai' => 1000]]],
            'request_status'             => 'SUBMITTED',
            'title'                      => 'Edit test',
            'idtbluser_submitter'        => $submitterId,
            'submitted_at'               => now(),
        ]);
        app(FlowEngineService::class)->startProcess($req, $fx['version']);

        $task = TblTask::where('idtblapproval_request', $req->idtblapproval_request)
            ->where('task_status', 'OPEN')->firstOrFail();

        return [$fx, $req, $task];
    }

    public function test_service_applies_whitelisted_edit_and_audits(): void
    {
        [$fx, $req, $task] = $this->setupEditableFlow(['header.keterangan']);

        $changes = app(ApproverPayloadEditService::class)
            ->apply($task, ['header.keterangan' => 'sudah diperbaiki'], $fx['approver']);

        $this->assertCount(1, $changes);
        $req->refresh();
        $this->assertSame('sudah diperbaiki', $req->payload_json['header'][0]['keterangan']);
        // field lain tidak berubah
        $this->assertSame(1000, $req->payload_json['header'][0]['nilai']);
        // teraudit
        $this->assertDatabaseHas('tblaction_log', [
            'idtblapproval_request' => $req->idtblapproval_request,
            'action_code'           => 'EDIT_PAYLOAD',
        ]);
    }

    public function test_service_ignores_non_whitelisted_path(): void
    {
        [$fx, $req, $task] = $this->setupEditableFlow(['header.keterangan']);

        // 'header.nilai' TIDAK di-whitelist → harus diabaikan (anti edit field terlarang).
        $changes = app(ApproverPayloadEditService::class)
            ->apply($task, ['header.nilai' => 99999, 'header.keterangan' => 'ok'], $fx['approver']);

        $this->assertCount(1, $changes);
        $this->assertSame('header.keterangan', $changes[0]['path']);
        $req->refresh();
        $this->assertSame(1000, $req->payload_json['header'][0]['nilai'], 'field non-whitelist tak boleh berubah');
        $this->assertSame('ok', $req->payload_json['header'][0]['keterangan']);
    }

    public function test_node_without_editable_fields_applies_nothing(): void
    {
        [$fx, $req, $task] = $this->setupEditableFlow([]); // whitelist kosong

        $changes = app(ApproverPayloadEditService::class)
            ->apply($task, ['header.keterangan' => 'x'], $fx['approver']);

        $this->assertCount(0, $changes);
        $req->refresh();
        $this->assertSame('lama', $req->payload_json['header'][0]['keterangan']);
    }

    public function test_non_scalar_array_value_is_rejected(): void
    {
        [$fx, $req, $task] = $this->setupEditableFlow(['header.keterangan']);

        // Approver mencoba menyuntik array ke field scalar (edits[path][]=x).
        $changes = app(ApproverPayloadEditService::class)
            ->apply($task, ['header.keterangan' => ['evil', 'nested' => 1]], $fx['approver']);

        $this->assertCount(0, $changes, 'nilai non-scalar harus ditolak');
        $req->refresh();
        $this->assertSame('lama', $req->payload_json['header'][0]['keterangan'], 'field tetap scalar & tak berubah');
    }

    public function test_ambiguous_multirow_detail_path_is_rejected(): void
    {
        $payload = ['header' => [['keterangan' => 'h']], 'detail' => [['qty' => 1], ['qty' => 2]]];
        [$fx, $req, $task] = $this->setupEditableFlow(['detail.qty'], null, $payload);

        // 'detail.qty' tanpa indeks pada detail multi-baris → ambigu → ditolak (cegah mutasi diam-diam baris 0).
        $changes = app(ApproverPayloadEditService::class)
            ->apply($task, ['detail.qty' => 999], $fx['approver']);

        $this->assertCount(0, $changes);
        $req->refresh();
        $this->assertSame(1, $req->payload_json['detail'][0]['qty']);
        $this->assertSame(2, $req->payload_json['detail'][1]['qty']);
    }

    public function test_explicit_index_detail_path_is_allowed(): void
    {
        $payload = ['header' => [['keterangan' => 'h']], 'detail' => [['qty' => 1], ['qty' => 2]]];
        [$fx, $req, $task] = $this->setupEditableFlow(['detail.1.qty'], null, $payload);

        $changes = app(ApproverPayloadEditService::class)
            ->apply($task, ['detail.1.qty' => 9], $fx['approver']);

        $this->assertCount(1, $changes);
        $req->refresh();
        $this->assertSame(1, $req->payload_json['detail'][0]['qty'], 'baris lain tak tersentuh');
        $this->assertSame(9, $req->payload_json['detail'][1]['qty']);
    }

    public function test_http_approve_with_edit_updates_payload_and_callback(): void
    {
        [$fx, $req, $task] = $this->setupEditableFlow(['header.keterangan']);

        // Approver perlu role APPROVER agar lolos role middleware route inbox.
        $role = TblRole::firstOrCreate(['role_code' => 'APPROVER'], ['role_name' => 'Approver', 'is_active' => 1]);
        TblUserRole::create(['idtbluser' => $fx['approver']->idtbluser, 'idtblrole' => $role->idtblrole]);

        $resp = $this->actingAs($fx['approver'])->post(route('inbox.act', $task->idtbltask), [
            'decision_code' => 'APPROVE',
            'decision_note' => 'oke setelah perbaikan',
            'edits'         => ['header.keterangan' => 'diperbaiki via web'],
        ]);
        $resp->assertRedirect(route('inbox.index'));

        $req->refresh();
        $this->assertSame('APPROVED', $req->request_status);
        $this->assertSame('diperbaiki via web', $req->payload_json['header'][0]['keterangan']);

        // Callback final memuat payload yang sudah diedit.
        $cb = TblCallbackOutbox::where('idtblapproval_request', $req->idtblapproval_request)->firstOrFail();
        $this->assertSame('diperbaiki via web', $cb->payload_json['payload']['header'][0]['keterangan']);
        $this->assertSame('APPROVED', $cb->payload_json['request_status']);

        // Edit teraudit + keputusan teraudit.
        $this->assertDatabaseHas('tblaction_log', ['idtblapproval_request' => $req->idtblapproval_request, 'action_code' => 'EDIT_PAYLOAD']);
        $this->assertDatabaseHas('tblaction_log', ['idtblapproval_request' => $req->idtblapproval_request, 'action_code' => 'APPROVE']);
    }
}
