@extends('layouts.master')
@section('title', 'Edit Approval Group')
@section('master_content')
<h5 class="mb-3"><i class="bi bi-people-fill"></i> Approval Group: {{ $item->group_code }}</h5>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-light small fw-semibold">Detail Group</div>
    <div class="card-body">
        <form method="POST" action="{{ route('master.approval-group.update', $item->idtblapproval_group) }}">
            @csrf @method('PUT')
            @include('partials._errors')
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Group Code</label>
                    <input type="text" name="group_code" required maxlength="50" class="form-control"
                           value="{{ old('group_code', $item->group_code) }}" readonly>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Group Name <span class="text-danger">*</span></label>
                    <input type="text" name="group_name" required maxlength="150" class="form-control"
                           value="{{ old('group_name', $item->group_name) }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description', $item->description) }}</textarea>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" id="ia" class="form-check-input"
                               {{ old('is_active', $item->is_active) ? 'checked' : '' }}>
                        <label for="ia" class="form-check-label">Aktif</label>
                    </div>
                </div>
            </div>
            <hr>
            <div class="d-flex justify-content-end">
                <button class="btn btn-primary">Simpan Group</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light small fw-semibold">Member Group ({{ $members->count() }})</div>
    <div class="card-body">
        <form method="POST" action="{{ route('master.approval-group.add-member', $item->idtblapproval_group) }}" class="row g-2 mb-3">
            @csrf
            <div class="col-md-7">
                <select name="idtbluser" class="form-select form-select-sm" required>
                    <option value="">-- pilih user --</option>
                    @foreach ($availableUsers as $u)
                        <option value="{{ $u->idtbluser }}">{{ $u->user_ref }} — {{ $u->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <input type="number" name="priority_no" min="0" class="form-control form-control-sm" placeholder="Priority" value="0">
            </div>
            <div class="col-md-2">
                <button class="btn btn-success btn-sm w-100"><i class="bi bi-plus-lg"></i> Add</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="table-light small"><tr>
                    <th>User Ref</th><th>Nama</th><th>Priority</th><th class="text-end">Aksi</th>
                </tr></thead>
                <tbody class="small">
                @forelse ($members as $m)
                    <tr>
                        <td><code>{{ optional($m->user)->user_ref }}</code></td>
                        <td>{{ optional($m->user)->full_name }}</td>
                        <td>{{ $m->priority_no }}</td>
                        <td class="text-end">
                            <form method="POST"
                                  action="{{ route('master.approval-group.remove-member', [$item->idtblapproval_group, $m->idtblapproval_group_member]) }}"
                                  class="d-inline" data-confirm="Keluarkan user ini dari group?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty <tr><td colspan="4" class="text-center text-muted py-3">Group belum punya member.</td></tr> @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
