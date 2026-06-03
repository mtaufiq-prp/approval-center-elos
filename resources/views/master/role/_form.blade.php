@include('partials._errors')
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Role Code <span class="text-danger">*</span></label>
        <input type="text" name="role_code" required maxlength="50" class="form-control text-uppercase"
               value="{{ old('role_code', $item->role_code ?? '') }}"
               {{ isset($item) && in_array($item->role_code, $protected ?? [], true) ? 'readonly' : '' }}>
        <small class="text-muted">A-Z, 0-9, underscore.</small>
    </div>
    <div class="col-md-8">
        <label class="form-label">Role Name <span class="text-danger">*</span></label>
        <input type="text" name="role_name" required maxlength="150" class="form-control"
               value="{{ old('role_name', $item->role_name ?? '') }}">
    </div>
    <div class="col-md-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2">{{ old('description', $item->description ?? '') }}</textarea>
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
<div class="d-flex justify-content-end">
    <a href="{{ route('master.role.index') }}" class="btn btn-light me-2">Batal</a>
    <button class="btn btn-primary">Simpan</button>
</div>
