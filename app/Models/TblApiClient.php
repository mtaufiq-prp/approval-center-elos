<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credential API untuk aplikasi sumber.
 *
 * Tabel : tblapi_client
 * PK    : idtblapi_client
 *
 * PENTING:
 * - Kolom `client_secret_hash` menyimpan AES-encrypted secret (bukan hash
 *   one-way) agar server bisa decrypt untuk verifikasi HMAC SHA256.
 * - Di-hidden dari serialisasi & TIDAK fillable; pengisian/rotasi
 *   melalui service khusus (akan dibuat di Tahap 7 / Tahap 5 admin UI).
 * - Jangan pernah expose ke UI, log, response API, atau audit trail.
 */
class TblApiClient extends Model
{
    protected $table      = 'tblapi_client';
    protected $primaryKey = 'idtblapi_client';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    /**
     * Catatan: client_secret_hash sengaja TIDAK fillable. Pengisian secret
     * harus melalui service yang melakukan enkripsi AES via APP_KEY.
     * Mass-assignment via $fillable bisa berakibat secret plaintext
     * tersimpan di DB jika developer lupa enkrip dulu.
     */
    protected $fillable = [
        'idtblsource_app',
        'client_key',
        'allowed_ip',
        'token_expired_at',
        'last_used_at',
        'is_active',
    ];

    /**
     * Sembunyikan dari serialisasi (toArray / toJson / response API).
     */
    protected $hidden = [
        'client_secret_hash',
        'secret_rotated_at',
    ];

    protected $casts = [
        'idtblsource_app'   => 'integer',
        'is_active'         => 'boolean',
        'token_expired_at'  => 'datetime',
        'last_used_at'      => 'datetime',
        'secret_rotated_at' => 'datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function sourceApp(): BelongsTo
    {
        return $this->belongsTo(TblSourceApp::class, 'idtblsource_app', 'idtblsource_app');
    }
}
