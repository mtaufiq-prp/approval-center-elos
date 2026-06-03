@extends('layouts.workflow')
@section('title','Tambah Assignee Rule')
@section('workflow_content')
<h5 class="mb-3"><i class="bi bi-person-gear"></i> Tambah Assignee Rule — Node: {{ $node->node_code }}</h5>
<div class="card shadow-sm"><div class="card-body">
<form method="POST" action="{{ route('workflow.assignee-rule.store', [$version->idtblflow_version, $node->idtblflow_step]) }}">@csrf
@include('workflow.assignee_rule._form', ['isLocked'=>false,'item'=>null])
</form></div></div>
@endsection
