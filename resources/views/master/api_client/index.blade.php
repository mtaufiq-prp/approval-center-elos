@extends('layouts.master')
@section('title', 'API Client')

@section('master_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-key"></i> API Client</h5>
    <a href="{{ route('master.api-client.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Tambah
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" value="{{ request('search') }}"
                       class="form-control form-control-sm" placeholder="Cari client_key / app_code...">
            </div>
            <div class="col-md-3">
                <select name="idtblsource_app" class="form-select form-select-sm">
                    <option value="">Semua source app</option>
                    @foreach ($sourceApps as $sa)
                        <option value="{{ $sa->idtblsource_app }}"
                            {{ (string) request('idtblsource_app') === (string) $sa->idtblsource_app ? 'selected' : '' }}>
                            {{ $sa->app_code }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="is_active" class="form-select form-select-sm">
                    <option value="all">Semua status</option>
                    <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Aktif</option>
                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Revoked</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Filter</button></div>
        </form>

        <div class="alert alert-info small mb-3">
            <i class="bi bi-info-circle"></i>
            Plaintext secret hanya ditampilkan satu kali saat dibuat / di-rotate.
            Jika lupa, gunakan tombol <strong>Rotate Secret</strong> untuk membuat secret baru.
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light small">
                    <tr>
                        <th>Source App</th>
                        <th>Client Key</th>
                        <th>Allowed IP</th>
                        <th>Last Used</th>
                        <th>Secret Rotated</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="small">
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ optional($item->sourceApp)->app_code }}</td>
                            <td><code class="small">{{ $item->client_key }}</code></td>
                            <td class="text-muted">{{ $item->allowed_ip ?: '-' }}</td>
                            <td class="text-muted">{{ optional($item->last_used_at)->format('Y-m-d H:i') ?? '-' }}</td>
                            <td class="text-muted">{{ optional($item->secret_rotated_at)->format('Y-m-d H:i') ?? '-' }}</td>
                            <td>
                                @if ($item->is_active)
                                    <span class="badge bg-success">Aktif</span>
                                @else
                                    <span class="badge bg-danger">Revoked</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('master.api-client.edit', $item->idtblapi_client) }}"
                                   class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('master.api-client.rotate', $item->idtblapi_client) }}"
                                      class="d-inline" data-confirm="Rotate secret untuk {{ $item->client_key }}? Secret lama tidak akan bisa dipakai lagi.">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-warning">Rotate</button>
                                </form>
                                <form method="POST" action="{{ route('master.api-client.destroy', $item->idtblapi_client) }}"
                                      class="d-inline" data-confirm="Yakin {{ $item->is_active ? 'revoke' : 'aktifkan kembali' }} {{ $item->client_key }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-{{ $item->is_active ? 'danger' : 'success' }}">
                                        {{ $item->is_active ? 'Revoke' : 'Activate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-end">{{ $items->links() }}</div>
    </div>
</div>
@endsection
