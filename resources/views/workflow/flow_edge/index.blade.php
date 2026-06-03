@extends('layouts.workflow')
@section('title','Flow Edges')
@section('workflow_content')
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="{{ route('workflow.flow-version.show', $version->idtblflow_version) }}">v{{ $version->version_no }}</a></li>
    <li class="breadcrumb-item active">Edges</li>
</ol></nav>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-arrow-right-circle"></i> Flow Edges / Transitions</h5>
    @unless ($version->isActive() && $version->isInUse())
    <a href="{{ route('workflow.flow-edge.create', $version->idtblflow_version) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah Edge</a>
    @endunless
</div>
<div class="card shadow-sm"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light small"><tr>
                <th>Code</th><th>From Node</th><th>Action</th><th>To Node</th><th>Condition</th><th>Priority</th><th>Default</th><th>Active</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody class="small">
            @forelse ($edges as $e)
                <tr>
                    <td><code class="small">{{ $e->transition_code }}</code></td>
                    <td><code>{{ optional($e->stepFrom)->node_code }}</code></td>
                    <td><span class="badge bg-light text-dark border">{{ $e->action_code }}</span></td>
                    <td><code>{{ $e->idtblflow_step_to ? optional($e->stepTo)->node_code : '[END]' }}</code></td>
                    <td class="text-muted small">{{ $e->condition_json ? \Str::limit(json_encode($e->condition_json), 50) : 'always' }}</td>
                    <td>{{ $e->priority_no }}</td>
                    <td>@if($e->is_default)<span class="badge bg-info">DEFAULT</span>@endif</td>
                    <td>@if($e->is_active)<span class="badge bg-success">Y</span>@else<span class="badge bg-secondary">N</span>@endif</td>
                    <td class="text-end">
                        <a href="{{ route('workflow.flow-edge.edit', [$version->idtblflow_version, $e->idtblflow_transition]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        @unless ($version->isActive() && $version->isInUse())
                        <form method="POST" action="{{ route('workflow.flow-edge.destroy', [$version->idtblflow_version, $e->idtblflow_transition]) }}" class="d-inline" data-confirm="Hapus edge ini?">
                            @csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger">Hapus</button>
                        </form>
                        @endunless
                    </td>
                </tr>
            @empty <tr><td colspan="9" class="text-center text-muted py-4">Belum ada edge.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div></div>
@endsection
