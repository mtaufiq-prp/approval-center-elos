@extends('layouts.workflow')
@section('title','Edit Assignee Rule')
@section('workflow_content')
<h5 class="mb-3"><i class="bi bi-person-gear"></i> Edit Assignee Rule — Node: {{ $node->node_code }}</h5>
<div class="card shadow-sm"><div class="card-body">
<form method="POST" action="{{ route('workflow.assignee-rule.update', [$version->idtblflow_version, $node->idtblflow_step, $item->idtblstep_assignee_rule]) }}">@csrf @method('PUT')
@include('workflow.assignee_rule._form')
</form></div></div>
@endsection
