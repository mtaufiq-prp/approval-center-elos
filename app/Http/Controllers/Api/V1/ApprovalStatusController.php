<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TblApprovalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/approval/status/{approval_request_id}
 * GET /api/v1/approval/status?doc_ref=X&idtbldocument_type=Y
 */
class ApprovalStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $client = $request->attributes->get('api_client');

        $q = TblApprovalRequest::where('idtblsource_app', $client->idtblsource_app)
            ->with(['processInstances.routeLogs', 'pendingTasks.assignee']);

        if ($id = $request->route('approval_request_id') ?? $request->query('approval_request_id')) {
            $q->where('idtblapproval_request', (int) $id);
        } elseif ($docRef = $request->query('doc_ref')) {
            $q->where('source_request_id', $docRef);
            if ($dtId = $request->query('idtbldocument_type')) {
                $q->where('idtbldocument_type', $dtId);
            }
        } else {
            return response()->json(['success' => false, 'message' => 'Sertakan approval_request_id atau doc_ref.'], 400);
        }

        $req = $q->first();
        if (! $req) {
            return response()->json(['success' => false, 'message' => 'Approval request tidak ditemukan.'], 404);
        }

        $pendingTasks = $req->pendingTasks->map(fn($t) => [
            'task_id'         => $t->idtbltask,
            'node_code'       => optional($t->flowStep)->node_code,
            'assignee_ref'    => optional($t->assignee)->user_ref,
            'due_at'          => optional($t->due_at)?->toIso8601String(),
        ]);

        return response()->json([
            'success'             => true,
            'approval_request_id' => $req->idtblapproval_request,
            'source_request_id'   => $req->source_request_id,
            'status'              => $req->request_status,
            'submitted_at'        => optional($req->submitted_at)?->toIso8601String(),
            'pending_tasks'       => $pendingTasks,
        ]);
    }
}
