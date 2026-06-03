<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot user <-> role.
 *
 * Tabel : tbluser_role
 * PK    : idtbluser_role
 *
 * Catatan: Tabel ini hanya punya created_at (tidak ada updated_at).
 */
class TblUserRole extends Model
{
    protected $table       = 'tbluser_role';
    protected $primaryKey  = 'idtbluser_role';
    public $incrementing   = true;
    protected $keyType     = 'int';

    /** Tabel append-only: hanya punya created_at */
    public $timestamps      = true;
    const UPDATED_AT        = null;

    protected $fillable = [
        'idtbluser',
        'idtblrole',
    ];

    protected $casts = [
        'idtbluser'  => 'integer',
        'idtblrole'  => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser', 'idtbluser');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(TblRole::class, 'idtblrole', 'idtblrole');
    }
}
