@extends('layouts.app')

@push('scripts')
<script>
    // Confirm deactivate
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form[data-confirm]').forEach(f => {
            f.addEventListener('submit', e => {
                if (!confirm(f.dataset.confirm)) e.preventDefault();
            });
        });
    });
</script>
@endpush

@section('content')
<div class="row">
    <aside class="col-lg-3 col-xl-2 mb-3">
        <div class="card shadow-sm">
            <div class="card-header bg-light fw-semibold small">
                <i class="bi bi-gear"></i> Master Data
            </div>
            <div class="list-group list-group-flush small">
                <a class="list-group-item list-group-item-action {{ request()->routeIs('master.source-app.*') ? 'active' : '' }}"
                   href="{{ route('master.source-app.index') }}">Source App</a>
                <a class="list-group-item list-group-item-action {{ request()->routeIs('master.api-client.*') ? 'active' : '' }}"
                   href="{{ route('master.api-client.index') }}">API Client</a>
                <a class="list-group-item list-group-item-action {{ request()->routeIs('master.user.*') ? 'active' : '' }}"
                   href="{{ route('master.user.index') }}">User</a>
                <a class="list-group-item list-group-item-action {{ request()->routeIs('master.role.*') ? 'active' : '' }}"
                   href="{{ route('master.role.index') }}">Role</a>
                <a class="list-group-item list-group-item-action {{ request()->routeIs('master.org-unit.*') ? 'active' : '' }}"
                   href="{{ route('master.org-unit.index') }}">Org Unit</a>
                <a class="list-group-item list-group-item-action {{ request()->routeIs('master.position.*') ? 'active' : '' }}"
                   href="{{ route('master.position.index') }}">Position</a>
                <a class="list-group-item list-group-item-action {{ request()->routeIs('master.approval-group.*') ? 'active' : '' }}"
                   href="{{ route('master.approval-group.index') }}">Approval Group</a>
                <a class="list-group-item list-group-item-action {{ request()->routeIs('master.document-type.*') ? 'active' : '' }}"
                   href="{{ route('master.document-type.index') }}">Document Type</a>
                <a class="list-group-item list-group-item-action {{ request()->routeIs('master.delegation.*') ? 'active' : '' }}"
                   href="{{ route('master.delegation.index') }}">Delegation</a>
            </div>
        </div>
    </aside>

    <div class="col-lg-9 col-xl-10">
        @yield('master_content')
    </div>
</div>
@endsection
