@extends('layouts.workflow')
@section('title','Routing Rule')
@section('workflow_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-signpost-split"></i> Routing Rule</h5>
    <a href="{{ route('workflow.routing-rule.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah</a>
</div>
<div class="card shadow-sm"><div class="card-body">
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-5"><input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Cari rule_code..."></div>
        <div class="col-md-3"><select name="idtblsource_app" class="form-select form-select-sm">
            <option value="">Semua app</option>
            @foreach ($sourceApps as $sa)<option value="{{ $sa->idtblsource_app }}" {{ (string)request('idtblsource_app')===(string)$sa->idtblsource_app?'selected':'' }}>{{ $sa->app_code }}</option>@endforeach
        </select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Filter</button></div>
    </form>
    <div class="alert alert-info small"><i class="bi bi-info-circle"></i>
        Routing Rule menentukan <strong>flow mana</strong> yang dipakai saat approval request masuk berdasarkan
        source_app, document_type, dan condition_json. Rule dengan <code>priority_no</code> terkecil dievaluasi pertama.
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light small"><tr>
                <th>Priority</th><th>Rule Code</th><th>App</th><th>Doc</th><th>Flow</th><th>Version Override</th><th>Condition</th><th>Status</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody class="small">
            @forelse ($items as $r)
                <tr>
                    <td><strong>{{ $r->priority_no }}</strong></td>
                    <td><code>{{ $r->rule_code }}</code></td>
                    <td>{{ optional($r->sourceApp)->app_code }}</td>
                    <td>{{ optional($r->documentType)->doc_code }}</td>
                    <td><code>{{ optional($r->flowDefinition)->flow_code }}</code></td>
                    <td>{{ $r->idtblflow_version ? 'v'.optional($r->flowVersion)->version_no : '<em>ACTIVE auto</em>' }}</td>
                    <td class="text-muted small">{{ \Str::limit(json_encode($r->condition_json), 60) }}</td>
                    <td>@if($r->is_active)<span class="badge bg-success">Aktif</span>@else<span class="badge bg-secondary">Nonaktif</span>@endif</td>
                    <td class="text-end">
                        <a href="{{ route('workflow.routing-rule.edit', $r->idtblrouting_rule) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="POST" action="{{ route('workflow.routing-rule.destroy', $r->idtblrouting_rule) }}" class="d-inline" data-confirm="Yakin?">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-{{ $r->is_active?'danger':'success' }}">{{ $r->is_active?'Deactivate':'Activate' }}</button>
                        </form>
                    </td>
                </tr>
            @empty <tr><td colspan="9" class="text-center text-muted py-4">Belum ada routing rule.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end">{{ $items->links() }}</div>
</div></div>
@endsection
