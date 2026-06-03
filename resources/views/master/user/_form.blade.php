@include('partials._errors')
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">User Ref <span class="text-danger">*</span></label>
        <input type="text" name="user_ref" required maxlength="80" class="form-control"
               value="{{ old('user_ref', $item->user_ref ?? '') }}"
               {{ isset($item) ? 'readonly' : '' }}>
        <small class="text-muted">NPK / employee id. Tidak dapat diubah setelah dibuat.</small>
    </div>
    <div class="col-md-8">
        <label class="form-label">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="full_name" required maxlength="150" class="form-control"
               value="{{ old('full_name', $item->full_name ?? '') }}">
    </div>

    <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" maxlength="150" class="form-control"
               value="{{ old('email', $item->email ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" maxlength="50" class="form-control"
               value="{{ old('phone', $item->phone ?? '') }}">
    </div>

    <div class="col-md-4">
        <label class="form-label">Org Unit</label>
        <select name="idtblorg_unit" class="form-select">
            <option value="">-- pilih --</option>
            @foreach ($orgUnits as $o)
                <option value="{{ $o->idtblorg_unit }}"
                    {{ (string) old('idtblorg_unit', $item->idtblorg_unit ?? '') === (string) $o->idtblorg_unit ? 'selected' : '' }}>
                    {{ $o->org_code }} — {{ $o->org_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Position</label>
        <select name="idtblposition" class="form-select">
            <option value="">-- pilih --</option>
            @foreach ($positions as $p)
                <option value="{{ $p->idtblposition }}"
                    {{ (string) old('idtblposition', $item->idtblposition ?? '') === (string) $p->idtblposition ? 'selected' : '' }}>
                    {{ $p->position_code }} — {{ $p->position_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Atasan (Superior)</label>
        <select name="idtbluser_superior" class="form-select">
            <option value="">-- pilih --</option>
            @foreach ($superiors as $s)
                @continue (isset($item) && $s->idtbluser === $item->idtbluser)
                <option value="{{ $s->idtbluser }}"
                    {{ (string) old('idtbluser_superior', $item->idtbluser_superior ?? '') === (string) $s->idtbluser ? 'selected' : '' }}>
                    {{ $s->user_ref }} — {{ $s->full_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-12">
        <label class="form-label">Roles</label>
        <div class="row">
            @foreach ($roles as $r)
                @php
                    $assigned = collect(old('role_ids', isset($item) ? $item->roles->pluck('idtblrole')->toArray() : []))
                        ->map(fn($v) => (int) $v)->all();
                @endphp
                <div class="col-md-3">
                    <div class="form-check">
                        <input type="checkbox" name="role_ids[]" value="{{ $r->idtblrole }}"
                               id="role_{{ $r->idtblrole }}"
                               class="form-check-input"
                               {{ in_array($r->idtblrole, $assigned, true) ? 'checked' : '' }}>
                        <label for="role_{{ $r->idtblrole }}" class="form-check-label">
                            <code>{{ $r->role_code }}</code>
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" id="ia" class="form-check-input"
                   {{ old('is_active', $item->is_active ?? true) ? 'checked' : '' }}>
            <label for="ia" class="form-check-label">Aktif</label>
        </div>
    </div>
</div>
<hr>
<div class="d-flex justify-content-between">
    <div>
        @if (isset($item))
            <form method="POST" action="{{ route('master.user.reset-password', $item->idtbluser) }}"
                  class="d-inline" data-confirm="Reset password untuk {{ $item->user_ref }}?">
                @csrf
                <button class="btn btn-outline-warning"><i class="bi bi-key"></i> Reset Password</button>
            </form>
        @endif
    </div>
    <div>
        <a href="{{ route('master.user.index') }}" class="btn btn-light me-2">Batal</a>
        <button class="btn btn-primary">Simpan</button>
    </div>
</div>
