@extends('layouts.auth')
@section('title', 'Login')

@section('content')
<div class="card shadow-sm">
    <div class="card-body p-4">
        <h5 class="card-title mb-3">Masuk ke Approval Center</h5>

        @if (session('error'))
            <div class="alert alert-warning small">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 small">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" autocomplete="off">
            @csrf

            <div class="mb-3">
                <label for="login" class="form-label">User Ref / Email</label>
                <input type="text" class="form-control @error('login') is-invalid @enderror"
                       id="login" name="login" value="{{ old('login') }}"
                       required autofocus>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control @error('password') is-invalid @enderror"
                       id="password" name="password" required>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                <label class="form-check-label" for="remember">Ingat saya di perangkat ini</label>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-box-arrow-in-right"></i> Login
            </button>
        </form>
    </div>
</div>
@endsection
