@extends('layouts.workflow')
@section('title','Edit Version')
@section('workflow_content')
<h5 class="mb-3">Edit Version v{{ $item->version_no }}</h5>
<div class="card shadow-sm"><div class="card-body">
<form method="POST" action="{{ route('workflow.flow-version.update', $item->idtblflow_version) }}">
@csrf @method('PUT') @include('partials._errors')
<div class="row g-3">
    <div class="col-md-3"><label class="form-label">Version No</label>
        <input type="number" name="version_no" class="form-control" required value="{{ old('version_no', $item->version_no) }}" {{ !$item->isDraft()?'readonly':'' }}></div>
    <div class="col-md-9"><label class="form-label">Version Name</label>
        <input type="text" name="version_name" class="form-control" required value="{{ old('version_name', $item->version_name) }}"></div>
    <div class="col-md-4"><label class="form-label">Effective Start</label>
        <input type="date" name="effective_start" class="form-control" value="{{ old('effective_start', optional($item->effective_start)->format('Y-m-d')) }}"></div>
    <div class="col-md-4"><label class="form-label">Effective End</label>
        <input type="date" name="effective_end" class="form-control" value="{{ old('effective_end', optional($item->effective_end)->format('Y-m-d')) }}"></div>
</div>
<hr><div class="d-flex justify-content-end">
    <a href="{{ route('workflow.flow-version.show', $item->idtblflow_version) }}" class="btn btn-light me-2">Batal</a>
    <button class="btn btn-primary">Simpan</button>
</div>
</form></div></div>
@endsection
