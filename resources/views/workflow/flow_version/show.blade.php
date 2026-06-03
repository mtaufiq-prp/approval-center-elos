@extends('layouts.workflow')
@section('title','Flow Version Detail')
@section('workflow_content')
<nav aria-label="breadcrumb" class="mb-2"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="{{ route('workflow.flow-definition.index') }}">Flow Definition</a></li>
    <li class="breadcrumb-item"><a href="{{ route('workflow.flow-version.index', $version->idtblflow_definition) }}">{{ optional($version->flowDefinition)->flow_code }}</a></li>
    <li class="breadcrumb-item active">v{{ $version->version_no }}</li>
</ol></nav>

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if (session('validation_result'))
    @php $vr = session('validation_result'); @endphp
    @if (!empty($vr['errors']))
        <div class="alert alert-danger small"><strong>Errors:</strong><ul class="mb-0">@foreach($vr['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @if (!empty($vr['warnings']))
        <div class="alert alert-warning small"><strong>Warnings:</strong><ul class="mb-0">@foreach($vr['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul></div>
    @endif
    @if (!empty($vr['checks']))
        <div class="alert alert-light small"><strong>Checks:</strong><ul class="mb-0">@foreach($vr['checks'] as $c)<li>{{ $c }}</li>@endforeach</ul></div>
    @endif
@endif

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 small">
            <div class="col-md-3"><strong>Flow:</strong> {{ optional($version->flowDefinition)->flow_code }}</div>
            <div class="col-md-3"><strong>Version:</strong> v{{ $version->version_no }} — {{ $version->version_name }}</div>
            <div class="col-md-2"><strong>Status:</strong>
                @php $cls=['DRAFT'=>'secondary','ACTIVE'=>'success','INACTIVE'=>'warning','ARCHIVED'=>'dark'][$version->status]??'light'; @endphp
                <span class="badge bg-{{ $cls }}">{{ $version->status }}</span>
            </div>
            <div class="col-md-4"><strong>Validation:</strong>
                @php $vc=['DRAFT'=>'secondary','VALID'=>'success','INVALID'=>'danger'][$version->validation_status??'DRAFT']??'secondary'; @endphp
                <span class="badge bg-{{ $vc }}">{{ $version->validation_status ?? 'DRAFT' }}</span>
                @if($version->validated_at) <small class="text-muted">{{ $version->validated_at->format('Y-m-d H:i') }}</small> @endif
            </div>
            @if ($version->validation_message)
                <div class="col-12 text-muted">{{ $version->validation_message }}</div>
            @endif
        </div>
        <div class="d-flex gap-2 mt-3 flex-wrap">
            <a href="{{ route('workflow.flow-version.builder', $version->idtblflow_version) }}"
               class="btn btn-sm btn-primary">
                <i class="bi bi-diagram-3"></i> Visual Builder
            </a>
            <a href="{{ route('workflow.flow-node.index', $version->idtblflow_version) }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-circle"></i> Nodes ({{ $version->steps->count() }})
            </a>
            <a href="{{ route('workflow.flow-edge.index', $version->idtblflow_version) }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-arrow-right"></i> Edges ({{ $version->transitions->count() }})
            </a>
            <a href="{{ route('workflow.flow-version.preview', $version->idtblflow_version) }}" class="btn btn-sm btn-outline-info">
                <i class="bi bi-eye"></i> Preview
            </a>
            <form method="POST" action="{{ route('workflow.flow-version.validate', $version->idtblflow_version) }}" class="d-inline">
                @csrf <button class="btn btn-sm btn-outline-warning"><i class="bi bi-check-circle"></i> Validate</button>
            </form>
            @if ($version->isDraft() && $version->isValidated())
            <form method="POST" action="{{ route('workflow.flow-version.deploy', $version->idtblflow_version) }}" class="d-inline" data-confirm="Deploy version ini menjadi ACTIVE?">
                @csrf <button class="btn btn-sm btn-success"><i class="bi bi-rocket"></i> Deploy</button>
            </form>
            @endif
            <form method="POST" action="{{ route('workflow.flow-version.clone', $version->idtblflow_version) }}" class="d-inline" data-confirm="Clone version ini ke version baru?">
                @csrf <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-copy"></i> Clone</button>
            </form>
            <a href="{{ route('workflow.flow-version.edit', $version->idtblflow_version) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil"></i> Edit Metadata
            </a>
        </div>
        @if ($version->isActive() && $version->isInUse())
            <div class="alert alert-warning small mt-3 mb-0">
                <i class="bi bi-lock"></i> Version ini sudah ACTIVE dan dipakai approval request.
                Node, edge, dan assignee rule <strong>tidak dapat diedit</strong>. Gunakan <strong>Clone</strong> untuk membuat versi baru.
            </div>
        @endif
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light small fw-semibold">Nodes</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="table-light small"><tr>
                    <th>node_code</th><th>step_name</th><th>Type</th><th>Gateway</th><th>Assignee Rules</th><th>step_order</th>
                </tr></thead>
                <tbody class="small">
                @foreach ($version->steps as $n)
                    <tr>
                        <td><code>{{ $n->node_code }}</code></td>
                        <td>{{ $n->step_name }}</td>
                        <td><span class="badge bg-{{ ['START'=>'success','END'=>'danger','DECISION'=>'warning','APPROVAL'=>'primary'][$n->step_type]??'secondary' }}">{{ $n->step_type }}</span></td>
                        <td><small>{{ $n->gateway_type !== 'NONE' ? $n->gateway_type : '-' }}</small></td>
                        <td>{{ $n->activeAssigneeRules->count() }}</td>
                        <td>{{ $n->step_order }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
