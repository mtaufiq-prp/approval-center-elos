@extends('layouts.master')
@section('title', 'Edit Org Unit')
@section('master_content')
<h5 class="mb-3"><i class="bi bi-diagram-3"></i> Edit Org Unit: {{ $item->org_code }}</h5>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.org-unit.update', $item->idtblorg_unit) }}">@csrf @method('PUT') @include('master.org_unit._form')</form>
</div></div>
@endsection
