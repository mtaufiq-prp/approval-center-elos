@extends('layouts.master')
@section('title', 'API Client Secret')

@section('master_content')
<h5 class="mb-3"><i class="bi bi-shield-lock"></i> Client Secret — Tampil Sekali</h5>

<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>PENTING!</strong>
    Secret di bawah ini hanya ditampilkan SEKALI. Setelah Anda meninggalkan halaman ini,
    tidak ada cara untuk melihat secret yang sama lagi. Jika hilang, lakukan
    <strong>Rotate Secret</strong> untuk membuat secret baru.
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <p class="mb-1"><strong>Source App:</strong> {{ optional($item->sourceApp)->app_code }}</p>
        <p class="mb-3"><strong>Client Key:</strong> <code>{{ $client_key }}</code></p>

        <label class="form-label fw-semibold">Client Secret (plaintext)</label>
        <div class="input-group">
            <input type="text" id="secret-field" class="form-control font-monospace"
                   value="{{ $plain_secret }}" readonly>
            <button class="btn btn-outline-secondary" type="button"
                    onclick="navigator.clipboard.writeText(document.getElementById('secret-field').value)">
                <i class="bi bi-clipboard"></i> Copy
            </button>
        </div>

        <hr>
        <p class="small text-muted mb-2">Petunjuk integrasi:</p>
        <ul class="small text-muted">
            <li>Simpan <code>client_key</code> dan <code>client_secret</code> di environment aplikasi asal Anda.</li>
            <li>Setiap request ke <code>/api/v1/approval/*</code> wajib mengirim header
                <code>X-Client-Key</code>, <code>X-Timestamp</code>, dan
                <code>X-Signature</code>.</li>
            <li>Signature dihitung: <code>HMAC_SHA256(X-Timestamp + "\n" + raw_body, client_secret)</code>.</li>
        </ul>

        <a href="{{ route('master.api-client.index') }}" class="btn btn-primary mt-2">
            <i class="bi bi-check2"></i> Saya sudah menyimpan secret
        </a>
    </div>
</div>
@endsection
