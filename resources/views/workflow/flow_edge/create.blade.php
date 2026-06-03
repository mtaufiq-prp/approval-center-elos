@extends('layouts.workflow')
@section('title','Tambah Edge')
@section('workflow_content')
<h5 class="mb-3"><i class="bi bi-arrow-right-circle"></i> Tambah Flow Edge — v{{ $version->version_no }}</h5>
<div class="card shadow-sm"><div class="card-body">
<form method="POST" action="{{ route('workflow.flow-edge.store', $version->idtblflow_version) }}">@csrf
@include('workflow.flow_edge._form', ['isLocked' => false])
</form></div></div>
@endsection
