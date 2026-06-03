@extends('layouts.workflow')
@section('title','Edit Edge')
@section('workflow_content')
<h5 class="mb-3"><i class="bi bi-arrow-right-circle"></i> Edit Edge @if($isLocked)<span class="badge bg-warning text-dark ms-2"><i class="bi bi-lock"></i> Locked</span>@endif</h5>
<div class="card shadow-sm"><div class="card-body">
<form method="POST" action="{{ route('workflow.flow-edge.update', [$version->idtblflow_version, $item->idtblflow_transition]) }}">@csrf @method('PUT')
@include('workflow.flow_edge._form')
</form></div></div>
@endsection
