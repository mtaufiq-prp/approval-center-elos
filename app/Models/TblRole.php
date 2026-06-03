<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Role / peran user dalam sistem.
 *
 * Tabel : tblrole
 * PK    : idtblrole
 *
 * 4 role utama (seed di RoleSeeder):
 * - ADMIN_APPROVAL
 * - APPROVER
 * - REQUESTER
 * - AUDITOR
 */
class TblRole extends Model
{
    protected $table      = 'tblrole';
    protected $primaryKey = 'idtblrole';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'role_code',
        'role_name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            TblUser::class,
            'tbluser_role',
            'idtblrole',
            'idtbluser',
            'idtblrole',
            'idtbluser'
        );
    }

    public function tasksAssigned(): HasMany
    {
        return $this->hasMany(TblTask::class, 'idtblrole_assigned', 'idtblrole');
    }

    public function slaEscalationsAsTarget(): HasMany
    {
        return $this->hasMany(TblSlaEscalationLog::class, 'idtblrole_escalated_to', 'idtblrole');
    }
}
