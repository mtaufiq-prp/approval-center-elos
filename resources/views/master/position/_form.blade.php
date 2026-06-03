@include('partials._errors')
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Position Code <span class="text-danger">*</span></label>
        <input type="text" name="position_code" required maxlength="50" class="form-control"
               value="{{ old('position_code', $item->position_code ?? '') }}"
               {{ isset($item) ? 'readonly' : '' }}>
    </div>
    <div class="col-md-8">
        <label class="form-label">Position Name <span class="text-danger">*</span></label>
        <input type="text" name="position_name" required maxlength="150" class="form-control"
               value="{{ old('position_name', $item->position_name ?? '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Level</label>
        <input type="number" name="level_no" min="0" max="50" class="form-control"
               value="{{ old('level_no', $item->level_no ?? '') }}">
        <small class="text-muted">Untuk SUPERIOR rule.</small>
    </div>
    <div class="col-md-6">
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
    <div class="col-md-3">
        <div class="form-check mt-4">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" id="ia" class="form-check-input"
                   {{ old('is_active', $item->is_active ?? true) ? 'checked' : '' }}>
            <label for="ia" class="form-check-label">Aktif</label>
        </div>
    </div>
</div>
<hr>
<div class="d-flex justify-content-end">
    <a href="{{ route('master.position.index') }}" class="btn btn-light me-2">Batal</a>
    <button class="btn btn-primary">Simpan</button>
</div>
