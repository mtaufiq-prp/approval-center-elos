@extends('layouts.workflow')
@section('title','Tambah Flow Definition')
@section('workflow_content')
<h5 class="mb-3">Tambah Flow Definition</h5>
<div class="card shadow-sm"><div class="card-body">
<form method="POST" action="{{ route('workflow.flow-definition.store') }}">@csrf
@include('workflow.flow_definition._form')
</form></div></div>
@endsection
