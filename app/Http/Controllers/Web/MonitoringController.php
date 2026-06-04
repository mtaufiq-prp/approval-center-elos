<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TblApprovalRequest;
use App\Models\TblDocumentType;
use App\Models\TblSourceApp;
use App\Models\TblTask;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * MonitoringController
 *
 * Tampilan read-only untuk melacak status approval request.
 * Bisa diakses ADMIN_APPROVAL dan AUDITOR.
 */
class MonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $q = TblApprovalRequest::with(['sourceApp', 'documentType', 'processInstance.flowStepCurrent']);

        // Filter
        if ($s = trim((string) $request->input('search'))) {
            $q->where(fn($w) =>
                $w->where('source_request_no', 'like', "%$s%")
                  ->orWhere('title', 'like', "%$s%")
                  ->orWhere('requester_ref', 'like', "%$s%")
                  ->orWhere('requester_name', 'like', "%$s%")
            );
        }
        if ($request->filled('status')) {
            $q->where('request_status', $request->input('status'));
        }
        if ($request->filled('idtblsource_app')) {
            $q->where('idtblsource_app', (int) $request->input('idtblsource_app'));
        }
        if ($request->filled('idtbldocument_type')) {
            $q->where('idtbldocument_type', (int) $request->input('idtbldocument_type'));
        }
        if ($request->filled('date_from')) {
            $q->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $q->where('created_at', '<', \Carbon\Carbon::parse($request->input('date_to'))->addDay());
        }
        if ($request->filled('priority')) {
            $q->where('priority', $request->input('priority'));
        }

        $items       = $q->orderByDesc('created_at')->paginate(20)->withQueryString();
        $sourceApps  = TblSourceApp::orderBy('app_code')->get();
        $docTypes    = TblDocumentType::with('sourceApp')->orderBy('doc_code')->get();
        $statuses    = ['SUBMITTED','IN_PROGRESS','APPROVED','REJECTED','RETURNED','CANCELLED','ERROR'];

        return view('monitoring.index', compact('items', 'sourceApps', 'docTypes', 'statuses'));
    }

    public function show(TblApprovalRequest $approval_request): View
    {
        $approval_request->load([
            'sourceApp',
            'documentType',
            'processInstance.flowVersion.flowDefinition',
            'processInstance.flowStepCurrent',
            'routeLogs.flowStep',
            'routeLogs.flowTransition',
        ]);

        // Tasks untuk request ini
        $tasks = TblTask::with(['flowStep', 'claimedBy', 'completedBy'])
            ->where('idtblapproval_request', $approval_request->idtblapproval_request)
            ->orderByDesc('created_at')
            ->get();

        // Action log
        $actionLogs = \App\Models\TblActionLog::where('idtblapproval_request', $approval_request->idtblapproval_request)
            ->orderBy('created_at')
            ->get();

        return view('monitoring.show', compact('approval_request', 'tasks', 'actionLogs'));
    }
}
