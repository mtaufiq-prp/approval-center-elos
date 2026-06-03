<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Komentar / catatan pada approval request atau task.
 *
 * Tabel : tblcomment
 * PK    : idtblcomment
 *
 * Append-only: hanya created_at, tidak ada updated_at.
 *
 * comment_type:
 *   GENERAL, APPROVAL_NOTE, REJECT_REASON, RETURN_REASON, SYSTEM
 *
 * FK: task_id → tbltask.idtbltask (skema asli).
 */
class TblComment extends Model
{
    protected $table      = 'tblcomment';
    protected $primaryKey = 'idtblcomment';
    public $incrementing  = true;
    protected $keyType    = 'int';

    public $timestamps  = true;
    const UPDATED_AT    = null;

    protected $fillable = [
        'idtblapproval_request',
        'task_id',
        'idtbluser',
        'comment_type',
        'comment_text',
    ];

    protected $casts = [
        'idtblapproval_request' => 'integer',
        'task_id'               => 'integer',
        'idtbluser'             => 'integer',
        'created_at'            => 'datetime',
    ];

    public const TYPE_GENERAL       = 'GENERAL';
    public const TYPE_APPROVAL_NOTE = 'APPROVAL_NOTE';
    public const TYPE_REJECT_REASON = 'REJECT_REASON';
    public const TYPE_RETURN_REASON = 'RETURN_REASON';
    public const TYPE_SYSTEM        = 'SYSTEM';

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(
            TblApprovalRequest::class,
            'idtblapproval_request',
            'idtblapproval_request'
        );
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(TblTask::class, 'task_id', 'idtbltask');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser', 'idtbluser');
    }
}
