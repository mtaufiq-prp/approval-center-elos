@extends('layouts.master')
@section('title', 'Org Unit')
@section('master_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Org Unit</h5>
    <a href="{{ route('master.org-unit.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah</a>
</div>
<div class="card shadow-sm"><div class="card-body">
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-5"><input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Cari..."></div>
        <div class="col-md-3"><select name="is_active" class="form-select form-select-sm">
            <option value="all">Semua status</option>
            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Aktif</option>
            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Nonaktif</option>
        </select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Filter</button></div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light small"><tr>
                <th>Code</th><th>Name</th><th>Parent</th><th>Status</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody class="small">
            @forelse ($items as $item)
                <tr>
                    <td><code>{{ $item->org_code }}</code></td>
                    <td>{{ $item->org_name }}</td>
                    <td class="text-muted">{{ optional($item->parent)->org_code }}</td>
                    <td>@if ($item->is_active)<span class="badge bg-success">Aktif</span>@else<span class="badge bg-secondary">Nonaktif</span>@endif</td>
                    <td class="text-end">
                        <a href="{{ route('master.org-unit.edit', $item->idtblorg_unit) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="POST" action="{{ route('master.org-unit.destroy', $item->idtblorg_unit) }}"
                              class="d-inline" data-confirm="Yakin?">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-{{ $item->is_active ? 'danger' : 'success' }}">
                                {{ $item->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @empty <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end">{{ $items->links() }}</div>
</div></div>
@endsection
