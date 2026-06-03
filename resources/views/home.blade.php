@extends('layouts.app')
@section('title', 'Beranda')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="card-title">
                    Selamat datang, {{ auth()->user()->full_name ?? auth()->user()->user_ref }}
                </h4>
                <p class="text-muted small mb-3">
                    User Ref: <code>{{ auth()->user()->user_ref }}</code> ·
                    Email: <code>{{ auth()->user()->email ?? '-' }}</code>
                </p>

                <p class="mb-2">Role aktif:</p>
                <div class="mb-3">
                    @forelse (auth()->user()->roles as $role)
                        <span class="badge bg-primary me-1">{{ $role->role_code }}</span>
                    @empty
                        <span class="text-muted">Belum ada role yang di-assign.</span>
                    @endforelse
                </div>

                <hr>

                <div class="alert alert-info small mb-0">
                    <strong>Tahap 4 — Authentication ready.</strong>
                    Dashboard, Inbox, Master, Workflow, dan Audit akan menyusul
                    pada Tahap 5 (Master Data), Tahap 6 (Workflow Engine),
                    Tahap 7 (API Integration), dan Tahap 8 (UI Operasional).
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
