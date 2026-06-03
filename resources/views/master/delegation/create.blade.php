@extends('layouts.master')
@section('title', 'Tambah Delegation')
@section('master_content')
<h5 class="mb-3"><i class="bi bi-person-check"></i> Tambah Delegation</h5>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.delegation.store') }}">@csrf @include('master.delegation._form')</form>
</div></div>
@endsection
