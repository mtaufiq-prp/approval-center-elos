@extends('layouts.workflow')
@section('title', 'Flow Definition')
@section('workflow_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-diagram-2"></i> Flow Definition</h5>
    <a href="{{ route('workflow.flow-definition.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah</a>
</div>
<div class="card shadow-sm"><div class="card-body">
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-5"><input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Cari..."></div>
        <div class="col-md-3"><select name="idtblsource_app" class="form-select form-select-sm">
            <option value="">Semua app</option>
            @foreach ($sourceApps as $sa)
                <option value="{{ $sa->idtblsource_app }}" {{ (string)request('idtblsource_app')===(string)$sa->idtblsource_app?'selected':'' }}>{{ $sa->app_code }}</option>
            @endforeach
        </select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Filter</button></div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light small"><tr>
                <th>Flow Code</th><th>Flow Name</th><th>App</th><th>Doc Type</th><th>Versions</th><th>Status</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody class="small">
            @forelse ($items as $item)
                <tr>
                    <td><code>{{ $item->flow_code }}</code></td>
                    <td>{{ $item->flow_name }}</td>
                    <td>{{ optional($item->sourceApp)->app_code }}</td>
                    <td>{{ optional($item->documentType)->doc_code }}</td>
                    <td><span class="badge bg-secondary">{{ $item->versions_count }}</span></td>
                    <td>@if($item->is_active)<span class="badge bg-success">Aktif</span>@else<span class="badge bg-secondary">Nonaktif</span>@endif</td>
                    <td class="text-end">
                        <a href="{{ route('workflow.flow-version.index', $item->idtblflow_definition) }}" class="btn btn-sm btn-outline-info">Versions</a>
                        <a href="{{ route('workflow.flow-definition.edit', $item->idtblflow_definition) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="POST" action="{{ route('workflow.flow-definition.destroy', $item->idtblflow_definition) }}" class="d-inline" data-confirm="Yakin?">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-{{ $item->is_active?'danger':'success' }}">{{ $item->is_active?'Deactivate':'Activate' }}</button>
                        </form>
                    </td>
                </tr>
            @empty <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end">{{ $items->links() }}</div>
</div></div>
@endsection
