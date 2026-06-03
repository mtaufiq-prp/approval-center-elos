@extends('layouts.auth')
@section('title', 'Ganti Password')

@section('content')
<div class="card shadow-sm">
    <div class="card-body p-4">
        <h5 class="card-title mb-3">Ganti Password</h5>

        @if (auth()->user()->must_change_password)
            <div class="alert alert-warning small mb-3">
                <i class="bi bi-exclamation-triangle"></i>
                Anda wajib mengganti password sebelum dapat mengakses menu lain.
            </div>
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

        <form method="POST" action="{{ route('password.change') }}" autocomplete="off">
            @csrf

            <div class="mb-3">
                <label for="current_password" class="form-label">Password Saat Ini</label>
                <input type="password" class="form-control @error('current_password') is-invalid @enderror"
                       id="current_password" name="current_password" required autofocus>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password Baru</label>
                <input type="password" class="form-control @error('password') is-invalid @enderror"
                       id="password" name="password" required>
                <small class="text-muted">Minimal 8 karakter, mengandung huruf, angka, dan simbol.</small>
            </div>

            <div class="mb-3">
                <label for="password_confirmation" class="form-label">Konfirmasi Password Baru</label>
                <input type="password" class="form-control"
                       id="password_confirmation" name="password_confirmation" required>
            </div>

            <div class="d-flex justify-content-between">
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                </form>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2-circle"></i> Simpan
                </button>
            </div>
        </form>

        <hr>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-link p-0 small text-muted">
                <i class="bi bi-box-arrow-right"></i> Logout
            </button>
        </form>
    </div>
</div>
@endsection
