<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TblApprovalRequest;
use App\Models\TblCallbackOutbox;
use App\Models\TblProcessInstance;
use App\Models\TblTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();

        // --- Inbox count untuk navbar ---
        // Bangun predikat inbox sekali, dipakai untuk count DAN list (#43)
        $myTaskQuery = fn() => TblTask::where('task_status', 'OPEN')
            ->where(fn($q) => $q
                ->where('idtbluser_assigned', $user->idtbluser)
                ->orWhereHas('candidates', fn($cq) =>
                    $cq->where('idtbluser', $user->idtbluser)->where('is_active', 1)
                )
            );

        $inboxCount = 0;
        if ($user->hasAnyRole('APPROVER', 'ADMIN_APPROVAL')) {
            $inboxCount = $myTaskQuery()->count();
        }

        // --- KPI global (admin) ---
        $kpi = [];
        if ($user->hasAnyRole('ADMIN_APPROVAL', 'AUDITOR')) {
            $kpi['total_request']     = TblApprovalRequest::count();
            $kpi['in_progress']       = TblApprovalRequest::where('request_status', 'IN_PROGRESS')->count();
            $kpi['approved_today']    = TblApprovalRequest::where('request_status', 'APPROVED')
                                            ->whereDate('updated_at', today())->count();
            $kpi['rejected_today']    = TblApprovalRequest::where('request_status', 'REJECTED')
                                            ->whereDate('updated_at', today())->count();
            $kpi['pending_callback']  = TblCallbackOutbox::where('status', 'PENDING')->count();
            $kpi['failed_callback']   = TblCallbackOutbox::where('status', 'FAILED')->count();
            $kpi['open_tasks']        = TblTask::where('task_status', 'OPEN')->count();
            $kpi['overdue_tasks']     = TblTask::where('task_status', 'OPEN')
                                            ->where('due_at', '<', now())->count();

            $kpi['status_breakdown']  = TblApprovalRequest::select('request_status', DB::raw('count(*) as total'))
                                            ->where('created_at', '>=', now()->subDays(7))
                                            ->groupBy('request_status')
                                            ->pluck('total', 'request_status')
                                            ->toArray();
        }

        // --- My tasks (approver) — reuse query builder (#43) ---
        $myTasks = [];
        if ($user->hasAnyRole('APPROVER', 'ADMIN_APPROVAL')) {
            $myTasks = $myTaskQuery()
                ->with(['approvalRequest', 'flowStep'])
                ->orderBy('due_at')
                ->limit(10)
                ->get();
        }

        return view('dashboard.index', compact('kpi', 'myTasks', 'inboxCount'));
    }
}
