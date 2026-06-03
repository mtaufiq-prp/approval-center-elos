@extends('layouts.master')
@section('title', 'Tambah Org Unit')
@section('master_content')
<h5 class="mb-3"><i class="bi bi-diagram-3"></i> Tambah Org Unit</h5>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.org-unit.store') }}">@csrf @include('master.org_unit._form')</form>
</div></div>
@endsection
