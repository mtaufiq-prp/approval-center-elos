@extends('layouts.workflow')
@section('title','New Version')
@section('workflow_content')
<h5 class="mb-3"><i class="bi bi-layers"></i> Buat Version Baru — {{ $definition->flow_name }}</h5>
<div class="card shadow-sm"><div class="card-body">
<form method="POST" action="{{ route('workflow.flow-version.store', $definition->idtblflow_definition) }}">
@csrf @include('partials._errors')
<div class="row g-3">
    <div class="col-md-3"><label class="form-label">Version No <span class="text-danger">*</span></label>
        <input type="number" name="version_no" class="form-control" required value="{{ old('version_no', $nextVersion) }}"></div>
    <div class="col-md-9"><label class="form-label">Version Name <span class="text-danger">*</span></label>
        <input type="text" name="version_name" class="form-control" required maxlength="120" value="{{ old('version_name','v'.$nextVersion.' Initial') }}"></div>
    <div class="col-md-4"><label class="form-label">Effective Start</label>
        <input type="date" name="effective_start" class="form-control" value="{{ old('effective_start') }}"></div>
    <div class="col-md-4"><label class="form-label">Effective End</label>
        <input type="date" name="effective_end" class="form-control" value="{{ old('effective_end') }}"></div>
</div>
<hr><div class="d-flex justify-content-end">
    <a href="{{ route('workflow.flow-version.index', $definition->idtblflow_definition) }}" class="btn btn-light me-2">Batal</a>
    <button class="btn btn-primary">Simpan</button>
</div>
</form></div></div>
@endsection
