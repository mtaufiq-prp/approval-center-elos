@extends('layouts.app')
@section('title', 'Callback Outbox')
@section('content')
<h5 class="mb-3"><i class="bi bi-send-check"></i> Callback Outbox</h5>
<div class="card shadow-sm mb-3"><div class="card-body">
    <form method="GET" class="row g-2">
        <div class="col-md-3"><select name="status" class="form-select form-select-sm">
            <option value="">Semua status</option>
            @foreach($statuses as $st)<option value="{{ $st }}" {{ request('status')===$st?'selected':'' }}>{{ $st }}</option>@endforeach
        </select></div>
        <div class="col-md-3"><select name="idtblsource_app" class="form-select form-select-sm">
            <option value="">Semua app</option>
            @foreach($sourceApps as $sa)<option value="{{ $sa->idtblsource_app }}" {{ (string)request('idtblsource_app')===(string)$sa->idtblsource_app?'selected':'' }}>{{ $sa->app_code }}</option>@endforeach
        </select></div>
        <div class="col-md-2"><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm"></div>
        <div class="col-md-2"><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm"></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
    </form>
</div></div>
<div class="card shadow-sm"><div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light small"><tr>
                <th>#</th><th>No Request</th><th>App</th><th>Event</th><th>Status</th>
                <th>Retry</th><th>HTTP</th><th>Next Retry</th><th></th>
            </tr></thead>
            <tbody class="small">
            @forelse($items as $item)
                <tr class="{{ $item->status==='DEAD'?'table-danger':($item->status==='FAILED'?'table-warning':'') }}">
                    <td>{{ $item->idtblcallback_outbox }}</td>
                    <td><code>{{ optional($item->approvalRequest)->source_request_no ?? '—' }}</code></td>
                    <td>{{ optional($item->sourceApp)->app_code }}</td>
                    <td><span class="badge bg-info">{{ $item->event_type }}</span></td>
                    <td><span class="badge bg-{{ match($item->status){'SENT'=>'success','PENDING'=>'warning','FAILED'=>'danger','DEAD'=>'dark',default=>'secondary'} }}">{{ $item->status }}</span></td>
                    <td>{{ $item->retry_count }}/{{ $item->max_retry }}</td>
                    <td>@if($item->last_response_code)<span class="badge bg-{{ $item->last_response_code < 400 ? 'success':'danger' }}">{{ $item->last_response_code }}</span>@else —@endif</td>
                    <td class="text-muted">{{ $item->next_retry_at?->format('d/m H:i') ?? '—' }}</td>
                    <td>
                        @if(in_array($item->status, ['FAILED','DEAD']))
                        <form method="POST" action="{{ route('audit.callback-outbox.retry', $item->idtblcallback_outbox) }}"
                              class="d-inline" data-confirm="Retry callback ini?">
                            @csrf
                            <button class="btn btn-sm btn-warning py-0">Retry</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between small text-muted">
        <span>Total: {{ $items->total() }}</span>{{ $items->links() }}
    </div>
</div></div>
@endsection
