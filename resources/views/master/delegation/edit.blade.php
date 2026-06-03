@extends('layouts.master')
@section('title', 'Edit Delegation')
@section('master_content')
<h5 class="mb-3"><i class="bi bi-person-check"></i> Edit Delegation #{{ $item->idtbldelegation }}</h5>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.delegation.update', $item->idtbldelegation) }}">@csrf @method('PUT') @include('master.delegation._form')</form>
</div></div>
@endsection
