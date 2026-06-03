@include('partials._errors')
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Delegator (yang mendelegasikan) <span class="text-danger">*</span></label>
        <select name="idtbluser_delegator" class="form-select" required>
            <option value="">-- pilih --</option>
            @foreach ($users as $u)
                <option value="{{ $u->idtbluser }}"
                    {{ (string) old('idtbluser_delegator', $item->idtbluser_delegator ?? '') === (string) $u->idtbluser ? 'selected' : '' }}>
                    {{ $u->user_ref }} — {{ $u->full_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Delegate (penerima) <span class="text-danger">*</span></label>
        <select name="idtbluser_delegate" class="form-select" required>
            <option value="">-- pilih --</option>
            @foreach ($users as $u)
                <option value="{{ $u->idtbluser }}"
                    {{ (string) old('idtbluser_delegate', $item->idtbluser_delegate ?? '') === (string) $u->idtbluser ? 'selected' : '' }}>
                    {{ $u->user_ref }} — {{ $u->full_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Source App (opsional, kosong = ALL)</label>
        <select name="idtblsource_app" class="form-select">
            <option value="">-- ALL --</option>
            @foreach ($sourceApps as $sa)
                <option value="{{ $sa->idtblsource_app }}"
                    {{ (string) old('idtblsource_app', $item->idtblsource_app ?? '') === (string) $sa->idtblsource_app ? 'selected' : '' }}>
                    {{ $sa->app_code }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Document Type (opsional, kosong = ALL)</label>
        <select name="idtbldocument_type" class="form-select">
            <option value="">-- ALL --</option>
            @foreach ($documentTypes as $d)
                <option value="{{ $d->idtbldocument_type }}"
                    {{ (string) old('idtbldocument_type', $item->idtbldocument_type ?? '') === (string) $d->idtbldocument_type ? 'selected' : '' }}>
                    {{ optional($d->sourceApp)->app_code }} / {{ $d->doc_code }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Start At <span class="text-danger">*</span></label>
        <input type="datetime-local" name="start_at" class="form-control" required
               value="{{ old('start_at', optional($item->start_at ?? null)->format('Y-m-d\TH:i')) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">End At <span class="text-danger">*</span></label>
        <input type="datetime-local" name="end_at" class="form-control" required
               value="{{ old('end_at', optional($item->end_at ?? null)->format('Y-m-d\TH:i')) }}">
    </div>
    <div class="col-12">
        <label class="form-label">Reason</label>
        <textarea name="reason" class="form-control" rows="2">{{ old('reason', $item->reason ?? '') }}</textarea>
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
    <a href="{{ route('master.delegation.index') }}" class="btn btn-light me-2">Batal</a>
    <button class="btn btn-primary">Simpan</button>
</div>
