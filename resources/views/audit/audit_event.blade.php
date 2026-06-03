@extends('layouts.app')
@section('title', 'Audit Event')
@section('content')
<h5 class="mb-3"><i class="bi bi-shield-exclamation"></i> Audit Event</h5>
<div class="card shadow-sm mb-3"><div class="card-body">
    <form method="GET" class="row g-2">
        <div class="col-md-4"><input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Cari entity / event / actor..."></div>
        <div class="col-md-2"><select name="event_code" class="form-select form-select-sm">
            <option value="">Semua event</option>
            @foreach($eventCodes as $ec)<option value="{{ $ec }}" {{ request('event_code')===$ec ? 'selected':'' }}>{{ $ec }}</option>@endforeach
        </select></div>
        <div class="col-md-2"><select name="entity_type" class="form-select form-select-sm">
            <option value="">Semua entity</option>
            @foreach($entityTypes as $et)<option value="{{ $et }}" {{ request('entity_type')===$et ? 'selected':'' }}>{{ $et }}</option>@endforeach
        </select></div>
        <div class="col-md-2"><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm"></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
    </form>
</div></div>
<div class="card shadow-sm"><div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light small"><tr>
                <th>Waktu</th><th>Entity</th><th>ID</th><th>Event</th><th>Actor</th><th>Pesan</th>
            </tr></thead>
            <tbody class="small">
            @forelse($items as $item)
                <tr>
                    <td class="text-muted text-nowrap">{{ $item->created_at?->format('d/m/y H:i') }}</td>
                    <td><code class="small">{{ $item->entity_type }}</code></td>
                    <td class="text-muted">{{ $item->entity_id }}</td>
                    <td><code class="small">{{ $item->event_code }}</code></td>
                    <td><code>{{ $item->actor_ref }}</code></td>
                    <td class="text-muted">{{ Str::limit($item->event_message, 70) }}</td>
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
