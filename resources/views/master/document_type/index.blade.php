@extends('layouts.master')
@section('title', 'Document Type')
@section('master_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Document Type</h5>
    <a href="{{ route('master.document-type.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah</a>
</div>
<div class="card shadow-sm"><div class="card-body">
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4"><input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Cari..."></div>
        <div class="col-md-3"><select name="idtblsource_app" class="form-select form-select-sm">
            <option value="">Semua source app</option>
            @foreach ($sourceApps as $sa) <option value="{{ $sa->idtblsource_app }}" {{ (string)request('idtblsource_app') === (string)$sa->idtblsource_app ? 'selected' : '' }}>{{ $sa->app_code }}</option>@endforeach
        </select></div>
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
                <th>Source App</th><th>Doc Code</th><th>Doc Name</th><th>Status</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody class="small">
            @forelse ($items as $item)
                <tr>
                    <td>{{ optional($item->sourceApp)->app_code }}</td>
                    <td><code>{{ $item->doc_code }}</code></td>
                    <td>{{ $item->doc_name }}</td>
                    <td>@if ($item->is_active)<span class="badge bg-success">Aktif</span>@else<span class="badge bg-secondary">Nonaktif</span>@endif</td>
                    <td class="text-end">
                        <a href="{{ route('master.document-type.edit', $item->idtbldocument_type) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="POST" action="{{ route('master.document-type.destroy', $item->idtbldocument_type) }}"
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
