@extends('layouts.app')
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('form[data-confirm]').forEach(f=>{
        f.addEventListener('submit',e=>{ if(!confirm(f.dataset.confirm)) e.preventDefault(); });
    });
});
</script>
@endpush
@section('content')
<div class="row">
    <aside class="col-lg-3 col-xl-2 mb-3">
        <div class="card shadow-sm">
            <div class="card-header bg-light fw-semibold small"><i class="bi bi-diagram-2"></i> Workflow Builder</div>
            <div class="list-group list-group-flush small">
                <a class="list-group-item list-group-item-action {{ request()->routeIs('workflow.flow-definition.*') ? 'active' : '' }}"
                   href="{{ route('workflow.flow-definition.index') }}">Flow Definition</a>
                <a class="list-group-item list-group-item-action {{ request()->routeIs('workflow.routing-rule.*') ? 'active' : '' }}"
                   href="{{ route('workflow.routing-rule.index') }}">Routing Rule</a>
            </div>
        </div>
    </aside>
    <div class="col-lg-9 col-xl-10">
        @yield('workflow_content')
    </div>
</div>
@endsection
