@extends('layouts.workflow')
@section('title','Flow Nodes')
@section('workflow_content')
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="{{ route('workflow.flow-definition.index') }}">Flow Definition</a></li>
    <li class="breadcrumb-item"><a href="{{ route('workflow.flow-version.index', $version->idtblflow_definition) }}">{{ optional($version->flowDefinition)->flow_code }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('workflow.flow-version.show', $version->idtblflow_version) }}">v{{ $version->version_no }}</a></li>
    <li class="breadcrumb-item active">Nodes</li>
</ol></nav>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-circle-square"></i> Flow Nodes</h5>
    @unless ($version->isActive() && $version->isInUse())
    <a href="{{ route('workflow.flow-node.create', $version->idtblflow_version) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah Node</a>
    @endunless
</div>
<div class="card shadow-sm"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light small"><tr>
                <th>node_code</th><th>step_name</th><th>Type</th><th>Gateway</th><th>Assignee Rules</th><th>Order</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody class="small">
            @forelse ($nodes->sortBy('step_order') as $n)
                <tr>
                    <td><code>{{ $n->node_code }}</code></td>
                    <td>{{ $n->step_name }}</td>
                    <td><span class="badge bg-{{ ['START'=>'success','END'=>'danger','DECISION'=>'warning','APPROVAL'=>'primary'][$n->step_type]??'secondary' }}">{{ $n->step_type }}</span></td>
                    <td>{{ $n->gateway_type !== 'NONE' ? $n->gateway_type : '-' }}</td>
                    <td>{{ $n->active_assignee_rules_count }}</td>
                    <td>{{ $n->step_order }}</td>
                    <td class="text-end">
                        <a href="{{ route('workflow.flow-node.edit', [$version->idtblflow_version, $n->idtblflow_step]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        @unless ($version->isActive() && $version->isInUse())
                        <form method="POST" action="{{ route('workflow.flow-node.destroy', [$version->idtblflow_version, $n->idtblflow_step]) }}" class="d-inline" data-confirm="Hapus node '{{ $n->node_code }}'?">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Hapus</button>
                        </form>
                        @endunless
                    </td>
                </tr>
            @empty <tr><td colspan="7" class="text-center text-muted py-4">Belum ada node.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div></div>
@endsection
