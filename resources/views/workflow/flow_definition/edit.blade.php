@extends('layouts.workflow')
@section('title','Edit Flow Definition')
@section('workflow_content')
<h5 class="mb-3">Edit: {{ $item->flow_code }}</h5>
<div class="card shadow-sm"><div class="card-body">
<form method="POST" action="{{ route('workflow.flow-definition.update', $item->idtblflow_definition) }}">@csrf @method('PUT')
@include('workflow.flow_definition._form')
</form></div></div>
@endsection
