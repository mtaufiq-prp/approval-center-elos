<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anggota approval group.
 *
 * Tabel : tblapproval_group_member
 * PK    : idtblapproval_group_member
 *
 * priority_no: untuk approval_mode SEQUENTIAL (urutan giliran approve).
 */
class TblApprovalGroupMember extends Model
{
    protected $table      = 'tblapproval_group_member';
    protected $primaryKey = 'idtblapproval_group_member';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'idtblapproval_group',
        'idtbluser',
        'priority_no',
        'is_active',
    ];

    protected $casts = [
        'idtblapproval_group' => 'integer',
        'idtbluser'           => 'integer',
        'priority_no'         => 'integer',
        'is_active'           => 'boolean',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(
            TblApprovalGroup::class,
            'idtblapproval_group',
            'idtblapproval_group'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser', 'idtbluser');
    }
}
