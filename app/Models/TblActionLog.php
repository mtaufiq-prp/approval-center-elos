<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Action log — histori aksi approval per request.
 *
 * Tabel : tblaction_log
 * PK    : idtblaction_log
 *
 * Append-only: hanya punya created_at, tidak ada updated_at.
 *
 * action_code (string bebas, tapi konsisten dengan service):
 *   SUBMIT, APPROVE, REJECT, RETURN, CANCEL, CLAIM, DELEGATE,
 *   AUTO_APPROVE, EXPIRE, RESEND_CALLBACK, dst.
 *
 * FK: task_id → tbltask.idtbltask (skema asli pakai task_id).
 */
class TblActionLog extends Model
{
    protected $table      = 'tblaction_log';
    protected $primaryKey = 'idtblaction_log';
    public $incrementing  = true;
    protected $keyType    = 'int';

    public $timestamps  = true;
    const UPDATED_AT    = null;

    protected $fillable = [
        'idtblapproval_request',
        'idtblprocess_instance',
        'task_id',
        'idtbluser_actor',
        'actor_ref',
        'action_code',
        'action_note',
        'before_status',
        'after_status',
        'idtblflow_step_before',
        'idtblflow_step_after',
        'client_ip',
        'user_agent',
    ];

    protected $casts = [
        'idtblapproval_request' => 'integer',
        'idtblprocess_instance' => 'integer',
        'task_id'               => 'integer',
        'idtbluser_actor'       => 'integer',
        'idtblflow_step_before' => 'integer',
        'idtblflow_step_after'  => 'integer',
        'created_at'            => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(
            TblApprovalRequest::class,
            'idtblapproval_request',
            'idtblapproval_request'
        );
    }

    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(
            TblProcessInstance::class,
            'idtblprocess_instance',
            'idtblprocess_instance'
        );
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(TblTask::class, 'task_id', 'idtbltask');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser_actor', 'idtbluser');
    }

    public function stepBefore(): BelongsTo
    {
        return $this->belongsTo(TblFlowStep::class, 'idtblflow_step_before', 'idtblflow_step');
    }

    public function stepAfter(): BelongsTo
    {
        return $this->belongsTo(TblFlowStep::class, 'idtblflow_step_after', 'idtblflow_step');
    }
}
