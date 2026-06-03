@include('partials._errors')

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Source App <span class="text-danger">*</span></label>
        <select name="idtblsource_app" class="form-select" required {{ isset($item) ? 'disabled' : '' }}>
            <option value="">-- pilih --</option>
            @foreach ($sourceApps as $sa)
                <option value="{{ $sa->idtblsource_app }}"
                    {{ (string) old('idtblsource_app', $item->idtblsource_app ?? '') === (string) $sa->idtblsource_app ? 'selected' : '' }}>
                    {{ $sa->app_code }} — {{ $sa->app_name }}
                </option>
            @endforeach
        </select>
        @if (isset($item))
            <input type="hidden" name="idtblsource_app" value="{{ $item->idtblsource_app }}">
            <small class="text-muted">Source App tidak dapat diubah setelah client dibuat.</small>
        @endif
    </div>

    <div class="col-md-6">
        <label class="form-label">Allowed IP</label>
        <input type="text" name="allowed_ip" class="form-control"
               value="{{ old('allowed_ip', $item->allowed_ip ?? '') }}"
               placeholder="mis. 10.10.0.0/16,192.168.1.5">
        <small class="text-muted">Pisahkan koma. Kosongkan untuk tidak membatasi.</small>
    </div>

    <div class="col-md-6">
        <label class="form-label">Token Expired At</label>
        <input type="datetime-local" name="token_expired_at" class="form-control"
               value="{{ old('token_expired_at', optional($item->token_expired_at ?? null)->format('Y-m-d\TH:i')) }}">
    </div>

    <div class="col-md-6">
        <div class="form-check mt-4">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" id="is_active" class="form-check-input"
                   {{ old('is_active', $item->is_active ?? true) ? 'checked' : '' }}>
            <label for="is_active" class="form-check-label">Aktif</label>
        </div>
    </div>

    @if (! isset($item))
        <div class="col-12">
            <div class="alert alert-warning small">
                <strong>Catatan:</strong> Saat client dibuat, sistem akan menghasilkan
                <code>client_key</code> dan <code>client_secret</code> baru.
                Secret hanya ditampilkan satu kali. Simpan baik-baik.
            </div>
        </div>
    @endif
</div>

<hr>
<div class="d-flex justify-content-end">
    <a href="{{ route('master.api-client.index') }}" class="btn btn-light me-2">Batal</a>
    <button class="btn btn-primary">Simpan</button>
</div>
