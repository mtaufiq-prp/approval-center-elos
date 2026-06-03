@include('partials._errors')
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Org Code <span class="text-danger">*</span></label>
        <input type="text" name="org_code" required maxlength="50" class="form-control"
               value="{{ old('org_code', $item->org_code ?? '') }}"
               {{ isset($item) ? 'readonly' : '' }}>
    </div>
    <div class="col-md-8">
        <label class="form-label">Org Name <span class="text-danger">*</span></label>
        <input type="text" name="org_name" required maxlength="150" class="form-control"
               value="{{ old('org_name', $item->org_name ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Parent</label>
        <select name="idtblorg_unit_parent" class="form-select">
            <option value="">-- root --</option>
            @foreach ($parents as $p)
                <option value="{{ $p->idtblorg_unit }}"
                    {{ (string) old('idtblorg_unit_parent', $item->idtblorg_unit_parent ?? '') === (string) $p->idtblorg_unit ? 'selected' : '' }}>
                    {{ $p->org_code }} — {{ $p->org_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
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
    <a href="{{ route('master.org-unit.index') }}" class="btn btn-light me-2">Batal</a>
    <button class="btn btn-primary">Simpan</button>
</div>
