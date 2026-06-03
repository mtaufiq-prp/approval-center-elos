@extends('layouts.master')
@section('title', 'Edit Source App')

@section('master_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-app-indicator"></i> Edit Source App: {{ $item->app_code }}</h5>
</div>

<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.source-app.update', $item->idtblsource_app) }}">
        @csrf
        @method('PUT')
        @include('master.source_app._form')
    </form>
</div></div>
@endsection
