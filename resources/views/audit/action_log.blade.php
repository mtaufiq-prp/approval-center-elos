@extends('layouts.app')
@section('title', 'Action Log')
@section('content')
<h5 class="mb-3"><i class="bi bi-journal-text"></i> Action Log</h5>
<div class="card shadow-sm mb-3"><div class="card-body">
    <form method="GET" class="row g-2">
        <div class="col-md-4"><input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Cari actor / action / no request..."></div>
        <div class="col-md-2"><select name="action_code" class="form-select form-select-sm">
            <option value="">Semua action</option>
            @foreach($actionCodes as $ac)<option value="{{ $ac }}" {{ request('action_code')===$ac ? 'selected':'' }}>{{ $ac }}</option>@endforeach
        </select></div>
        <div class="col-md-2"><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm" placeholder="Dari"></div>
        <div class="col-md-2"><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm" placeholder="Sampai"></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
    </form>
</div></div>
<div class="card shadow-sm"><div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light small"><tr>
                <th>Waktu</th><th>No Request</th><th>Aktor</th><th>Aksi</th>
                <th>Status Sebelum</th><th>Status Sesudah</th><th>Catatan</th>
            </tr></thead>
            <tbody class="small">
            @forelse($items as $item)
                <tr>
                    <td class="text-muted text-nowrap">{{ $item->created_at?->format('d/m/y H:i') }}</td>
                    <td><code>{{ optional($item->approvalRequest)->source_request_no ?? '—' }}</code></td>
                    <td><code>{{ $item->actor_ref }}</code></td>
                    <td><span class="badge bg-{{ match($item->action_code){'APPROVE','AUTO_APPROVE'=>'success','REJECT'=>'danger','RETURN'=>'warning','CANCEL'=>'secondary',default=>'info'} }}">{{ $item->action_code }}</span></td>
                    <td><span class="badge bg-secondary">{{ $item->before_status }}</span></td>
                    <td><span class="badge bg-primary">{{ $item->after_status }}</span></td>
                    <td class="text-muted">{{ Str::limit($item->action_note, 60) }}</td>
                </tr>
            @empty <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between small text-muted">
        <span>Total: {{ $items->total() }}</span>{{ $items->links() }}
    </div>
</div></div>
@endsection
