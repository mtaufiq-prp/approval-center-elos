@extends('layouts.app')
@section('title', 'Monitoring Approval')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-list-check"></i> Monitoring Approval Request</h5>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <input type="text" name="search" value="{{ request('search') }}"
                       class="form-control form-control-sm" placeholder="Cari no/judul/pemohon...">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua status</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" {{ request('status')===$s ? 'selected':'' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="idtblsource_app" class="form-select form-select-sm">
                    <option value="">Semua app</option>
                    @foreach($sourceApps as $sa)
                        <option value="{{ $sa->idtblsource_app }}" {{ (string)request('idtblsource_app')===(string)$sa->idtblsource_app ? 'selected':'' }}>
                            {{ $sa->app_code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="priority" class="form-select form-select-sm">
                    <option value="">Semua prioritas</option>
                    @foreach(['LOW','NORMAL','HIGH','URGENT'] as $p)
                        <option value="{{ $p }}" {{ request('priority')===$p ? 'selected':'' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="form-control form-control-sm" title="Dari tanggal">
            </div>
            <div class="col-md-1">
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="form-control form-control-sm" title="Sampai tanggal">
            </div>
            <div class="col-12 text-end">
                <a href="{{ route('monitoring.index') }}" class="btn btn-sm btn-outline-secondary me-1">Reset</a>
                <button class="btn btn-sm btn-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light small">
                    <tr>
                        <th>No Request</th><th>Judul</th><th>App</th><th>Pemohon</th>
                        <th>Prioritas</th><th>Status</th><th>Step Saat Ini</th><th>Dibuat</th><th></th>
                    </tr>
                </thead>
                <tbody class="small">
                @php
                $sc = ['SUBMITTED'=>'secondary','IN_PROGRESS'=>'primary','APPROVED'=>'success',
                       'REJECTED'=>'danger','RETURNED'=>'warning','CANCELLED'=>'secondary','ERROR'=>'danger'];
                @endphp
                @forelse($items as $req)
                    <tr>
                        <td><code>{{ $req->source_request_no ?? '-' }}</code></td>
                        <td>{{ Str::limit($req->title, 35) }}</td>
                        <td>{{ optional($req->sourceApp)->app_code }}</td>
                        <td>{{ $req->requester_name }}</td>
                        <td><span class="badge bg-{{ match($req->priority){'URGENT'=>'danger','HIGH'=>'warning','LOW'=>'secondary',default=>'info'} }}">{{ $req->priority }}</span></td>
                        <td><span class="badge bg-{{ $sc[$req->request_status] ?? 'secondary' }}">{{ $req->request_status }}</span></td>
                        <td class="text-muted">@nodeLabel(optional(optional($req->processInstance)->flowStepCurrent)->step_name)</td>
                        <td class="text-muted text-nowrap">{{ $req->created_at?->format('d/m/y H:i') }}</td>
                        <td>
                            <a href="{{ route('monitoring.show', $req->idtblapproval_request) }}"
                               class="btn btn-sm btn-outline-primary">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-5">Tidak ada data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center small text-muted">
        <span>Total: {{ $items->total() }}</span>
        {{ $items->links() }}
    </div>
</div>
@endsection
