@include('partials._errors')
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">transition_code</label>
        <input type="text" name="transition_code" maxlength="100" class="form-control text-uppercase"
               value="{{ old('transition_code', $item->transition_code ?? '') }}"
               {{ ($isLocked??false)?'readonly':'' }}
               placeholder="BMH_TO_RRM (auto jika kosong)">
    </div>
    <div class="col-md-4">
        <label class="form-label">transition_name</label>
        <input type="text" name="transition_name" maxlength="150" class="form-control"
               value="{{ old('transition_name', $item->transition_name ?? '') }}"
               {{ ($isLocked??false)?'readonly':'' }}>
    </div>
    <div class="col-md-4">
        <label class="form-label">transition_type</label>
        <select name="transition_type" class="form-select">
            @foreach (['NORMAL','DEFAULT','ERROR','TIMEOUT'] as $t)
                <option value="{{ $t }}" {{ old('transition_type',$item->transition_type??'NORMAL')===$t?'selected':'' }}>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">From Node <span class="text-danger">*</span></label>
        <select name="idtblflow_step_from" class="form-select" required {{ ($isLocked??false)?'disabled':'' }}>
            <option value="">-- pilih --</option>
            @foreach ($nodes as $n)
                <option value="{{ $n->idtblflow_step }}"
                    {{ (string)old('idtblflow_step_from',$item->idtblflow_step_from??'')===(string)$n->idtblflow_step?'selected':'' }}>
                    {{ $n->node_code }} ({{ $n->step_type }})
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">action_code <span class="text-danger">*</span></label>
        <input type="text" name="action_code" required maxlength="50" class="form-control"
               value="{{ old('action_code', $item->action_code ?? '') }}"
               {{ ($isLocked??false)?'readonly':'' }}
               placeholder="APPROVE / REJECT / SUBMIT / AUTO">
        <small class="text-muted">Bebas. Engine mencocokkan dengan action yang dipilih approver.</small>
    </div>
    <div class="col-md-4">
        <label class="form-label">To Node</label>
        <select name="idtblflow_step_to" class="form-select" {{ ($isLocked??false)?'disabled':'' }}>
            <option value="">-- [END virtual] --</option>
            @foreach ($nodes as $n)
                <option value="{{ $n->idtblflow_step }}"
                    {{ (string)old('idtblflow_step_to',$item->idtblflow_step_to??'')===(string)$n->idtblflow_step?'selected':'' }}>
                    {{ $n->node_code }} ({{ $n->step_type }})
                </option>
            @endforeach
        </select>
        <small class="text-muted">Kosong = END virtual. Disarankan pakai node END eksplisit.</small>
    </div>
    <div class="col-md-3">
        <label class="form-label">priority_no <span class="text-danger">*</span></label>
        <input type="number" name="priority_no" required min="0" class="form-control"
               value="{{ old('priority_no', $item->priority_no ?? 100) }}"
               {{ ($isLocked??false)?'readonly':'' }}>
        <small class="text-muted">Lebih kecil = lebih dulu dievaluasi (EXCLUSIVE gateway).</small>
    </div>
    <div class="col-md-3">
        <label class="form-label">final_status (opsional)</label>
        <input type="text" name="final_status" maxlength="50" class="form-control"
               value="{{ old('final_status', $item->final_status ?? '') }}"
               placeholder="APPROVED / REJECTED">
    </div>
    <div class="col-md-3">
        <div class="form-check mt-4">
            <input type="hidden" name="is_default" value="0">
            <input type="checkbox" name="is_default" value="1" id="is_default" class="form-check-input"
                   {{ old('is_default',$item->is_default??false)?'checked':'' }}
                   {{ ($isLocked??false)?'disabled':'' }}>
            <label for="is_default" class="form-check-label">Default transition?</label>
        </div>
        <small class="text-muted">Max 1 default per from_node + action_code.</small>
    </div>
    <div class="col-md-3">
        <div class="form-check mt-4">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" id="is_active_edge" class="form-check-input"
                   {{ old('is_active',$item->is_active??true)?'checked':'' }}>
            <label for="is_active_edge" class="form-check-label">Aktif</label>
        </div>
    </div>
    <div class="col-md-12">
        <label class="form-label">condition_json (penentu jalur utama)</label>
        <textarea name="condition_json_raw" class="form-control font-monospace small" rows="5"
            placeholder='null (kosong = always-true / default path)
{"op":"=","field":"kondisi_produk","value":"RUSAK"}
{"logic":"AND","conditions":[{"op":">","field":"nilai","value":10000000},{"op":"=","field":"kondisi_kemasan","value":"BOCOR"}]}'
            {{ ($isLocked??false)?'readonly':'' }}>{{ old('condition_json_raw', isset($item) && $item->condition_json ? json_encode($item->condition_json, JSON_PRETTY_PRINT) : '') }}</textarea>
        <small class="text-muted">Format: leaf {op,field,value} ATAU group {logic:AND|OR, conditions:[...]}. Operator: = != > >= < <= IN NOT_IN BETWEEN CONTAINS IS_NULL IS_NOT_NULL.</small>
    </div>
</div>
<hr><div class="d-flex justify-content-end">
    <a href="{{ route('workflow.flow-edge.index', $version->idtblflow_version) }}" class="btn btn-light me-2">Batal</a>
    @unless ($isLocked??false)
    <button class="btn btn-primary">Simpan Edge</button>
    @endunless
</div>
