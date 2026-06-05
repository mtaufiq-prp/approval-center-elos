<?php

namespace Tests\Feature\Approval;

use App\Models\TblApiClient;
use App\Models\TblApprovalRequest;
use App\Models\TblSourceApp;
use App\Models\TblTask;
use App\Services\FlowEngineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsApprovalFlow;
use Tests\TestCase;

/**
 * Uji jalur API hub (HMAC) + perbaikan iterasi lanjutan:
 *  - HMAC valid → submit sukses; header hilang → 401
 *  - Replay nonce ditolak (Cache::add atomic)           (#H3)
 *  - Kredensial kadaluarsa ditolak                      (#54)
 *  - Submit duplikat (doc_ref sama) → idempotent 200    (#93/#H4)
 *  - Cancel request yang sudah final → 409 NOT_CANCELLABLE + tidak meng-corrupt (#H1/#H7)
 *  - Status request program lain tidak terlihat (scoping/IDOR)
 */
class ApprovalApiTest extends TestCase
{
    use DatabaseTransactions, BuildsApprovalFlow;

    private string $secret = 'super-secret-plain-key-123456';

    private function signed(string $method, string $uri, array $payload, TblApiClient $client, array $o = [])
    {
        $body  = $payload === [] && $method === 'GET' ? '' : json_encode($payload);
        $ts    = (string) ($o['ts'] ?? time());
        $nonce = $o['nonce'] ?? bin2hex(random_bytes(8));
        $sig   = $o['sig'] ?? hash_hmac('sha256', $ts . "\n" . $nonce . "\n" . $body, $o['secret'] ?? $this->secret);

        $headers = $this->transformHeadersToServerVars([
            'X-Client-Key' => $o['client_key'] ?? $client->client_key,
            'X-Timestamp'  => $ts,
            'X-Nonce'      => $nonce,
            'X-Signature'  => $sig,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ]);

        return $this->call($method, $uri, [], [], [], $headers, $body === '' ? null : $body);
    }

    private function submitPayload(array $fx, string $docRef = 'DOC-1'): array
    {
        return [
            'doc_ref'            => $docRef,
            'idtbldocument_type' => $fx['docType']->idtbldocument_type,
            'context_json'       => ['amount' => 100],
        ];
    }

    public function test_valid_hmac_submit_succeeds(): void
    {
        $fx = $this->buildMinimalFlow();
        $client = $this->makeApiClient($fx['app'], 'CK1', $this->secret);

        $resp = $this->signed('POST', '/api/v1/approval/submit', $this->submitPayload($fx), $client);

        $resp->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('tblapproval_request', [
            'idtblsource_app'   => $fx['app']->idtblsource_app,
            'source_request_id' => 'DOC-1',
            'request_status'    => 'IN_PROGRESS',
        ]);
    }

    public function test_missing_headers_rejected(): void
    {
        $fx = $this->buildMinimalFlow();
        $this->makeApiClient($fx['app'], 'CK1', $this->secret);

        $this->postJson('/api/v1/approval/submit', $this->submitPayload($fx))
            ->assertStatus(401);
    }

    public function test_replay_nonce_rejected(): void
    {
        $fx = $this->buildMinimalFlow();
        $client = $this->makeApiClient($fx['app'], 'CK1', $this->secret);

        $nonce = bin2hex(random_bytes(8));
        $ts = (string) time();

        $first = $this->signed('POST', '/api/v1/approval/submit', $this->submitPayload($fx, 'DOC-A'), $client, ['nonce' => $nonce, 'ts' => $ts]);
        $first->assertStatus(201);

        // Nonce yang sama → harus ditolak sebagai replay (sebelum controller).
        $replay = $this->signed('POST', '/api/v1/approval/submit', $this->submitPayload($fx, 'DOC-A'), $client, ['nonce' => $nonce, 'ts' => $ts]);
        $replay->assertStatus(401)->assertJson(['error' => 'REPLAY_DETECTED']);
    }

    public function test_expired_client_rejected(): void
    {
        $fx = $this->buildMinimalFlow();
        $client = $this->makeApiClient($fx['app'], 'CK1', $this->secret, [
            'token_expired_at' => now()->subDay(),
        ]);

        $this->signed('POST', '/api/v1/approval/submit', $this->submitPayload($fx), $client)
            ->assertStatus(401)
            ->assertJson(['error' => 'CLIENT_EXPIRED']);
    }

    public function test_duplicate_submit_is_idempotent(): void
    {
        $fx = $this->buildMinimalFlow();
        $client = $this->makeApiClient($fx['app'], 'CK1', $this->secret);

        $first = $this->signed('POST', '/api/v1/approval/submit', $this->submitPayload($fx, 'DOC-DUP'), $client);
        $first->assertStatus(201);
        $id = $first->json('approval_request_id');

        $second = $this->signed('POST', '/api/v1/approval/submit', $this->submitPayload($fx, 'DOC-DUP'), $client);
        $second->assertStatus(200)
            ->assertJson(['idempotent' => true, 'approval_request_id' => $id]);

        $this->assertEquals(1, TblApprovalRequest::where('idtblsource_app', $fx['app']->idtblsource_app)
            ->where('source_request_id', 'DOC-DUP')->count(), 'Tidak boleh ada request duplikat.');
    }

