@extends('layouts.workflow')
@section('title','Tambah Node')
@section('workflow_content')
<h5 class="mb-3">Tambah Flow Node — v{{ $version->version_no }}</h5>
<div class="card shadow-sm"><div class="card-body">
<form method="POST" action="{{ route('workflow.flow-node.store', $version->idtblflow_version) }}">@csrf
@include('workflow.flow_node._form', ['isLocked' => false])
</form></div></div>
@endsection
