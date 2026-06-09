<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TblActionLog;
use App\Models\TblApprovalRequest;
use App\Services\ApprovalRouteService;
use Illuminate\Http\Request;

/**
 * Tracking PUBLIK read-only sebuah approval request — untuk dilihat dari source app
 * (mis. PSTB) tanpa login hub. Diamankan dengan token HMAC per-id:
 *   sig = hash_hmac('sha256', 'track:'.{id}, APP_KEY)
 * Source app membentuk link dengan sig yang sama (APP_KEY = secret bersama).
 * Tidak ada aksi apa pun di halaman ini (murni baca).
 */
class TrackController extends Controller
{
    public function show(Request $request, int $id)
    {
        $sig      = (string) $request->query('sig', '');
        $expected = hash_hmac('sha256', 'track:' . $id, (string) config('app.key'));
        if ($sig === '' || ! hash_equals($expected, $sig)) {
            abort(403, 'Link tidak valid.');
        }

        $req = TblApprovalRequest::with(['documentType', 'sourceApp', 'processInstance'])->findOrFail($id);

        $instance      = $req->processInstance;
        $approvalRoute = $instance ? app(ApprovalRouteService::class)->build($instance) : [];

        $history = TblActionLog::where('idtblapproval_request', $id)
            ->with('actor')->orderBy('created_at')->get();

        $payloadJson = is_array($req->payload_json) ? $req->payload_json
            : json_decode($req->payload_json ?? '{}', true);
        $contextJson = is_array($req->context_json) ? $req->context_json
            : json_decode($req->context_json ?? '{}', true);

        return view('track.show', compact('req', 'approvalRoute', 'history', 'payloadJson', 'contextJson'));
    }
}
