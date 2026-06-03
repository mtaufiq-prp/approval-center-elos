@extends('layouts.app')
@section('title', 'Riwayat Keputusan')

@section('content')
@php
    $decisionBadge = [
        'APPROVED'  => ['success', 'check-circle',          'Disetujui'],
        'REJECTED'  => ['danger',  'x-circle',              'Ditolak'],
        'RETURNED'  => ['warning', 'arrow-counterclockwise','Dikembalikan'],
        'CANCELLED' => ['secondary','slash-circle',         'Dibatalkan'],
        'SKIPPED'   => ['light',   'skip-forward',          'Dilewati'],
        'EXPIRED'   => ['dark',    'hourglass-bottom',      'Expired'],
    ];
    $activeDecision = request('decision');
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Riwayat Keputusan</h5>
</div>

{{-- Tab navigation --}}
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link" href="{{ route('inbox.index') }}">
            <i class="bi bi-inbox"></i> Inbox
            @if($inboxCount > 0)
                <span class="badge bg-danger ms-1">{{ $inboxCount }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="{{ route('inbox.history') }}">
            <i class="bi bi-clock-history"></i> Riwayat
        </a>
    </li>
</ul>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-5">
                <input type="text" name="search" value="{{ request('search') }}"
                       class="form-control form-control-sm" placeholder="Cari no request / judul...">
            </div>
            <div class="col-md-4">
                <select name="decision" class="form-select form-select-sm">
                    <option value="">Semua keputusan</option>
                    @foreach($decisionBadge as $code => [$c,$i,$lbl])
                        <option value="{{ $code }}" {{ $activeDecision === $code ? 'selected':'' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary flex-fill">Filter</button>
                @if(request('search') || request('decision'))
                    <a href="{{ route('inbox.history') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                @endif
            </div>
        </form>

        @php
            $reqStatusColor = [ 
                'DRAFT'=>'secondary','SUBMITTED'=>'secondary','IN_PROGRESS'=>'primary',
                'APPROVED'=>'success','REJECTED'=>'danger','RETURNED'=>'warning',
                'CANCELLED'=>'secondary','ERROR'=>'danger',
            ];
        @endphp
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light small">
                    <tr>
                        <th>Waktu Aksi</th>
                        <th>No Request</th>
                        <th>Judul</th>
                        <th>Source</th>
                        <th>Step yang Saya Aksi</th>
                        <th>Keputusan Saya</th>
                        <th>Catatan</th>
                        <th>Status Request Sekarang</th>
                        <th class="text-end">Aksi</th> 
                    </tr>
                </thead>
                <tbody class="small">
                @forelse($items as $task)
                    @php
                        [$color, $icon, $label] = $decisionBadge[$task->task_status] ?? ['info','info-circle',$task->task_status];
                        $reqStatus = optional($task->approvalRequest)->request_status;
                        $reqColor  = $reqStatusColor[$reqStatus] ?? 'secondary';
                        $curStep   = optional(optional($task->approvalRequest)->flowStepCurrent)->step_name;
                    @endphp
                    <tr>
                        <td class="text-nowrap text-muted">
                            {{ $task->completed_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td><code>{{ optional($task->approvalRequest)->source_request_no ?? '-' }}</code></td>
                        <td>{{ \Illuminate\Support\Str::limit(optional($task->approvalRequest)->title, 40) }}</td>
                        <td>{{ optional(optional($task->approvalRequest)->sourceApp)->app_code }}</td>
                        <td>{{ optional($task->flowStep)->step_name }}</td>
                        <td>
                            <span class="badge bg-{{ $color }}">
                                <i class="bi bi-{{ $icon }}"></i> {{ $label }}
                            </span>
                        </td>
                        <td>
                            @if($task->decision_note)
                                <span class="text-muted" title="{{ $task->decision_note }}">
                                    {{ \Illuminate\Support\Str::limit($task->decision_note, 60) }}
                                </span>
                            @else
                                <span class="text-muted fst-italic">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $reqColor }}">{{ $reqStatus ?? '—' }}</span>
                            @if($curStep && !in_array($reqStatus, ['APPROVED','REJECTED','CANCELLED']))
                                <div class="small text-muted mt-1">
                                    <i class="bi bi-geo-alt"></i> {{ $curStep }}
                                </div>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('inbox.show', $task->idtbltask) }}"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> Lihat
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        Belum ada riwayat keputusan.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-end">{{ $items->links() }}</div>
    </div>
</div>
@endsection
