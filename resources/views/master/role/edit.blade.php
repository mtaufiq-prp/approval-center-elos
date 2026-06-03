@extends('layouts.master')
@section('title', 'Edit Role')
@section('master_content')
<h5 class="mb-3"><i class="bi bi-person-badge"></i> Edit Role: {{ $item->role_code }}</h5>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.role.update', $item->idtblrole) }}">@csrf @method('PUT')
        @include('master.role._form')
    </form>
</div></div>
@endsection
