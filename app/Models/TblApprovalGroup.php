<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Group approval — kumpulan user yang berhak approve task tertentu.
 *
 * Tabel : tblapproval_group
 * PK    : idtblapproval_group
 *
 * Dipakai oleh AssigneeResolver tipe GROUP.
 */
class TblApprovalGroup extends Model
{
    protected $table      = 'tblapproval_group';
    protected $primaryKey = 'idtblapproval_group';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'group_code',
        'group_name',
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

    public function members(): HasMany
    {
        return $this->hasMany(
            TblApprovalGroupMember::class,
            'idtblapproval_group',
            'idtblapproval_group'
        );
    }

    /**
     * Member langsung sebagai TblUser (skip tabel pivot).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            TblUser::class,
            'tblapproval_group_member',
            'idtblapproval_group',
            'idtbluser',
            'idtblapproval_group',
            'idtbluser'
        )->wherePivot('is_active', 1);
    }

    public function tasksAssigned(): HasMany
    {
        return $this->hasMany(
            TblTask::class,
            'idtblapproval_group_assigned',
            'idtblapproval_group'
        );
    }
}
