<?php

namespace App\Http\Controllers\Web\Workflow;

use App\Http\Controllers\Controller;
use App\Models\TblFlowVersion;
use App\Services\AuditTrailService;
use App\Services\FlowBuilderDataService;
use App\Services\FlowBuilderSaveService;
use App\Services\FlowValidationService;
use App\Services\FlowVersionDeploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * FlowBuilderController
 *
 * Semua endpoint untuk visual workflow builder (React Flow canvas).
 *
 * Routes (semua prefix /workflow, middleware auth+role:ADMIN_APPROVAL):
 *   GET  flow-version/{flow_version}/builder
 *   GET  api/flow-version/{flow_version}/builder-data
 *   POST api/flow-version/{flow_version}/builder-save
 *   POST api/flow-version/{flow_version}/builder-validate
 *   POST api/flow-version/{flow_version}/builder-deploy
 *   POST api/flow-version/{flow_version}/builder-clone
 */
class FlowBuilderController extends Controller
{
    public function __construct(
        private FlowBuilderDataService       $dataService,
        private FlowBuilderSaveService       $saveService,
        private FlowValidationService        $validationService,
        private FlowVersionDeploymentService $deployService,
        private AuditTrailService            $audit,
    ) {}

    /* ------------------------------------------------------------------ */
    /* GET /workflow/flow-version/{flow_version}/builder                    */
    /* ------------------------------------------------------------------ */
    public function builder(TblFlowVersion $flow_version): View
    {
        $flow_version->load('flowDefinition');
        $isLocked = $this->dataService->isLocked($flow_version);

        return view('workflow.builder.index', [
            'version'  => $flow_version,
            'isLocked' => $isLocked,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* GET /workflow/api/flow-version/{flow_version}/builder-data           */
    /* ------------------------------------------------------------------ */
    public function builderData(TblFlowVersion $flow_version): JsonResponse
    {
        try {
            $data = $this->dataService->load($flow_version);
            return response()->json($data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("builderData: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data builder.',
            ], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /* POST /workflow/api/flow-version/{flow_version}/builder-save          */
    /* ------------------------------------------------------------------ */
    public function builderSave(Request $request, TblFlowVersion $flow_version): JsonResponse
    {
        $payload = $request->validate([
            'nodes'            => ['present', 'array'],   // present = wajib ada, boleh kosong []
            'edges'            => ['present', 'array'],
            'deleted_node_ids' => ['nullable', 'array'],
            'deleted_edge_ids' => ['nullable', 'array'],
            'diagram_json'     => ['nullable', 'array'],
        ]);

        $result = $this->saveService->save($flow_version, $payload);

        $statusCode = $result['success'] ? 200 : 422;
        return response()->json($result, $statusCode);
    }

    /* ------------------------------------------------------------------ */
    /* POST /workflow/api/flow-version/{flow_version}/builder-validate      */
    /* ------------------------------------------------------------------ */
    public function builderValidate(TblFlowVersion $flow_version): JsonResponse
    {
        $flow_version->load('nodes.activeAssigneeRules', 'edges');

        $result = $this->validationService->validate($flow_version);

        // Simpan hasil validasi hanya jika versi belum terkunci (DRAFT/UNDER_REVIEW)
        if (! $flow_version->isLocked()) {
            $flow_version->validation_status  = $result->isValid
                ? TblFlowVersion::VALIDATION_VALID
                : TblFlowVersion::VALIDATION_INVALID;
            $flow_version->validation_message = $result->summary();
            $flow_version->validated_at       = now();
            $flow_version->save();
        }

        $this->audit->recordEvent(
            entityType: 'FLOW_BUILDER',
            entityId:   $flow_version->idtblflow_version,
            eventCode:  'FLOW_VALIDATED',
            message:    $result->summary(),
            newValues:  ['errors' => $result->errors, 'warnings' => $result->warnings],
        );

        return response()->json([
            'success'            => $result->isValid,
            'is_valid'           => $result->isValid,
            'validation_status'  => $flow_version->validation_status,
            'validation_message' => $flow_version->validation_message,
            'errors'             => $result->errors,
            'warnings'           => $result->warnings,
            'checks'             => $result->checks,
            'error_node_codes'   => $this->extractNodeCodes($result->errors),
            'error_edge_codes'   => $this->extractEdgeCodes($result->errors),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* POST /workflow/api/flow-version/{flow_version}/builder-deploy        */
    /* ------------------------------------------------------------------ */
    public function builderDeploy(Request $request, TblFlowVersion $flow_version): JsonResponse
    {
        if ($this->dataService->isLocked($flow_version)) {
            return response()->json([
                'success' => false,
                'message' => 'Flow version ini locked. Buat Clone untuk perubahan.',
            ], 422);
        }

        $note = $request->input('deployment_note', '');

        try {
            $deployed = $this->deployService->deploy($flow_version, $note);
            return response()->json([
                'success' => true,
                'message' => "Flow version v{$deployed->version_no} berhasil di-deploy menjadi ACTIVE.",
                'status'  => $deployed->status,
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /* ------------------------------------------------------------------ */
    /* POST /workflow/api/flow-version/{flow_version}/builder-clone         */
    /* ------------------------------------------------------------------ */
    public function builderClone(Request $request, TblFlowVersion $flow_version): JsonResponse
    {
        $newName = $request->input('new_name');

        try {
            $newVersion = $this->deployService->cloneVersion($flow_version, $newName);
            return response()->json([
                'success'               => true,
                'message'               => "Flow berhasil di-clone ke version v{$newVersion->version_no} (DRAFT).",
                'new_version_id'        => $newVersion->idtblflow_version,
                'new_version_no'        => $newVersion->version_no,
                'builder_url'           => route('workflow.flow-version.builder', $newVersion->idtblflow_version),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("builderClone: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Gagal meng-clone flow version.',
            ], 422);
        }
    }

    /* ------------------------------------------------------------------ */
    /* GET /workflow/api/jobtitle-search?q=keyword                        */
    /* Dipakai oleh builder untuk dropdown JOBTITLE assignee type         */
    /* ------------------------------------------------------------------ */
    public function jobtitleSearch(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));
        try {
            if ($q === '') {
                // Tanpa query: ambil jobtitle yang paling sering dipakai approver
                $rows = \Illuminate\Support\Facades\DB::select(
                    "SELECT j.jobtitleid, j.jobtitlename,
                            COUNT(e.employeeno) as employee_count
                     FROM db_master.ms_jobtitle j
                     LEFT JOIN db_master.tbemployeeit e
                           ON e.jobtitleid = j.jobtitleid AND e.activestatus = 1
                     WHERE j.jobtitlestatus = 1
                     GROUP BY j.jobtitleid, j.jobtitlename
                     HAVING employee_count > 0
                     ORDER BY j.jobtitlename
                     LIMIT 30"
                );
            } else {
                $rows = \Illuminate\Support\Facades\DB::select(
                    "SELECT j.jobtitleid, j.jobtitlename,
                            COUNT(e.employeeno) as employee_count
                     FROM db_master.ms_jobtitle j
                     LEFT JOIN db_master.tbemployeeit e
                           ON e.jobtitleid = j.jobtitleid AND e.activestatus = 1
                     WHERE j.jobtitlestatus = 1
                       AND (j.jobtitlename LIKE ? OR j.jobtitleid LIKE ?)
                     GROUP BY j.jobtitleid, j.jobtitlename
                     HAVING employee_count > 0
                     ORDER BY j.jobtitlename
                     LIMIT 20",
                    ["%{$q}%", "%{$q}%"]
                );
            }

            $results = array_map(fn($r) => [
                'id'    => $r->jobtitleid,
                'name'  => $r->jobtitlename,
                'count' => (int) $r->employee_count,
            ], $rows);

            return response()->json(['success' => true, 'data' => $results]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("jobtitleSearch: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jobtitle.',
                'data'    => [],
            ]);
        }
    }



    /** Extract node_code yang disebut dalam pesan error untuk highlight */
    private function extractNodeCodes(array $errors): array
    {
        $codes = [];
        foreach ($errors as $msg) {
            if (preg_match_all("/'([A-Z0-9_]+)'/", $msg, $m)) {
                $codes = array_merge($codes, $m[1]);
            }
        }
        return array_unique($codes);
    }

    /** Extract transition_code yang disebut dalam pesan error */
    private function extractEdgeCodes(array $errors): array
    {
        $codes = [];
        foreach ($errors as $msg) {
            if (str_contains($msg, 'Edge') && preg_match_all("/'([A-Za-z0-9_]+)'/", $msg, $m)) {
                $codes = array_merge($codes, $m[1]);
            }
        }
        return array_unique($codes);
    }
}
