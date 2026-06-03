@extends('layouts.master')
@section('title', 'Tambah User')
@section('master_content')
<h5 class="mb-3"><i class="bi bi-people"></i> Tambah User</h5>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.user.store') }}">@csrf
        @include('master.user._form')
    </form>
</div></div>
@endsection
