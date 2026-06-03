@extends('layouts.master')
@section('title', 'Edit Document Type')
@section('master_content')
<h5 class="mb-3"><i class="bi bi-file-earmark-text"></i> Edit Document Type: {{ $item->doc_code }}</h5>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.document-type.update', $item->idtbldocument_type) }}">@csrf @method('PUT') @include('master.document_type._form')</form>
</div></div>
@endsection
