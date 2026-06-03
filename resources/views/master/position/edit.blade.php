@extends('layouts.master')
@section('title', 'Edit Position')
@section('master_content')
<h5 class="mb-3"><i class="bi bi-briefcase"></i> Edit Position: {{ $item->position_code }}</h5>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.position.update', $item->idtblposition) }}">@csrf @method('PUT') @include('master.position._form')</form>
</div></div>
@endsection
