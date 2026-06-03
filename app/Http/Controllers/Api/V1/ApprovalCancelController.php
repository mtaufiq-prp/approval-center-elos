<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TblApprovalRequest;
use App\Models\TblTask;
use App\Models\TblProcessInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * POST /api/v1/approval/{approval_request_id}/cancel
 */
class ApprovalCancelController extends Controller
{
    public function __invoke(Request $request, int $approval_request_id): JsonResponse
    {
        $client = $request->attributes->get('api_client');
        $reason = $request->input('reason', '');

        $req = TblApprovalRequest::where('idtblapproval_request', $approval_request_id)
            ->where('idtblsource_app', $client->idtblsource_app)
            ->first();

        if (! $req) {
            return response()->json(['success' => false, 'message' => 'Approval request tidak ditemukan.'], 404);
        }

        if (in_array($req->status, ['APPROVED', 'REJECTED', 'CANCELLED'])) {
            return response()->json(['success' => false, 'message' => "Tidak bisa cancel request yang sudah {$req->status}."], 422);
        }

        DB::transaction(function () use ($req, $reason) {
            TblTask::where('idtblapproval_request', $req->idtblapproval_request)
                ->where('task_status', 'PENDING')
                ->update(['task_status' => 'CANCELLED', 'completed_at' => now()]);

            TblProcessInstance::where('idtblapproval_request', $req->idtblapproval_request)
                ->whereIn('status', ['RUNNING', 'PENDING'])
                ->update(['status' => 'CANCELLED', 'completed_at' => now()]);

            $req->status = 'CANCELLED';
            $req->save();
        });

        return response()->json([
            'success' => true,
            'approval_request_id' => $req->idtblapproval_request,
            'status'  => 'CANCELLED',
            'message' => 'Approval request berhasil dibatalkan.',
        ]);
    }
}
