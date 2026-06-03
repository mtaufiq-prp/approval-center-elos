@extends('layouts.master')
@section('title', 'Approval Group')
@section('master_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-people-fill"></i> Approval Group</h5>
    <a href="{{ route('master.approval-group.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah</a>
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
                <th>Code</th><th>Name</th><th>Members</th><th>Status</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody class="small">
            @forelse ($items as $item)
                <tr>
                    <td><code>{{ $item->group_code }}</code></td>
                    <td>{{ $item->group_name }}</td>
                    <td><span class="badge bg-info">{{ $item->members_count }}</span></td>
                    <td>@if ($item->is_active)<span class="badge bg-success">Aktif</span>@else<span class="badge bg-secondary">Nonaktif</span>@endif</td>
                    <td class="text-end">
                        <a href="{{ route('master.approval-group.edit', $item->idtblapproval_group) }}" class="btn btn-sm btn-outline-primary">Edit & Member</a>
                        <form method="POST" action="{{ route('master.approval-group.destroy', $item->idtblapproval_group) }}"
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
