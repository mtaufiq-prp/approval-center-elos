@extends('layouts.master')
@section('title', 'Delegation')
@section('master_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-person-check"></i> Delegation</h5>
    <a href="{{ route('master.delegation.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah</a>
</div>
<div class="card shadow-sm"><div class="card-body">
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-6"><input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Cari user_ref / nama..."></div>
        <div class="col-md-3"><select name="status" class="form-select form-select-sm">
            <option value="">Semua</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif (saat ini)</option>
            <option value="future" {{ request('status') === 'future' ? 'selected' : '' }}>Akan datang</option>
            <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Lewat</option>
        </select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Filter</button></div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light small"><tr>
                <th>Delegator</th><th>Delegate</th><th>Source App</th><th>Doc</th><th>Periode</th><th>Status</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody class="small">
            @forelse ($items as $item)
                @php $now = now(); $inPeriod = $now->between($item->start_at, $item->end_at); @endphp
                <tr>
                    <td><code>{{ optional($item->delegator)->user_ref }}</code> {{ optional($item->delegator)->full_name }}</td>
                    <td><code>{{ optional($item->delegate)->user_ref }}</code> {{ optional($item->delegate)->full_name }}</td>
                    <td>{{ optional($item->sourceApp)->app_code ?: 'ALL' }}</td>
                    <td>{{ optional($item->documentType)->doc_code ?: 'ALL' }}</td>
                    <td>{{ $item->start_at->format('Y-m-d H:i') }} → {{ $item->end_at->format('Y-m-d H:i') }}</td>
                    <td>
                        @if (!$item->is_active) <span class="badge bg-secondary">Stopped</span>
                        @elseif ($inPeriod) <span class="badge bg-success">Active</span>
                        @elseif ($now->lt($item->start_at)) <span class="badge bg-warning text-dark">Future</span>
                        @else <span class="badge bg-light text-dark">Expired</span> @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('master.delegation.edit', $item->idtbldelegation) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="POST" action="{{ route('master.delegation.destroy', $item->idtbldelegation) }}"
                              class="d-inline" data-confirm="Yakin?">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-{{ $item->is_active ? 'danger' : 'success' }}">
                                {{ $item->is_active ? 'Stop' : 'Resume' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @empty <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end">{{ $items->links() }}</div>
</div></div>
@endsection
