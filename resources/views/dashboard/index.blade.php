@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-speedometer2"></i> Dashboard</h5>
    <small class="text-muted">{{ now()->isoFormat('dddd, D MMMM YYYY HH:mm') }}</small>
</div>

@if(!empty($kpi))
{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    @php
    $cards = [
        ['label'=>'Total Request',     'val'=>$kpi['total_request'],    'icon'=>'file-earmark-text',  'color'=>'primary'],
        ['label'=>'In Progress',        'val'=>$kpi['in_progress'],      'icon'=>'hourglass-split',    'color'=>'warning'],
        ['label'=>'Approved Hari Ini',  'val'=>$kpi['approved_today'],   'icon'=>'check-circle',       'color'=>'success'],
        ['label'=>'Rejected Hari Ini',  'val'=>$kpi['rejected_today'],   'icon'=>'x-circle',           'color'=>'danger'],
        ['label'=>'Open Tasks',         'val'=>$kpi['open_tasks'],       'icon'=>'inbox',              'color'=>'info'],
        ['label'=>'Overdue Tasks',      'val'=>$kpi['overdue_tasks'],    'icon'=>'alarm',              'color'=>'danger'],
        ['label'=>'Pending Callback',   'val'=>$kpi['pending_callback'], 'icon'=>'send',               'color'=>'secondary'],
        ['label'=>'Failed Callback',    'val'=>$kpi['failed_callback'],  'icon'=>'send-x',             'color'=>'danger'],
    ];
    @endphp
    @foreach($cards as $c)
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">{{ $c['label'] }}</div>
                        <div class="fs-4 fw-bold text-{{ $c['color'] }}">{{ number_format($c['val']) }}</div>
                    </div>
                    <i class="bi bi-{{ $c['icon'] }} fs-3 text-{{ $c['color'] }} opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Status breakdown 7 hari --}}
@if(!empty($kpi['status_breakdown']))
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header small"><i class="bi bi-bar-chart"></i> Status Request (7 Hari Terakhir)</div>
            <div class="card-body">
                @php
                $statusColors = [
                    'SUBMITTED'=>'secondary','IN_PROGRESS'=>'warning','APPROVED'=>'success',
                    'REJECTED'=>'danger','RETURNED'=>'warning','CANCELLED'=>'secondary','ERROR'=>'danger'
                ];
                @endphp
                @foreach($kpi['status_breakdown'] as $status => $count)
                <div class="mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="badge bg-{{ $statusColors[$status] ?? 'secondary' }}">{{ $status }}</span>
                        <strong>{{ $count }}</strong>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar bg-{{ $statusColors[$status] ?? 'secondary' }}"
                             style="width:{{ $kpi['total_request'] > 0 ? round($count/$kpi['total_request']*100) : 0 }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header small"><i class="bi bi-link-45deg"></i> Akses Cepat</div>
            <div class="card-body d-grid gap-2">
                <a href="{{ route('monitoring.index') }}" class="btn btn-outline-primary btn-sm text-start">
                    <i class="bi bi-list-check"></i> Monitoring Approval Request
                </a>
                <a href="{{ route('audit.callback-outbox') }}" class="btn btn-outline-warning btn-sm text-start">
                    <i class="bi bi-send"></i> Callback Outbox
                    @if($kpi['failed_callback'] > 0)
                        <span class="badge bg-danger ms-1">{{ $kpi['failed_callback'] }} gagal</span>
                    @endif
                </a>
                <a href="{{ route('audit.audit-event') }}" class="btn btn-outline-secondary btn-sm text-start">
                    <i class="bi bi-shield-exclamation"></i> Audit Event
                </a>
                <a href="{{ route('workflow.flow-definition.index') }}" class="btn btn-outline-info btn-sm text-start">
                    <i class="bi bi-diagram-3"></i> Workflow Builder
                </a>
            </div>
        </div>
    </div>
</div>
@endif
@endif

{{-- My Tasks --}}
@if(!empty($myTasks) && count($myTasks) > 0)
<div class="card shadow-sm">
    <div class="card-header small"><i class="bi bi-inbox"></i> Task Menunggu Saya ({{ count($myTasks) }})</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light small">
                    <tr><th>No Request</th><th>Judul</th><th>Step</th><th>Due</th><th></th></tr>
                </thead>
                <tbody class="small">
                @foreach($myTasks as $task)
                    <tr class="{{ $task->due_at && $task->due_at->isPast() ? 'table-danger' : '' }}">
                        <td><code>{{ optional($task->approvalRequest)->source_request_no ?? '-' }}</code></td>
                        <td>{{ Str::limit(optional($task->approvalRequest)->title, 40) }}</td>
                        <td>{{ optional($task->flowStep)->step_name }}</td>
                        <td>
                            @if($task->due_at)
                                <span class="{{ $task->due_at->isPast() ? 'text-danger fw-bold' : 'text-muted' }}">
                                    {{ $task->due_at->format('d/m H:i') }}
                                </span>
                            @else — @endif
                        </td>
                        <td><a href="{{ route('inbox.show', $task->idtbltask) }}" class="btn btn-xs btn-primary btn-sm">Buka</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer small text-end">
        <a href="{{ route('inbox.index') }}">Lihat semua inbox →</a>
    </div>
</div>
@elseif(auth()->user()->hasAnyRole('APPROVER','ADMIN_APPROVAL'))
<div class="alert alert-success">
    <i class="bi bi-check-circle"></i> Tidak ada task yang menunggu. Inbox bersih!
</div>
@endif
@endsection
