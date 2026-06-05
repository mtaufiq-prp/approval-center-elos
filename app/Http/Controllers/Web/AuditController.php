<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SendCallbackJob;
use App\Models\TblActionLog;
use App\Models\TblAuditEvent;
use App\Models\TblCallbackOutbox;
use App\Models\TblIntegrationMessageLog;
use App\Models\TblSourceApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AuditController
 *
 * Semua halaman read-only untuk audit & observability:
 *  - actionLog()       : tblaction_log  — keputusan approver
 *  - auditEvent()      : tblaudit_event — perubahan master data
 *  - integrationLog()  : tblintegration_message_log — request/response API
 *  - callbackOutbox()  : tblcallback_outbox — outbound callback ke source app
 *  - retryCallback()   : re-dispatch SendCallbackJob untuk item FAILED
 */
class AuditController extends Controller
{
    /* ------------------------------------------------------------------ */
    public function actionLog(Request $request): View
    {
        $q = TblActionLog::with(['approvalRequest', 'actor']);

        if ($s = trim((string) $request->input('search'))) {
            $q->where(fn($w) => $w->where('actor_ref', 'like', "%$s%")
                                  ->orWhere('action_code', 'like', "%$s%")
                                  ->orWhereHas('approvalRequest', fn($aq) =>
                                      $aq->where('source_request_no', 'like', "%$s%")
                                  ));
        }
        if ($request->filled('action_code')) {
            $q->where('action_code', $request->input('action_code'));
        }
        if ($request->filled('date_from')) {
            $q->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $q->where('created_at', '<', \Carbon\Carbon::parse($request->input('date_to'))->addDay());
        }

        $items = $q->orderByDesc('created_at')->paginate(30)->withQueryString();
        $actionCodes = \Illuminate\Support\Facades\Cache::remember(
            'audit:distinct:action_code', 600,
            fn () => TblActionLog::select('action_code')->distinct()->pluck('action_code')
        );

        return view('audit.action_log', compact('items', 'actionCodes'));
    }

    /* ------------------------------------------------------------------ */
    public function auditEvent(Request $request): View
    {
        $q = TblAuditEvent::query();

        if ($s = trim((string) $request->input('search'))) {
            $q->where(fn($w) => $w->where('entity_type', 'like', "%$s%")
                                  ->orWhere('event_code', 'like', "%$s%")
                                  ->orWhere('actor_ref', 'like', "%$s%")
                                  ->orWhere('event_message', 'like', "%$s%"));
        }
        if ($request->filled('event_code')) {
            $q->where('event_code', $request->input('event_code'));
        }
        if ($request->filled('entity_type')) {
            $q->where('entity_type', $request->input('entity_type'));
        }
        if ($request->filled('date_from')) {
            $q->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $q->where('created_at', '<', \Carbon\Carbon::parse($request->input('date_to'))->addDay());
        }

        $items       = $q->orderByDesc('created_at')->paginate(30)->withQueryString();
        $eventCodes  = \Illuminate\Support\Facades\Cache::remember('audit:distinct:event_code', 600,
            fn () => TblAuditEvent::select('event_code')->distinct()->orderBy('event_code')->pluck('event_code'));
        $entityTypes = \Illuminate\Support\Facades\Cache::remember('audit:distinct:entity_type', 600,
            fn () => TblAuditEvent::select('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type'));

        return view('audit.audit_event', compact('items', 'eventCodes', 'entityTypes'));
    }

    /* ------------------------------------------------------------------ */
    public function integrationLog(Request $request): View
    {
        $q = TblIntegrationMessageLog::with('sourceApp');

        if ($s = trim((string) $request->input('search'))) {
            $q->where(fn($w) => $w->where('endpoint', 'like', "%$s%")
                                  ->orWhereHas('sourceApp', fn($sq) =>
                                      $sq->where('app_code', 'like', "%$s%")
                                  ));
        }
        if ($request->filled('direction')) {
            $q->where('direction', $request->input('direction'));
        }
        if ($request->filled('idtblsource_app')) {
            $q->where('idtblsource_app', (int) $request->input('idtblsource_app'));
        }
        if ($request->filled('date_from')) {
            $q->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $q->where('created_at', '<', \Carbon\Carbon::parse($request->input('date_to'))->addDay());
        }

        $items      = $q->orderByDesc('created_at')->paginate(30)->withQueryString();
        $sourceApps = TblSourceApp::orderBy('app_code')->get();

        return view('audit.integration_log', compact('items', 'sourceApps'));
    }

    /* ------------------------------------------------------------------ */
    public function callbackOutbox(Request $request): View
    {
        $q = TblCallbackOutbox::with(['approvalRequest', 'sourceApp']);

        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('idtblsource_app')) {
            $q->where('idtblsource_app', (int) $request->input('idtblsource_app'));
        }
        if ($request->filled('date_from')) {
            $q->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $q->where('created_at', '<', \Carbon\Carbon::parse($request->input('date_to'))->addDay());
        }

        $items      = $q->orderByDesc('created_at')->paginate(30)->withQueryString();
        $sourceApps = TblSourceApp::orderBy('app_code')->get();
        $statuses   = ['PENDING', 'SENT', 'FAILED', 'DEAD'];

        return view('audit.callback_outbox', compact('items', 'sourceApps', 'statuses'));
    }

    /* ------------------------------------------------------------------ */
    public function retryCallback(int $idtblcallback_outbox): RedirectResponse
    {
        $outbox = TblCallbackOutbox::findOrFail($idtblcallback_outbox);

        if (! in_array($outbox->status, ['FAILED', 'DEAD'], true)) {
            return back()->with('error', 'Hanya callback FAILED atau DEAD yang bisa di-retry.');
        }

        // #H8: catat override manual admin SEBELUM reset (sebelumnya tidak ada jejak
        // bahwa admin me-reset & menembak ulang callback — termasuk yang DEAD/SSRF-blocked).
        app(\App\Services\AuditTrailService::class)->recordEvent(
            entityType: 'tblcallback_outbox',
            entityId:   $outbox->idtblcallback_outbox,
            eventCode:  'CALLBACK_RETRY',
            message:    "Manual retry oleh admin untuk callback #{$outbox->idtblcallback_outbox} ke {$outbox->target_url}",
            newValues:  [
                'prev_status'      => $outbox->status,
                'prev_retry_count' => $outbox->retry_count,
                'event_type'       => $outbox->event_type,
                'idtblapproval_request' => $outbox->idtblapproval_request,
            ],
        );

        // Reset agar bisa diproses ulang (termasuk retry_count, agar tak langsung skip)
        $outbox->status        = 'PENDING';
        $outbox->retry_count   = 0;
        $outbox->next_retry_at = now();
        $outbox->save();

        // Dispatch dengan int ID sesuai konstruktor job (#99)
        SendCallbackJob::dispatch($outbox->idtblcallback_outbox);

        return back()->with('status', "Callback #{$outbox->idtblcallback_outbox} akan diproses ulang.");
    }
}
