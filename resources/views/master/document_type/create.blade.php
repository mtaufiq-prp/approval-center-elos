@extends('layouts.master')
@section('title', 'Tambah Document Type')
@section('master_content')
<h5 class="mb-3"><i class="bi bi-file-earmark-text"></i> Tambah Document Type</h5>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.document-type.store') }}">@csrf @include('master.document_type._form')</form>
</div></div>
@endsection
