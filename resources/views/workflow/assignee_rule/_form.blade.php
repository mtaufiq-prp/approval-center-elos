@include('partials._errors')
<div class="alert alert-info small"><i class="bi bi-info-circle"></i>
    assignee_value bergantung assignee_type:<br>
    USER=user_ref | ROLE=role_code | GROUP=group_code | POSITION=position_code |
    SUPERIOR=(kosong) | FIELD_USER=nama field di context_json | FIELD_POSITION=nama field | API_RESOLVER=endpoint_url
</div>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">assignee_type <span class="text-danger">*</span></label>
        <select name="assignee_type" class="form-select" required {{ ($isLocked??false)?'disabled':'' }}>
            @foreach ($types as $t)
                <option value="{{ $t }}" {{ old('assignee_type',$item->assignee_type??'')===$t?'selected':'' }}>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">assignee_value</label>
        <input type="text" name="assignee_value" maxlength="150" class="form-control"
               value="{{ old('assignee_value', $item->assignee_value ?? '') }}"
               {{ ($isLocked??false)?'readonly':'' }}>
    </div>
    <div class="col-md-2">
        <label class="form-label">priority_no <span class="text-danger">*</span></label>
        <input type="number" name="priority_no" required min="0" class="form-control"
               value="{{ old('priority_no', $item->priority_no ?? 1) }}">
    </div>
    <div class="col-md-2">
        <div class="form-check mt-4">
            <input type="hidden" name="is_required" value="0">
            <input type="checkbox" name="is_required" value="1" id="ir" class="form-check-input"
                   {{ old('is_required',$item->is_required??true)?'checked':'' }}>
            <label for="ir" class="form-check-label">Required</label>
        </div>
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" id="ia" class="form-check-input"
                   {{ old('is_active',$item->is_active??true)?'checked':'' }}>
            <label for="ia" class="form-check-label">Aktif</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">condition_json (kapan rule ini berlaku, opsional)</label>
        <textarea name="condition_json_raw" class="form-control font-monospace small" rows="3">{{ old('condition_json_raw', isset($item) && $item->condition_json ? json_encode($item->condition_json, JSON_PRETTY_PRINT) : '') }}</textarea>
    </div>
</div>
<hr><div class="d-flex justify-content-end">
    <a href="{{ route('workflow.flow-node.edit', [$version->idtblflow_version, $node->idtblflow_step]) }}" class="btn btn-light me-2">Batal</a>
    <button class="btn btn-primary">Simpan Rule</button>
</div>
