<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unit organisasi (departemen, divisi, sales office, dst).
 * Self-referencing parent untuk hierarki organisasi.
 *
 * Tabel : tblorg_unit
 * PK    : idtblorg_unit
 */
class TblOrgUnit extends Model
{
    protected $table      = 'tblorg_unit';
    protected $primaryKey = 'idtblorg_unit';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'org_code',
        'org_name',
        'idtblorg_unit_parent',
        'is_active',
    ];

    protected $casts = [
        'idtblorg_unit_parent' => 'integer',
        'is_active'            => 'boolean',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            TblOrgUnit::class,
            'idtblorg_unit_parent',
            'idtblorg_unit'
        );
    }

    public function children(): HasMany
    {
        return $this->hasMany(
            TblOrgUnit::class,
            'idtblorg_unit_parent',
            'idtblorg_unit'
        );
    }

    public function users(): HasMany
    {
        return $this->hasMany(TblUser::class, 'idtblorg_unit', 'idtblorg_unit');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(TblPosition::class, 'idtblorg_unit', 'idtblorg_unit');
    }
}
