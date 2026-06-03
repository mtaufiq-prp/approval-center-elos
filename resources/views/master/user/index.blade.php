@extends('layouts.master')
@section('title', 'User')
@section('master_content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-people"></i> User</h5>
    <a href="{{ route('master.user.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Tambah</a>
</div>
<div class="card shadow-sm"><div class="card-body">
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4"><input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Cari user_ref / nama / email..."></div>
        <div class="col-md-3"><select name="role_id" class="form-select form-select-sm">
            <option value="">Semua role</option>
            @foreach ($roles as $r) <option value="{{ $r->idtblrole }}" {{ (string)request('role_id') === (string)$r->idtblrole ? 'selected' : '' }}>{{ $r->role_code }}</option>@endforeach
        </select></div>
        <div class="col-md-3"><select name="is_active" class="form-select form-select-sm">
            <option value="all">Semua status</option>
            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Aktif</option>
            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Nonaktif</option>
        </select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Filter</button></div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light small"><tr>
                <th>User Ref</th><th>Nama</th><th>Email</th><th>Org</th><th>Posisi</th><th>Roles</th><th>Status</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody class="small">
            @forelse ($items as $item)
                <tr>
                    <td><code>{{ $item->user_ref }}</code></td>
                    <td>{{ $item->full_name }}</td>
                    <td class="text-muted">{{ $item->email ?: '-' }}</td>
                    <td class="text-muted">{{ optional($item->orgUnit)->org_code }}</td>
                    <td class="text-muted">{{ optional($item->position)->position_code }}</td>
                    <td>
                        @foreach ($item->roles as $r)
                            <span class="badge bg-primary">{{ $r->role_code }}</span>
                        @endforeach
                    </td>
                    <td>
                        @if ($item->is_active) <span class="badge bg-success">Aktif</span>
                        @else <span class="badge bg-secondary">Nonaktif</span> @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('master.user.edit', $item->idtbluser) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="POST" action="{{ route('master.user.destroy', $item->idtbluser) }}"
                              class="d-inline" data-confirm="Yakin {{ $item->is_active ? 'nonaktifkan' : 'aktifkan' }} user ini?">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-{{ $item->is_active ? 'danger' : 'success' }}">
                                {{ $item->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @empty <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-end">{{ $items->links() }}</div>
</div></div>
@endsection
