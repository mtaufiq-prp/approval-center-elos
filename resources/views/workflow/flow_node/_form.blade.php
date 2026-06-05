@include('partials._errors')
<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">node_code <span class="text-danger">*</span></label>
        <input type="text" name="node_code" required maxlength="100" class="form-control text-uppercase"
               value="{{ old('node_code', $item->node_code ?? '') }}" {{ (isset($item) && ($isLocked??false))?'readonly':'' }}
               pattern="[A-Z0-9_]+" title="Huruf kapital, angka, underscore">
        <small class="text-muted">Unik per version. Contoh: START, BMH, DECISION_KONDISI</small>
    </div>
    <div class="col-md-3">
        <label class="form-label">step_type (Node Type) <span class="text-danger">*</span></label>
        <select name="step_type" class="form-select" id="step_type" {{ ($isLocked??false)?'disabled':'' }}>
            @foreach (['START','APPROVAL','DECISION','END','REVIEW','NOTIFICATION','SYSTEM'] as $t)
                <option value="{{ $t }}" {{ old('step_type',$item->step_type??'APPROVAL')===$t?'selected':'' }}>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">gateway_type</label>
        <select name="gateway_type" class="form-select" id="gateway_type" {{ ($isLocked??false)?'disabled':'' }}>
            @foreach (['NONE','EXCLUSIVE','INCLUSIVE','PARALLEL'] as $g)
                <option value="{{ $g }}" {{ old('gateway_type',$item->gateway_type??'NONE')===$g?'selected':'' }}>{{ $g }}</option>
            @endforeach
        </select>
        <small class="text-muted">Wajib non-NONE untuk DECISION. Non-DECISION wajib NONE.</small>
    </div>
    <div class="col-md-3">
        <label class="form-label">step_order</label>
        <input type="number" name="step_order" min="0" class="form-control" value="{{ old('step_order', $item->step_order ?? 10) }}">
        <small class="text-muted">Untuk sorting tampilan saja.</small>
    </div>
    <div class="col-md-12">
        <label class="form-label">step_name <span class="text-danger">*</span></label>
        <input type="text" name="step_name" required maxlength="180" class="form-control"
               value="{{ old('step_name', $item->step_name ?? '') }}" {{ ($isLocked??false)?'readonly':'' }}>
    </div>
    <div class="col-md-3">
        <label class="form-label">approval_mode</label>
        <select name="approval_mode" class="form-select">
            <option value="">-- N/A --</option>
            @foreach (['ANY'=>'ANY (salah satu)','ALL'=>'ALL (semua)','SEQUENTIAL'=>'SEQUENTIAL'] as $v=>$l)
                <option value="{{ $v }}" {{ old('approval_mode',$item->approval_mode??'ANY')===$v?'selected':'' }}>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">SLA Hours</label>
        <input type="number" name="sla_hours" min="0" class="form-control" value="{{ old('sla_hours', $item->sla_hours ?? '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">pos_x</label>
        <input type="number" name="pos_x" class="form-control" value="{{ old('pos_x', $item->pos_x ?? '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">pos_y</label>
        <input type="number" name="pos_y" class="form-control" value="{{ old('pos_y', $item->pos_y ?? '') }}">
    </div>
    <div class="col-md-12">
        <label class="form-label">instruction</label>
        <textarea name="instruction" class="form-control" rows="2">{{ old('instruction', $item->instruction ?? '') }}</textarea>
    </div>
    <div class="col-md-12">
        <label class="form-label">Field yang boleh diedit approver (editable_fields)</label>
        @php
            $editablePrefill = old('editable_fields_raw',
                isset($item) && is_array($item->node_config_json ?? null)
                    ? implode("\n", $item->node_config_json['editable_fields'] ?? [])
                    : '');
        @endphp
        <textarea name="editable_fields_raw" class="form-control font-monospace small" rows="3"
            placeholder="Satu path per baris (gaya form_schema), mis.:&#10;header.keterangan&#10;header.alamat_kirim">{{ $editablePrefill }}</textarea>
        <small class="text-muted">
            Approver di node ini boleh mengubah field tsb saat memproses task (hanya field NON-routing).
            Kosongkan = approver tidak boleh edit. Path mengikuti konvensi form_schema (mis. <code>header.keterangan</code>).
        </small>
    </div>
    <div class="col-md-12">
        @php
            $cbCfg     = isset($item) && is_array($item->node_config_json ?? null) ? $item->node_config_json : [];
            $cbOn      = (bool) old('callback_on_enter', $cbCfg['callback_on_enter'] ?? false);
            $cbEvent   = old('callback_event_code', $cbCfg['callback_event_code'] ?? '');
        @endphp
        <div class="form-check">
            {{-- hidden 0 agar uncheck terkirim (default checkbox tidak terkirim) --}}
            <input type="hidden" name="callback_on_enter" value="0">
            <input type="checkbox" class="form-check-input" id="cb_on_enter" name="callback_on_enter" value="1" {{ $cbOn ? 'checked' : '' }}>
            <label class="form-check-label" for="cb_on_enter">
                Kirim callback ke source app saat flow MASUK node ini
            </label>
        </div>
        <input type="text" name="callback_event_code" class="form-control form-control-sm mt-1"
               maxlength="80" placeholder="event_code (opsional, default = node_code), mis. STEP_BMH_REACHED"
               value="{{ $cbEvent }}">
        <small class="text-muted">
            Saat flow sampai di node ini, satu callback (event <code>TASK_CREATED</code>) dikirim ke
            <code>callback_url</code> source app dengan <code>event_code</code>, <code>node_code</code>, status,
            dan payload. Berguna bila source app harus "melakukan sesuatu" di step ini. Dikirim ulang tiap node dimasuki.
            <br><span class="text-warning"><i class="bi bi-exclamation-triangle"></i></span>
            Tidak berlaku di node START/END (state akhir sudah dicakup callback final). Mengaktifkan di banyak node
            menambah volume callback — sesuaikan kapasitas worker/<code>batch_size</code> pada beban tinggi.
        </small>
    </div>
    <div class="col-md-12">
        <label class="form-label">condition_json (filter node — Tahap 5: dipakai validator struktural saja)</label>
        <textarea name="condition_json_raw" class="form-control font-monospace small" rows="4"
            placeholder='null (kosong = always) atau {"op":"=","field":"status","value":"ACTIVE"}'
            {{ ($isLocked??false)?'readonly':'' }}>{{ old('condition_json_raw', isset($item) && $item->condition_json ? json_encode($item->condition_json, JSON_PRETTY_PRINT) : '') }}</textarea>
        <small class="text-muted">Catatan: condition di NODE bersifat guard/filter. Runtime engine prioritaskan condition di EDGE/Transition.</small>
    </div>
