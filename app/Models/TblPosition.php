<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jabatan / posisi pekerjaan.
 * Self-referencing parent untuk hierarki jabatan.
 *
 * Tabel : tblposition
 * PK    : idtblposition
 *
 * Dipakai oleh AssigneeResolver tipe POSITION dan FIELD_POSITION.
 */
class TblPosition extends Model
{
    protected $table      = 'tblposition';
    protected $primaryKey = 'idtblposition';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'position_code',
        'position_name',
        'level_no',
        'idtblorg_unit',
        'idtblposition_parent',
        'is_active',
    ];

    protected $casts = [
        'level_no'             => 'integer',
        'idtblorg_unit'        => 'integer',
        'idtblposition_parent' => 'integer',
        'is_active'            => 'boolean',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(TblOrgUnit::class, 'idtblorg_unit', 'idtblorg_unit');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            TblPosition::class,
            'idtblposition_parent',
            'idtblposition'
        );
    }

    public function children(): HasMany
    {
        return $this->hasMany(
            TblPosition::class,
            'idtblposition_parent',
            'idtblposition'
        );
    }

    public function users(): HasMany
    {
        return $this->hasMany(TblUser::class, 'idtblposition', 'idtblposition');
    }
}
