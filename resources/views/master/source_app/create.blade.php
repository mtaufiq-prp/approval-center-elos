@extends('layouts.master')
@section('title', 'Tambah Source App')

@section('master_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-app-indicator"></i> Tambah Source App</h5>
</div>

<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.source-app.store') }}">
        @csrf
        @include('master.source_app._form')
    </form>
</div></div>
@endsection
