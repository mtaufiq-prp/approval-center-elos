@extends('layouts.workflow')
@section('title','Tambah Routing Rule')
@section('workflow_content')
<h5 class="mb-3"><i class="bi bi-signpost-split"></i> Tambah Routing Rule</h5>
<div class="card shadow-sm"><div class="card-body"><form method="POST" action="{{ route('workflow.routing-rule.store') }}">@csrf @include('workflow.routing_rule._form')</form></div></div>
@endsection
