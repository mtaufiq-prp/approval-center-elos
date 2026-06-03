@extends('layouts.master')
@section('title', 'Edit User')
@section('master_content')
<h5 class="mb-3"><i class="bi bi-people"></i> Edit User: {{ $item->user_ref }}</h5>

@if (session('temp_password'))
<div class="alert alert-danger">
    <i class="bi bi-shield-lock"></i>
    <strong>Password sementara untuk {{ session('reset_user_ref') }}</strong> (tampil sekali):
    <div class="input-group mt-2">
        <input type="text" id="tpw" class="form-control font-monospace" value="{{ session('temp_password') }}" readonly>
        <button class="btn btn-outline-secondary" type="button"
                onclick="navigator.clipboard.writeText(document.getElementById('tpw').value)">
            <i class="bi bi-clipboard"></i> Copy
        </button>
    </div>
    <small class="d-block mt-2">User wajib ganti password saat login pertama.</small>
</div>
@endif

<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.user.update', $item->idtbluser) }}">@csrf @method('PUT')
        @include('master.user._form')
    </form>
</div></div>
@endsection
