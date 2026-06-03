<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Approval Center') - Propan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background:#f4f6f9; min-height:100vh; display:flex; align-items:center; justify-content:center; }
        .auth-card { max-width:420px; width:100%; }
        .brand { text-align:center; margin-bottom:1.5rem; }
        .brand h1 { font-size:1.4rem; font-weight:600; margin:0; color:#1a3a5e; }
        .brand small { color:#6c757d; }
    </style>
</head>
<body>
<div class="container">
    <div class="auth-card mx-auto">
        <div class="brand">
            <h1><i class="bi bi-shield-check"></i> Approval Center</h1>
            <small>PT Propan Raya</small>
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="alert">{{ session('status') }}</div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
        @endif

        @yield('content')

        <p class="text-center text-muted small mt-4 mb-0">
            &copy; {{ date('Y') }} PT Propan Raya - Internal Use Only
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
