@extends('layouts.master')
@section('title', 'Tambah Role')
@section('master_content')
<h5 class="mb-3"><i class="bi bi-person-badge"></i> Tambah Role</h5>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.role.store') }}">@csrf
        @include('master.role._form', ['protected' => []])
    </form>
</div></div>
@endsection
