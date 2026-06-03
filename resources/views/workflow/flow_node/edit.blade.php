@extends('layouts.workflow')
@section('title','Edit Node')
@section('workflow_content')
<h5 class="mb-3">Edit Node: {{ $item->node_code }} @if($isLocked)<span class="badge bg-warning text-dark ms-2"><i class="bi bi-lock"></i> Locked</span>@endif</h5>
<div class="card shadow-sm"><div class="card-body">
<form method="POST" action="{{ route('workflow.flow-node.update', [$version->idtblflow_version, $item->idtblflow_step]) }}">@csrf @method('PUT')
@include('workflow.flow_node._form')
</form></div></div>
@endsection
