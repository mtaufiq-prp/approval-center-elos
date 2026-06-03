@extends('layouts.master')
@section('title', 'Edit API Client')

@section('master_content')
<h5 class="mb-3"><i class="bi bi-key"></i> Edit API Client: {{ $item->client_key }}</h5>
<div class="card shadow-sm"><div class="card-body">
    <form method="POST" action="{{ route('master.api-client.update', $item->idtblapi_client) }}">
        @csrf
        @method('PUT')
        @include('master.api_client._form')
    </form>
</div></div>
@endsection
