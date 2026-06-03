@extends('layouts.app')
@section('title', 'Inbox')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-inbox"></i> Inbox
        @if($inboxCount > 0)
            <span class="badge bg-danger ms-1">{{ $inboxCount }}</span>
        @endif
    </h5>
</div>

{{-- Tab navigation --}} 
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link active" href="{{ route('inbox.index') }}">
            <i class="bi bi-inbox"></i> Inbox
            @if($inboxCount > 0)
                <span class="badge bg-danger ms-1">{{ $inboxCount }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('inbox.history') }}">
            <i class="bi bi-clock-history"></i> Riwayat
        </a>
    </li>
</ul>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-6">
                <input type="text" name="search" value="{{ request('search') }}"
                       class="form-control form-control-sm" placeholder="Cari no request / judul...">
            </div>
            <div class="col-md-3">
                <select name="overdue" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="1" {{ request('overdue')==='1' ? 'selected':'' }}>Overdue saja</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-outline-primary w-100">Filter</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light small">
                    <tr>
                        <th>No Request</th><th>Judul</th><th>Source App</th>
                        <th>Step</th><th>Prioritas</th><th>Due</th><th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="small">
                @forelse($items as $task)
                    @php $overdue = $task->due_at && $task->due_at->isPast(); @endphp
                    <tr class="{{ $overdue ? 'table-warning' : '' }}">
                        <td><code>{{ optional($task->approvalRequest)->source_request_no ?? '-' }}</code></td>
                        <td>{{ Str::limit(optional($task->approvalRequest)->title, 45) }}</td>
                        <td>{{ optional(optional($task->approvalRequest)->sourceApp)->app_code }}</td>
                        <td>{{ optional($task->flowStep)->step_name }}</td>
                        <td>
                            @php $p = optional($task->approvalRequest)->priority; @endphp
                            <span class="badge bg-{{ match($p) { 'URGENT'=>'danger','HIGH'=>'warning','LOW'=>'secondary', default=>'info' } }}">
                                {{ $p ?? '-' }}
                            </span>
                        </td>
                        <td>
                            @if($task->due_at)
                                <span class="{{ $overdue ? 'text-danger fw-bold' : 'text-muted' }}">
                                    {{ $task->due_at->format('d/m H:i') }}
                                    @if($overdue) <i class="bi bi-alarm-fill"></i> @endif
                                </span>
                            @else <span class="text-muted">—</span> @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('inbox.show', $task->idtbltask) }}"
                               class="btn btn-sm btn-primary">
                                <i class="bi bi-arrow-right-circle"></i> Buka
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>
                        Inbox kosong. Tidak ada task yang menunggu.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-end">{{ $items->links() }}</div>
    </div>
</div>
@endsection
