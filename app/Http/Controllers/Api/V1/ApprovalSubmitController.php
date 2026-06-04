<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TblApprovalRequest;
use App\Models\TblIntegrationMessageLog;
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
            'doc_no'              => ['nullable', 'string', 'max:100'],
            'idtbldocument_type'  => ['required', 'integer'],
            'callback_url'        => ['nullable', 'url', 'max:255'],
            'context_json'        => ['required', 'array', 'min:1'],
            'context_json.*'      => ['nullable'],
            'payload_json'        => ['nullable', 'array'],
            'payload_json.header' => ['sometimes', 'array'],
            'payload_json.detail' => ['sometimes', 'array'],
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

        // #104: pastikan document type milik source_app klien terautentikasi
        $docTypeOwned = \App\Models\TblDocumentType::where('idtbldocument_type', (int) $data['idtbldocument_type'])
            ->where('idtblsource_app', $client->idtblsource_app)
            ->exists();
        if (! $docTypeOwned) {
            return response()->json([
                'success' => false,
                'error'   => 'INVALID_DOCUMENT_TYPE',
                'message' => 'Document type tidak dikenal untuk source app ini.',
            ], 422);
        }

        // #92: idempotency check di-scope ke source_app (unique komposit di DB)
        if ($idempKey = $data['idempotency_key'] ?? null) {
            $existing = TblApprovalRequest::where('idtblsource_app', $client->idtblsource_app)
                ->where('idempotency_key', $idempKey)->first();
            if ($existing) {
                return $this->idempotentResponse($existing);
            }
        }

        // #93: retry doc_ref yang sama (tanpa idempotency_key) → balasan idempoten, bukan 422
        $dup = TblApprovalRequest::where('idtblsource_app', $client->idtblsource_app)
            ->where('idtbldocument_type', (int) $data['idtbldocument_type'])
            ->where('source_request_id', $data['doc_ref'])
            ->first();
        if ($dup) {
            return $this->idempotentResponse($dup);
        }

        $msgLog = TblIntegrationMessageLog::create([
            'idtblsource_app'    => $client->idtblsource_app,
            'direction'          => 'INBOUND',
            'endpoint'           => $request->path(),
            'http_method'        => $request->method(),
            'request_body_json'  => $data,
            'status'             => 'PENDING',
            'idempotency_key'    => $data['idempotency_key'] ?? null,
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
                    'source_request_no'          => $data['doc_no'] ?? $data['doc_ref'],
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
                'status'                => 'SUCCESS',
            ]);

            return response()->json([
                'success'             => true,
                'error_code'          => null,
                'approval_request_id' => $result->idtblapproval_request,
                'status'              => $result->request_status,
                'message'             => 'Approval request berhasil disubmit.',
                'data'                => ['_computed' => $result->context_json['_computed'] ?? null],
            ], 201);

        } catch (\Throwable $e) {
            Log::error("ApprovalSubmit: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
            $msgLog->update(['status' => 'FAILED', 'error_message' => mb_substr($e->getMessage(), 0, 1000)]);

            // #100: bedakan kesalahan konfigurasi routing (klien) vs fault server
            $isRouting = $e instanceof \RuntimeException
                && str_contains($e->getMessage(), 'routing rule');
            return response()->json([
                'success'    => false,
                'error_code' => $isRouting ? 'NO_ROUTING_RULE' : 'INTERNAL_ERROR',
                'message'    => $isRouting
                    ? 'Tidak ada flow approval yang cocok untuk request ini.'
                    : 'Terjadi kesalahan saat memproses request. Hubungi administrator.',
            ], $isRouting ? 422 : 500);
        }
    }

    /**
     * Balasan idempoten standar untuk request yang sudah ada (#92, #93).
     */
    private function idempotentResponse(TblApprovalRequest $existing): JsonResponse
    {
        return response()->json([
            'success'             => true,
            'idempotent'          => true,
            'error_code'          => null,
            'approval_request_id' => $existing->idtblapproval_request,
            'status'              => $existing->request_status,
            'message'             => 'Request sudah diproses (idempotent).',
        ]);
    }
}