    public function test_cancel_finalized_request_returns_conflict_and_keeps_state(): void
    {
        $fx = $this->buildMinimalFlow();
        $client = $this->makeApiClient($fx['app'], 'CK1', $this->secret);

        $submit = $this->signed('POST', '/api/v1/approval/submit', $this->submitPayload($fx, 'DOC-CXL'), $client);
        $submit->assertStatus(201);
        $id = $submit->json('approval_request_id');

        // Approve hingga final (APPROVED).
        $task = TblTask::where('idtblapproval_request', $id)->where('task_status', 'OPEN')->first();
        app(FlowEngineService::class)->completeTask($task, 'APPROVE', null, $fx['approver']);
        $this->assertSame('APPROVED', TblApprovalRequest::find($id)->request_status);

        // Cancel request yang sudah APPROVED → 409, status TIDAK boleh berubah jadi CANCELLED.
        $cancel = $this->signed('POST', "/api/v1/approval/{$id}/cancel", ['reason' => 'batal'], $client);
        $cancel->assertStatus(409)->assertJson(['error_code' => 'NOT_CANCELLABLE']);

        $this->assertSame('APPROVED', TblApprovalRequest::find($id)->request_status,
            'Cancel tidak boleh menimpa status final APPROVED (state corruption).');
    }

    public function test_cancel_running_request_succeeds_and_audits(): void
    {
        $fx = $this->buildMinimalFlow();
        $client = $this->makeApiClient($fx['app'], 'CK1', $this->secret);

        $submit = $this->signed('POST', '/api/v1/approval/submit', $this->submitPayload($fx, 'DOC-RUN'), $client);
        $id = $submit->json('approval_request_id');

        $cancel = $this->signed('POST', "/api/v1/approval/{$id}/cancel", ['reason' => 'tidak jadi'], $client);
        $cancel->assertStatus(200)->assertJson(['status' => 'CANCELLED']);

        $this->assertSame('CANCELLED', TblApprovalRequest::find($id)->request_status);
        // #H7: alasan tercatat di audit trail.
        $this->assertDatabaseHas('tblaction_log', [
            'idtblapproval_request' => $id,
            'action_code'           => 'CANCEL',
            'action_note'           => 'tidak jadi',
        ]);
    }

    public function test_resubmit_returned_with_same_idempotency_key_reopens(): void
    {
        $fx = $this->buildMinimalFlow();
        $client = $this->makeApiClient($fx['app'], 'CK1', $this->secret);

        $payload = $this->submitPayload($fx, 'DOC-RET');
        $payload['idempotency_key'] = 'IDEMP-RET-1';

        $first = $this->signed('POST', '/api/v1/approval/submit', $payload, $client);
        $first->assertStatus(201);
        $id = $first->json('approval_request_id');

        // RETURN via engine (approver mengembalikan ke pemohon).
        $task = TblTask::where('idtblapproval_request', $id)->where('task_status', 'OPEN')->first();
        app(FlowEngineService::class)->completeTask($task, 'RETURN', 'lengkapi', $fx['approver']);
        $this->assertSame('RETURNED', TblApprovalRequest::find($id)->request_status);

        // #R1: resubmit dengan idempotency_key SAMA harus RE-DRIVE (bukan idempotent stuck).
        $second = $this->signed('POST', '/api/v1/approval/submit', $payload, $client);
        $second->assertStatus(200)->assertJson(['reopened' => true, 'approval_request_id' => $id]);

        $this->assertSame('IN_PROGRESS', TblApprovalRequest::find($id)->request_status);
        $this->assertEquals(1, TblTask::where('idtblapproval_request', $id)->where('task_status', 'OPEN')->count(),
            'Resubmit RETURNED harus membuat task OPEN baru.');
        // Tetap satu request & satu instance (tidak ada duplikat).
        $this->assertEquals(1, TblApprovalRequest::where('idtblsource_app', $fx['app']->idtblsource_app)
            ->where('source_request_id', 'DOC-RET')->count());
    }

    public function test_status_of_other_program_not_visible(): void
    {
        $fx = $this->buildMinimalFlow();
        $clientA = $this->makeApiClient($fx['app'], 'CKA', $this->secret);

        $submit = $this->signed('POST', '/api/v1/approval/submit', $this->submitPayload($fx, 'DOC-X'), $clientA);
        $id = $submit->json('approval_request_id');

        // Client B di source_app berbeda.
        $appB = TblSourceApp::create(['app_code' => 'OTH', 'app_name' => 'Other', 'is_active' => 1]);
        $clientB = $this->makeApiClient($appB, 'CKB', $this->secret);

        $this->signed('GET', "/api/v1/approval/{$id}/status", [], $clientB)
            ->assertStatus(404);

        // Pemilik tetap bisa lihat.
        $this->signed('GET', "/api/v1/approval/{$id}/status", [], $clientA)
            ->assertStatus(200)
            ->assertJson(['approval_request_id' => $id]);
    }
}
