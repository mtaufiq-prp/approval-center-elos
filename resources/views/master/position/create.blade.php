@extends('layouts.master')
@section('title', 'Tambah Position')
@section('master_content')
<h5 class="mb-3"><i class="bi bi-briefcase"></i> Tambah Position</h5>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.position.store') }}">@csrf @include('master.position._form')</form>
</div></div>
@endsection