</div>

@if (isset($item) && $item->isApproval() && !($isLocked??false))
<hr>
<div class="d-flex justify-content-between align-items-center mb-2">
    <strong class="small">Assignee Rules</strong>
    <a href="{{ route('workflow.assignee-rule.create', [$version->idtblflow_version, $item->idtblflow_step]) }}" class="btn btn-sm btn-outline-success">+ Tambah Rule</a>
</div>
<div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead class="table-light small"><tr><th>Type</th><th>Value</th><th>Priority</th><th>Required</th><th>Active</th><th></th></tr></thead>
        <tbody class="small">
        @forelse ($item->activeAssigneeRules ?? [] as $r)
            <tr>
                <td><code>{{ $r->assignee_type }}</code></td>
                <td>{{ $r->assignee_value }}</td>
                <td>{{ $r->priority_no }}</td>
                <td>@if($r->is_required)<span class="badge bg-info">Required</span>@endif</td>
                <td>@if($r->is_active)<span class="badge bg-success">Aktif</span>@else<span class="badge bg-secondary">Nonaktif</span>@endif</td>
                <td>
                    <a href="{{ route('workflow.assignee-rule.edit', [$version->idtblflow_version, $item->idtblflow_step, $r->idtblstep_assignee_rule]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    <form method="POST" action="{{ route('workflow.assignee-rule.destroy', [$version->idtblflow_version, $item->idtblflow_step, $r->idtblstep_assignee_rule]) }}" class="d-inline" data-confirm="Hapus rule?">
                        @csrf @method('DELETE') <button class="btn btn-sm btn-outline-danger">Hapus</button>
                    </form>
                </td>
            </tr>
        @empty <tr><td colspan="6" class="text-muted text-center py-2">Belum ada assignee rule.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif

<hr>
<div class="d-flex justify-content-end">
    <a href="{{ route('workflow.flow-node.index', $version->idtblflow_version) }}" class="btn btn-light me-2">Batal</a>
    @unless ($isLocked??false)
    <button class="btn btn-primary">Simpan Node</button>
    @endunless
</div>

<script>
document.getElementById('step_type')?.addEventListener('change', function() {
    const gw = document.getElementById('gateway_type');
    if (!gw) return;
    if (this.value === 'DECISION') {
        if (gw.value === 'NONE') gw.value = 'EXCLUSIVE';
    } else {
        gw.value = 'NONE';
    }
});
</script>
