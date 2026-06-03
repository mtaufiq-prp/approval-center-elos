<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kandidat approver untuk sebuah task.
 *
 * Tabel : tbltask_candidate
 * PK    : idtbltask_candidate
 *
 * FK: task_id  → tbltask.idtbltask  (TIDAK pakai pola idtbltask)
 *     idtbluser → tbluser.idtbluser
 *
 * candidate_source: DIRECT, ROLE, GROUP, POSITION, SUPERIOR, DELEGATION,
 *                   API_RESOLVER
 *
 * Catatan: Tabel ini hanya punya created_at (append-only).
 */
class TblTaskCandidate extends Model
{
    protected $table      = 'tbltask_candidate';
    protected $primaryKey = 'idtbltask_candidate';
    public $incrementing  = true;
    protected $keyType    = 'int';

    public $timestamps  = true;
    const UPDATED_AT    = null;

    protected $fillable = [
        'task_id',
        'idtbluser',
        'candidate_source',
        'priority_no',
        'is_active',
    ];

    protected $casts = [
        'task_id'     => 'integer',
        'idtbluser'   => 'integer',
        'priority_no' => 'integer',
        'is_active'   => 'boolean',
        'created_at'  => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TblTask::class, 'task_id', 'idtbltask');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser', 'idtbluser');
    }
}
