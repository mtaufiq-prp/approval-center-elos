@include('partials._errors')
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Flow Code <span class="text-danger">*</span></label>
        <input type="text" name="flow_code" required maxlength="80" class="form-control"
               value="{{ old('flow_code', $item->flow_code ?? '') }}" {{ isset($item)?'readonly':'' }}>
    </div>
    <div class="col-md-8">
        <label class="form-label">Flow Name <span class="text-danger">*</span></label>
        <input type="text" name="flow_name" required maxlength="180" class="form-control"
               value="{{ old('flow_name', $item->flow_name ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Source App <span class="text-danger">*</span></label>
        <select name="idtblsource_app" class="form-select" required {{ isset($item)?'disabled':'' }}>
            <option value="">-- pilih --</option>
            @foreach ($sourceApps as $sa)
                <option value="{{ $sa->idtblsource_app }}"
                    {{ (string)old('idtblsource_app',$item->idtblsource_app??'')===(string)$sa->idtblsource_app?'selected':'' }}>
                    {{ $sa->app_code }} &mdash; {{ $sa->app_name }}</option>
            @endforeach
        </select>
        @if(isset($item))<input type="hidden" name="idtblsource_app" value="{{ $item->idtblsource_app }}">@endif
    </div>
    <div class="col-md-6">
        <label class="form-label">Document Type <span class="text-danger">*</span></label>
        <select name="idtbldocument_type" class="form-select" required {{ isset($item)?'disabled':'' }}>
            <option value="">-- pilih --</option>
            @foreach ($documentTypes as $d)
                <option value="{{ $d->idtbldocument_type }}"
                    {{ (string)old('idtbldocument_type',$item->idtbldocument_type??'')===(string)$d->idtbldocument_type?'selected':'' }}>
                    {{ optional($d->sourceApp)->app_code }} / {{ $d->doc_code }}</option>
            @endforeach
        </select>
        @if(isset($item))<input type="hidden" name="idtbldocument_type" value="{{ $item->idtbldocument_type }}">@endif
    </div>
    <div class="col-12"><label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2">{{ old('description', $item->description ?? '') }}</textarea></div>
    <div class="col-md-6"><div class="form-check">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" id="ia" class="form-check-input"
               {{ old('is_active',$item->is_active??true)?'checked':'' }}>
        <label for="ia" class="form-check-label">Aktif</label>
    </div></div>
</div>
<hr><div class="d-flex justify-content-end">
    <a href="{{ route('workflow.flow-definition.index') }}" class="btn btn-light me-2">Batal</a>
    <button class="btn btn-primary">Simpan</button>
</div>
