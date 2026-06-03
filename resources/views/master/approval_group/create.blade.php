@extends('layouts.master')
@section('title', 'Tambah Approval Group')
@section('master_content')
<h5 class="mb-3"><i class="bi bi-people-fill"></i> Tambah Approval Group</h5>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.approval-group.store') }}">@csrf
        @include('partials._errors')
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Group Code <span class="text-danger">*</span></label>
                <input type="text" name="group_code" required maxlength="50" class="form-control"
                       value="{{ old('group_code') }}">
            </div>
            <div class="col-md-8">
                <label class="form-label">Group Name <span class="text-danger">*</span></label>
                <input type="text" name="group_name" required maxlength="150" class="form-control"
                       value="{{ old('group_name') }}">
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
            </div>
        </div>
        <hr>
        <div class="d-flex justify-content-end">
            <a href="{{ route('master.approval-group.index') }}" class="btn btn-light me-2">Batal</a>
            <button class="btn btn-primary">Simpan & Lanjut Tambah Member</button>
        </div>
    </form>
</div></div>
@endsection
