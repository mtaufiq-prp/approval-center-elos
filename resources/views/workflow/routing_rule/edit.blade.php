@extends('layouts.workflow')
@section('title','Edit Routing Rule')
@section('workflow_content')
<h5 class="mb-3"><i class="bi bi-signpost-split"></i> Edit: {{ $item->rule_code }}</h5>
<div class="card shadow-sm"><div class="card-body"><form method="POST" action="{{ route('workflow.routing-rule.update', $item->idtblrouting_rule) }}">@csrf @method('PUT') @include('workflow.routing_rule._form')</form></div></div>
@endsection
