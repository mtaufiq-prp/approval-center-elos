@extends('layouts.app')
@section('title', 'Integration Log')
@section('content')
<h5 class="mb-3"><i class="bi bi-arrow-left-right"></i> Integration Log</h5>
<div class="card shadow-sm mb-3"><div class="card-body">
    <form method="GET" class="row g-2">
        <div class="col-md-4"><input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Cari endpoint / app..."></div>
        <div class="col-md-2"><select name="direction" class="form-select form-select-sm">
            <option value="">Semua arah</option>
            <option value="INBOUND" {{ request('direction')==='INBOUND'?'selected':'' }}>INBOUND</option>
            <option value="OUTBOUND" {{ request('direction')==='OUTBOUND'?'selected':'' }}>OUTBOUND</option>
        </select></div>
        <div class="col-md-2"><select name="idtblsource_app" class="form-select form-select-sm">
            <option value="">Semua app</option>
            @foreach($sourceApps as $sa)<option value="{{ $sa->idtblsource_app }}" {{ (string)request('idtblsource_app')===(string)$sa->idtblsource_app?'selected':'' }}>{{ $sa->app_code }}</option>@endforeach
        </select></div>
        <div class="col-md-2"><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm"></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
    </form>
</div></div>
<div class="card shadow-sm"><div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light small"><tr>
                <th>Waktu</th><th>Arah</th><th>App</th><th>Method</th><th>Endpoint</th><th>HTTP</th>
            </tr></thead>
            <tbody class="small">
            @forelse($items as $item)
                <tr>
                    <td class="text-muted text-nowrap">{{ $item->created_at?->format('d/m/y H:i') }}</td>
                    <td><span class="badge bg-{{ $item->direction==='INBOUND'?'info':'primary' }}">{{ $item->direction }}</span></td>
                    <td>{{ optional($item->sourceApp)->app_code }}</td>
                    <td><code class="small">{{ $item->http_method }}</code></td>
                    <td class="text-muted small">{{ Str::limit($item->endpoint, 50) }}</td>
                    <td>
                        @if($item->response_code)
                            <span class="badge bg-{{ $item->response_code < 400 ? 'success' : 'danger' }}">
                                {{ $item->response_code }}
                            </span>
                        @else —
                        @endif
                    </td>
                </tr>
            @empty <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between small text-muted">
        <span>Total: {{ $items->total() }}</span>{{ $items->links() }}
    </div>
</div></div>
@endsection
