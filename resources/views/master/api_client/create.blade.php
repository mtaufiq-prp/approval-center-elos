@extends('layouts.master')
@section('title', 'Tambah API Client')

@section('master_content')
<h5 class="mb-3"><i class="bi bi-key"></i> Tambah API Client</h5>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.api-client.store') }}">
        @csrf
        @include('master.api_client._form')
    </form>
</div></div>
@endsection
