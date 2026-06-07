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
            // Document type boleh disebut via doc_code (DISARANKAN, stabil & terlihat di UI)
            // ATAU idtbldocument_type (legacy/internal). Wajib salah satu.
            'idtbldocument_type'  => ['required_without:doc_code', 'nullable', 'integer'],
            'doc_code'            => ['required_without:idtbldocument_type', 'nullable', 'string', 'max:50'],
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

        // Resolve document type → id kanonik. Terima doc_code (disarankan) ATAU
        // idtbldocument_type (legacy). SELALU di-scope ke source_app klien (sekaligus
        // cek ownership #104). doc_code unik per source_app (uq_tbl_doc_type) → tak ambigu.
        $docTypeId = $this->resolveDocTypeId($data, $client);
        if (! $docTypeId) {
            return response()->json([
                'success' => false,
                'error'   => 'INVALID_DOCUMENT_TYPE',
                'message' => 'Document type tidak dikenal untuk source app ini (cek doc_code / idtbldocument_type).',
            ], 422);
        }

        // #92: idempotency check di-scope ke source_app (unique komposit di DB)
        if ($idempKey = $data['idempotency_key'] ?? null) {
            $existing = TblApprovalRequest::where('idtblsource_app', $client->idtblsource_app)
                ->where('idempotency_key', $idempKey)->first();
            if ($existing) {
                // #R1: jika request sebelumnya RETURNED, resubmit (walau reuse idempotency_key)
                // harus me-RE-DRIVE, bukan ditelan idempotent → jika tidak, RETURN tetap dead-end
                // untuk source app yang memakai idempotency_key.
                if ($existing->request_status === 'RETURNED') {
                    return $this->reopenReturnedRequest($existing, $data, $client, $request);
                }
                return $this->idempotentResponse($existing);
            }
        }

        // #93: retry doc_ref yang sama (tanpa idempotency_key) → balasan idempoten, bukan 422.
        // #H2: KECUALI bila request sebelumnya RETURNED (dikembalikan untuk perbaikan) →
        // re-drive dengan data baru, bukan ditelan dedup (yang membuatnya macet permanen).
        $dup = TblApprovalRequest::where('idtblsource_app', $client->idtblsource_app)
            ->where('idtbldocument_type', $docTypeId)
            ->where('source_request_id', $data['doc_ref'])
            ->first();
        if ($dup) {
            if ($dup->request_status === 'RETURNED') {
                return $this->reopenReturnedRequest($dup, $data, $client, $request);
            }
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

        // #12: mode async memindahkan eksekusi engine ke queue (lihat StartProcessJob).
        $asyncStart = (bool) config('approval_center.async_start', false);

        try {
            $result = DB::transaction(function () use ($data, $client, $idempKey, $asyncStart, $docTypeId) {

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
                    $docTypeId,
                    $contextJson
                );

                // ── Buat approval request ───────────────────────────────
                $submitter = isset($data['submitter_user_ref'])
                    ? \App\Models\TblUser::where('user_ref', $data['submitter_user_ref'])->first()
                    : null;

                $req = TblApprovalRequest::create([
                    'idtblsource_app'           => $client->idtblsource_app,
                    'idtbldocument_type'         => $docTypeId,
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

                // #L1: catat SUBMIT ke trail aksi per-request (tblaction_log) agar audit
                // approval lengkap dari pembuatan hingga keputusan. task_id null (belum ada task).
                \App\Models\TblActionLog::create([
                    'idtblapproval_request' => $req->idtblapproval_request,
                    'idtbluser_actor'       => $submitter?->idtbluser,
                    'actor_ref'             => $data['submitter_user_ref']
                                                ?? optional($client->sourceApp)->app_code,
                    'action_code'           => 'SUBMIT',
                    'action_note'           => 'Request disubmit via API oleh source app.',
                    'after_status'          => 'SUBMITTED',
                    'client_ip'             => request()?->ip(),
                    'user_agent'            => substr((string) request()?->userAgent(), 0, 255),
                ]);

                // ── Start flow engine ───────────────────────────────────
                // Sinkron (default): engine jalan dalam transaksi → response langsung
                // membawa status IN_PROGRESS & task siap. Async: lewati di sini, job
                // di-dispatch SETELAH commit (lihat di bawah).
                if (! $asyncStart) {
                    $this->flowEngine->startProcess($req, $version);
                }

                return $req->fresh();
            });

            // #12: dispatch SETELAH transaksi commit agar job menemukan request yang sudah persist.
            if ($asyncStart) {
                \App\Jobs\StartProcessJob::dispatch($result->idtblapproval_request);
            }

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
            // #H4: race submit duplikat. Dua submit identik konkuren lolos pre-check
            // (TOCTOU) lalu INSERT kedua kena unique (uq_tbl_request_idempotency /
            // uq_tbl_request_source_doc, errno 1062). Balas idempotent 200, bukan 500.
            if ($e instanceof \Illuminate\Database\QueryException
                && (int) ($e->errorInfo[1] ?? 0) === 1062) {
                $existing = TblApprovalRequest::where('idtblsource_app', $client->idtblsource_app)
                    ->where(function ($q) use ($data, $idempKey, $docTypeId) {
                        $q->where(function ($q2) use ($data, $docTypeId) {
                            $q2->where('idtbldocument_type', $docTypeId)
                               ->where('source_request_id', $data['doc_ref']);
                        });
                        if ($idempKey) {
                            $q->orWhere('idempotency_key', $idempKey);
                        }
                    })
                    ->first();
                if ($existing) {
                    // #iter3: konsisten dgn pre-check — RETURNED harus di-RE-DRIVE, bukan idempotent
                    // (defense-in-depth bila RETURNED muncul tepat di jendela balapan INSERT).
                    if ($existing->request_status === 'RETURNED') {
                        $msgLog->update(['status' => 'SUCCESS']);
                        return $this->reopenReturnedRequest($existing, $data, $client, $request);
                    }
                    $msgLog->update([
                        'idtblapproval_request' => $existing->idtblapproval_request,
                        'status'                => 'SUCCESS',
                    ]);
                    return $this->idempotentResponse($existing);
                }
            }

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

    /**
     * Resolve document type → idtbldocument_type, SELALU di-scope ke source_app klien
     * terautentikasi. Prioritas: `doc_code` (disarankan, stabil & terlihat di UI) lalu
     * `idtbldocument_type` (legacy). doc_code unik per source_app (uq_tbl_doc_type) → tak
     * ambigu. Mengembalikan null bila tak ditemukan (= bukan milik source app ini).
     */
    private function resolveDocTypeId(array $data, $client): ?int
    {
        $base = fn () => \App\Models\TblDocumentType::where('idtblsource_app', $client->idtblsource_app);

        if (! empty($data['doc_code'])) {
            $row = $base()->where('doc_code', $data['doc_code'])->first();
        } elseif (! empty($data['idtbldocument_type'])) {
            $row = $base()->where('idtbldocument_type', (int) $data['idtbldocument_type'])->first();
        } else {
            return null;
        }

        return $row ? (int) $row->idtbldocument_type : null;
    }

    /**
     * Re-drive request yang sebelumnya RETURNED dengan data perbaikan (#H2).
     *
     * Reuse baris request (uq_tbl_request_source_doc melarang baris baru dengan
     * doc_ref sama) & instance tunggalnya: perbarui context/payload, re-route,
     * reset ke SUBMITTED, lalu jalankan ulang engine. Re-check status di bawah
     * lock agar tidak balapan dengan resubmit konkuren.
     */
    private function reopenReturnedRequest(
        TblApprovalRequest $dup,
        array $data,
        $client,
        Request $request
    ): JsonResponse {
        $msgLog = TblIntegrationMessageLog::create([
            'idtblsource_app'   => $client->idtblsource_app,
            'direction'         => 'INBOUND',
            'endpoint'          => $request->path(),
            'http_method'       => $request->method(),
            'request_body_json' => $data,
            'status'            => 'PENDING',
            'idempotency_key'   => $data['idempotency_key'] ?? null,
        ]);

        try {
            $result = DB::transaction(function () use ($dup, $data, $client) {
                // #R2: KUNCI instance DULU, lalu request — urutan SAMA dengan cancel & approve
                // (instance → request). Sebelumnya reopen mengunci request dulu lalu restartProcess
                // mengunci instance (request → instance) → ABBA deadlock vs cancel.
                \App\Models\TblProcessInstance::where('idtblapproval_request', $dup->idtblapproval_request)
                    ->lockForUpdate()->first();

                $locked = TblApprovalRequest::where('idtblapproval_request', $dup->idtblapproval_request)
                    ->lockForUpdate()->firstOrFail();

                // Balapan: status sudah berubah dari RETURNED → kembalikan idempotent.
                if ($locked->request_status !== 'RETURNED') {
                    return ['noop' => $locked];
                }

                $payloadJson = $data['payload_json'] ?? [];
                $contextJson = $this->enrichment->enrich(
                    $data['context_json'],
                    $payloadJson,
                    optional($client->sourceApp)->app_code ?? ''
                );

                $version = $this->routingRuleService->determineFlowVersion(
                    $client->idtblsource_app,
                    (int) $dup->idtbldocument_type,
                    $contextJson
                );

                $submitter = isset($data['submitter_user_ref'])
                    ? \App\Models\TblUser::where('user_ref', $data['submitter_user_ref'])->first()
                    : null;

                $locked->context_json               = $contextJson;
                $locked->payload_json               = $payloadJson;
                $locked->idtblflow_version_selected = $version->idtblflow_version;
                $locked->request_status             = 'SUBMITTED';
                $locked->completed_at               = null;
                $locked->submitted_at               = now();
                if ($submitter) {
                    $locked->idtbluser_submitter = $submitter->idtbluser;
                }
                $locked->save();

                \App\Models\TblActionLog::create([
                    'idtblapproval_request' => $locked->idtblapproval_request,
                    'idtbluser_actor'       => $submitter?->idtbluser,
                    'actor_ref'             => $data['submitter_user_ref']
                                                ?? optional($client->sourceApp)->app_code,
                    'action_code'           => 'RESUBMIT',
                    'action_note'           => 'Resubmit setelah RETURNED — proses dijalankan ulang.',
                    'before_status'         => 'RETURNED',
                    'after_status'          => 'SUBMITTED',
                    'client_ip'             => request()?->ip(),
                    'user_agent'            => substr((string) request()?->userAgent(), 0, 255),
                ]);

                // Catatan: re-drive RETURNED dijalankan SINKRON walau APPROVAL_ASYNC_START aktif.
                // Jalur ini jarang (resubmit setelah dikembalikan), bukan bagian beban create
                // 1000/menit, sehingga latency sinkron dapat diterima & menjaga kesederhanaan.
                $this->flowEngine->restartProcess($locked, $version);

                return ['request' => $locked->fresh()];
            });

            if (isset($result['noop'])) {
                $msgLog->update(['idtblapproval_request' => $result['noop']->idtblapproval_request, 'status' => 'SUCCESS']);
                return $this->idempotentResponse($result['noop']);
            }

            $req = $result['request'];
            $msgLog->update(['idtblapproval_request' => $req->idtblapproval_request, 'status' => 'SUCCESS']);

            return response()->json([
                'success'             => true,
                'reopened'            => true,
                'error_code'          => null,
                'approval_request_id' => $req->idtblapproval_request,
                'status'              => $req->request_status,
                'message'             => 'Request RETURNED di-submit ulang & diproses kembali.',
            ], 200);

        } catch (\Throwable $e) {
            Log::error("ApprovalSubmit reopen #{$dup->idtblapproval_request}: {$e->getMessage()}");
            $msgLog->update(['status' => 'FAILED', 'error_message' => mb_substr($e->getMessage(), 0, 1000)]);
            $isRouting = $e instanceof \RuntimeException && str_contains($e->getMessage(), 'routing rule');
            return response()->json([
                'success'    => false,
                'error_code' => $isRouting ? 'NO_ROUTING_RULE' : 'INTERNAL_ERROR',
                'message'    => $isRouting
                    ? 'Tidak ada flow approval yang cocok untuk request ini.'
                    : 'Gagal memproses ulang request. Hubungi administrator.',
            ], $isRouting ? 422 : 500);
        }
    }
}

