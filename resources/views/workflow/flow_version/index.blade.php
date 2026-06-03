@extends('layouts.workflow')
@section('title','Flow Versions')
@section('workflow_content')
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="{{ route('workflow.flow-definition.index') }}">Flow Definition</a></li>
    <li class="breadcrumb-item active">{{ $definition->flow_code }}</li>
</ol></nav>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-layers"></i> Versions — {{ $definition->flow_name }}</h5>
    <a href="{{ route('workflow.flow-version.create', $definition->idtblflow_definition) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Version</a>
</div>
<div class="card shadow-sm"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light small"><tr>
                <th>v#</th><th>Name</th><th>Status</th><th>Validation</th><th>Nodes</th><th>Edges</th><th>In Use</th><th>Deployed</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody class="small">
            @forelse ($items as $v)
                <tr>
                    <td><strong>v{{ $v->version_no }}</strong></td>
                    <td>{{ $v->version_name }}</td>
                    <td>
                        @php $cls=['DRAFT'=>'secondary','ACTIVE'=>'success','INACTIVE'=>'warning','ARCHIVED'=>'dark'][$v->status]??'light'; @endphp
                        <span class="badge bg-{{ $cls }}">{{ $v->status }}</span>
                    </td>
                    <td>
                        @php $vc=['DRAFT'=>'secondary','VALID'=>'success','INVALID'=>'danger'][$v->validation_status??'DRAFT']??'secondary'; @endphp
                        <span class="badge bg-{{ $vc }}">{{ $v->validation_status ?? 'DRAFT' }}</span>
                    </td>
                    <td>{{ $v->steps_count }}</td>
                    <td>{{ $v->transitions_count }}</td>
                    <td>{{ $v->in_use_count > 0 ? '⚠️ '.$v->in_use_count.' req' : '-' }}</td>
                    <td class="text-muted small">{{ optional($v->deployed_at)->format('Y-m-d H:i') ?? '-' }}</td>
                    <td class="text-end">
                        <a href="{{ route('workflow.flow-version.show', $v->idtblflow_version) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                        <a href="{{ route('workflow.flow-version.preview', $v->idtblflow_version) }}" class="btn btn-sm btn-outline-info">Preview</a>
                        @if($v->status === 'DRAFT')
                        <form method="POST" action="{{ route('workflow.flow-version.deploy', $v->idtblflow_version) }}" class="d-inline" data-confirm="Deploy version ini menjadi ACTIVE?">
                            @csrf <button class="btn btn-sm btn-success">Deploy</button>
                        </form>
                        @endif
                        <form method="POST" action="{{ route('workflow.flow-version.clone', $v->idtblflow_version) }}" class="d-inline" data-confirm="Clone version ini?">
                            @csrf <button class="btn btn-sm btn-outline-warning">Clone</button>
                        </form>
                    </td>
                </tr>
            @empty <tr><td colspan="9" class="text-center text-muted py-4">Belum ada version.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end">{{ $items->links() }}</div>
</div></div>
@endsection
