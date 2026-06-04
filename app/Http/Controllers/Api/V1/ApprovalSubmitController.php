<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TblApprovalRequest;
use App\Models\TblIntegrationMessageLog;
use App\Services\AuditTrailService;
use App\Services\FlowEngineService;
use App\Services\PayloadEnrichmentService;
use App\Services\RoutingRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/v1/approval/submit
 *
 * Sebelum flow engine berjalan, context_json diperkaya oleh
 * PayloadEnrichmentService dengan field _computed (total nilai,
 * list alasan, user ref BMH/RRM/PMM/PD/NRM/PKG/CEO).
 * Source app tidak perlu diubah.
 */
class ApprovalSubmitController extends Controller
{
    public function __construct(
        private RoutingRuleService      $routingRuleService,
        private FlowEngineService       $flowEngine,
        private PayloadEnrichmentService $enrichment,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $client = $request->attributes->get('api_client');

        $data = $request->validate([
            'doc_ref'             => ['required', 'string', 'max:100'],
            'idtbldocument_type'  => ['required', 'integer'],
            'callback_url'        => ['nullable', 'url', 'max:255'],
            'context_json'        => ['required', 'array'],
            'payload_json'        => ['nullable', 'array'],
            'idempotency_key'     => ['nullable', 'string', 'max:100'],
            'submitter_user_ref'  => ['nullable', 'string', 'max:80'],
            'title'               => ['nullable', 'string', 'max:255'],
            'requester_ref'       => ['nullable', 'string', 'max:80'],
            'requester_name'      => ['nullable', 'string', 'max:150'],
            'requester_org_code'  => ['nullable', 'string', 'max:50'],
            'requester_org_name'  => ['nullable', 'string', 'max:100'],
            'amount'              => ['nullable', 'numeric'],
            'priority'            => ['nullable', 'string', 'max:20'],
        ]);

        // Idempotency check
        if ($idempKey = $data['idempotency_key'] ?? null) {
            $existing = TblApprovalRequest::where('idempotency_key', $idempKey)->first();
            if ($existing) {
                return response()->json([
                    'success'             => true,
                    'idempotent'          => true,
                    'approval_request_id' => $existing->idtblapproval_request,
                    'status'              => $existing->request_status,
                    'message'             => 'Request sudah diproses (idempotent).',
                ]);
            }
        }

        $msgLog = TblIntegrationMessageLog::create([
            'idtblapproval_request' => null,
            'idtblapi_client'       => $client->idtblapi_client,
            'direction'             => 'INBOUND',
            'message_type'          => 'APPROVAL_SUBMIT',
            'payload_json'          => $data,
            'status'                => 'RECEIVED',
        ]);

        try {
            $result = DB::transaction(function () use ($data, $client, $idempKey) {

                // ── Payload enrichment ──────────────────────────────────
                // Inject _computed fields ke context_json tanpa mengubah source app
                $payloadJson  = $data['payload_json'] ?? [];
                $contextJson  = $this->enrichment->enrich(
                    $data['context_json'],
                    $payloadJson,
                    optional($client->sourceApp)->app_code ?? ''
                );

                // ── Resolve flow version via routing rule ───────────────
                $version = $this->routingRuleService->determineFlowVersion(
                    $client->idtblsource_app,
                    (int) $data['idtbldocument_type'],
                    $contextJson
                );

                // ── Buat approval request ───────────────────────────────
                $submitter = isset($data['submitter_user_ref'])
                    ? \App\Models\TblUser::where('user_ref', $data['submitter_user_ref'])->first()
                    : null;

                $req = TblApprovalRequest::create([
                    'idtblsource_app'           => $client->idtblsource_app,
                    'idtbldocument_type'         => $data['idtbldocument_type'],
                    'source_request_id'          => $data['doc_ref'],
                    'idtblflow_version_selected' => $version->idtblflow_version,
                    'callback_url'               => $data['callback_url']
                                                    ?? optional($client->sourceApp)->default_callback_url,
                    'context_json'               => $contextJson,   // sudah diperkaya
                    'payload_json'               => $payloadJson,
                    'request_status'             => 'SUBMITTED',
                    'title'                      => $data['title'] ?? $data['doc_ref'],
                    'requester_ref'              => $data['requester_ref'] ?? null,
                    'requester_name'             => $data['requester_name'] ?? null,
                    'requester_org_code'         => $data['requester_org_code'] ?? null,
                    'requester_org_name'         => $data['requester_org_name'] ?? null,
                    'amount'                     => $data['amount'] ?? null,
                    'priority'                   => $data['priority'] ?? 'NORMAL',
                    'idtbluser_submitter'        => optional($submitter)->idtbluser,
                    'idempotency_key'            => $idempKey,
                    'submitted_at'               => now(),
                ]);

                // ── Start flow engine ───────────────────────────────────
                $this->flowEngine->startProcess($req, $version);

                return $req->fresh();
            });

            $msgLog->update([
                'idtblapproval_request' => $result->idtblapproval_request,
                'status'                => 'PROCESSED',
            ]);

            return response()->json([
                'success'             => true,
                'approval_request_id' => $result->idtblapproval_request,
                'status'              => $result->request_status,
                'message'             => 'Approval request berhasil disubmit.',
                '_computed'           => $result->context_json['_computed'] ?? null,
            ], 201);

        } catch (\Throwable $e) {
            Log::error("ApprovalSubmit: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
            $msgLog->update(['status' => 'ERROR', 'error_message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error'   => 'SUBMIT_ERROR',
                'message' => 'Terjadi kesalahan saat memproses request. Hubungi administrator.',
            ], 422);
        }
    }
}

