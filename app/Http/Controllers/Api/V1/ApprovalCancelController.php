<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TblActionLog;
use App\Models\TblApprovalRequest;
use App\Models\TblProcessInstance;
use App\Models\TblProcessToken;
use App\Models\TblTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/v1/approval/{approval_request_id}/cancel
 */
class ApprovalCancelController extends Controller
{
    /** Status request yang TIDAK bisa di-cancel (sudah final keras). */
    private const HARD_FINAL = ['APPROVED', 'REJECTED', 'CANCELLED'];

    public function __invoke(Request $request, int $approval_request_id): JsonResponse
    {
        $client = $request->attributes->get('api_client');
        $reason = trim((string) $request->input('reason', ''));

        // Cek kepemilikan source_app (cegah cancel lintas-program / IDOR).
        $exists = TblApprovalRequest::where('idtblapproval_request', $approval_request_id)
            ->where('idtblsource_app', $client->idtblsource_app)
            ->exists();
        if (! $exists) {
            return response()->json(['success' => false, 'message' => 'Approval request tidak ditemukan.'], 404);
        }

        try {
            $outcome = DB::transaction(function () use ($approval_request_id, $reason, $client) {
                // #H1/#H7: LOCK ORDER instance → request, SAMA dengan completeCurrentTask
                // (task → instance → request), untuk menghindari deadlock & menyerialkan
                // cancel vs approve. Re-baca status DI DALAM lock (bukan TOCTOU di luar TX).
                $instance = TblProcessInstance::where('idtblapproval_request', $approval_request_id)
                    ->lockForUpdate()->first();

                $req = TblApprovalRequest::where('idtblapproval_request', $approval_request_id)
                    ->lockForUpdate()->firstOrFail();

                // Jika approve/reject memenangkan balapan, status (dibaca di bawah lock)
                // sudah final → konflik, JANGAN timpa menjadi CANCELLED.
                if (in_array($req->request_status, self::HARD_FINAL, true)) {
                    return ['conflict' => $req->request_status];
                }

                $beforeStatus = $req->request_status;

                // Batalkan task yang masih aktif.
                TblTask::where('idtblapproval_request', $req->idtblapproval_request)
                    ->whereIn('task_status', ['OPEN', 'CLAIMED'])
                    ->update(['task_status' => 'CANCELLED', 'completed_at' => now()]);

                // Tutup instance HANYA bila masih RUNNING (request RETURNED punya instance
                // COMPLETED — biarkan apa adanya, cukup tandai request CANCELLED).
                if ($instance && $instance->instance_status === 'RUNNING') {
                    $instance->instance_status = 'CANCELLED';
                    $instance->ended_at        = now();
                    $instance->save();
                }

                // Hentikan token aktif agar tidak ada task basi yang menghidupkan kembali.
                TblProcessToken::where('idtblapproval_request', $req->idtblapproval_request)
                    ->where('token_status', TblProcessToken::STATUS_ACTIVE)
                    ->update(['token_status' => TblProcessToken::STATUS_COMPLETED, 'completed_at' => now()]);

                $req->request_status = 'CANCELLED';
                $req->completed_at   = now();
                $req->save();

                // #H7: audit pembatalan + simpan alasan (sebelumnya tidak tercatat sama sekali).
                TblActionLog::create([
                    'idtblapproval_request' => $req->idtblapproval_request,
                    'idtblprocess_instance' => $instance?->idtblprocess_instance,
                    'idtbluser_actor'       => null,
                    'actor_ref'             => optional($client->sourceApp)->app_code ?? 'SOURCE_APP',
                    'action_code'           => 'CANCEL',
                    'action_note'           => $reason !== '' ? $reason : 'Dibatalkan oleh source app via API.',
                    'before_status'         => $beforeStatus,
                    'after_status'          => 'CANCELLED',
                    'client_ip'             => request()?->ip(),
                    'user_agent'            => substr((string) request()?->userAgent(), 0, 255),
                ]);

                return ['cancelled' => true, 'id' => $req->idtblapproval_request];
            });

            if (isset($outcome['conflict'])) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'NOT_CANCELLABLE',
                    'message'    => "Tidak bisa cancel request yang sudah {$outcome['conflict']}.",
                ], 409);
            }

            return response()->json([
                'success'             => true,
                'approval_request_id' => $outcome['id'],
                'status'              => 'CANCELLED',
                'message'             => 'Approval request berhasil dibatalkan.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // #R4: baris hilang antara pre-check & locked read → 404, bukan 500.
            return response()->json(['success' => false, 'message' => 'Approval request tidak ditemukan.'], 404);
        } catch (\Throwable $e) {
            Log::error("ApprovalCancel #{$approval_request_id}: {$e->getMessage()}");
            return response()->json([
                'success'    => false,
                'error_code' => 'INTERNAL_ERROR',
                'message'    => 'Gagal membatalkan request. Hubungi administrator.',
            ], 500);
        }
    }
}
