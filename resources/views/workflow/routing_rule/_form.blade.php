@include('partials._errors')
<div class="row g-3">
    <div class="col-md-4"><label class="form-label">Rule Code <span class="text-danger">*</span></label>
        <input type="text" name="rule_code" required maxlength="80" class="form-control"
               value="{{ old('rule_code', $item->rule_code ?? '') }}" {{ isset($item)?'readonly':'' }}></div>
    <div class="col-md-8"><label class="form-label">Rule Name <span class="text-danger">*</span></label>
        <input type="text" name="rule_name" required maxlength="180" class="form-control"
               value="{{ old('rule_name', $item->rule_name ?? '') }}"></div>
    <div class="col-md-4"><label class="form-label">Source App <span class="text-danger">*</span></label>
        <select name="idtblsource_app" class="form-select" required>
            <option value="">-- pilih --</option>
            @foreach ($sourceApps as $sa)<option value="{{ $sa->idtblsource_app }}" {{ (string)old('idtblsource_app',$item->idtblsource_app??'')===(string)$sa->idtblsource_app?'selected':'' }}>{{ $sa->app_code }}</option>@endforeach
        </select></div>
    <div class="col-md-4"><label class="form-label">Document Type <span class="text-danger">*</span></label>
        <select name="idtbldocument_type" class="form-select" required>
            <option value="">-- pilih --</option>
            @foreach ($documentTypes as $d)<option value="{{ $d->idtbldocument_type }}" {{ (string)old('idtbldocument_type',$item->idtbldocument_type??'')===(string)$d->idtbldocument_type?'selected':'' }}>{{ optional($d->sourceApp)->app_code }}/{{ $d->doc_code }}</option>@endforeach
        </select></div>
    <div class="col-md-4"><label class="form-label">Priority No <span class="text-danger">*</span></label>
        <input type="number" name="priority_no" required min="0" class="form-control"
               value="{{ old('priority_no', $item->priority_no ?? 100) }}">
        <small class="text-muted">Kecil = dievaluasi duluan.</small></div>
    <div class="col-md-6"><label class="form-label">Flow Definition <span class="text-danger">*</span></label>
        <select name="idtblflow_definition" class="form-select" required>
            <option value="">-- pilih --</option>
            @foreach ($flowDefinitions as $fd)<option value="{{ $fd->idtblflow_definition }}" {{ (string)old('idtblflow_definition',$item->idtblflow_definition??'')===(string)$fd->idtblflow_definition?'selected':'' }}>{{ $fd->flow_code }}</option>@endforeach
        </select></div>
    <div class="col-md-6"><label class="form-label">Flow Version Override (opsional)</label>
        <select name="idtblflow_version" class="form-select">
            <option value="">-- AUTO: ambil version ACTIVE terbaru --</option>
            @foreach ($flowVersions as $fv)<option value="{{ $fv->idtblflow_version }}" {{ (string)old('idtblflow_version',$item->idtblflow_version??'')===(string)$fv->idtblflow_version?'selected':'' }}>{{ optional($fv->flowDefinition??null)->flow_code??'' }} v{{ $fv->version_no }}</option>@endforeach
        </select></div>
    <div class="col-12"><label class="form-label">condition_json (kapan rule ini berlaku)</label>
        <textarea name="condition_json_raw" class="form-control font-monospace small" rows="5"
            placeholder='Kosong = selalu match (catch-all / default rule)&#10;{"op":"=","field":"doc_subtype","value":"RETUR_PRODUK"}&#10;{"logic":"AND","conditions":[{"op":">","field":"total_nilai","value":5000000}]}'>{{ old('condition_json_raw', isset($item) && $item->condition_json ? json_encode($item->condition_json, JSON_PRETTY_PRINT) : '') }}</textarea>
        <small class="text-muted">Evaluasi terhadap context_json yang dikirim saat submit.</small></div>
    <div class="col-md-6"><div class="form-check"><input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" id="ia" class="form-check-input"
               {{ old('is_active',$item->is_active??true)?'checked':'' }}>
        <label for="ia" class="form-check-label">Aktif</label></div></div>
</div>
<hr><div class="d-flex justify-content-end">
    <a href="{{ route('workflow.routing-rule.index') }}" class="btn btn-light me-2">Batal</a>
    <button class="btn btn-primary">Simpan</button>
</div>
