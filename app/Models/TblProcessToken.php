<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Token runtime engine BPMN-lite.
 *
 * Tabel : tblprocess_token
 * PK    : idtblprocess_token
 *
 * Fase awal: 1 token utama per process_instance.
 * Fase lanjut: INCLUSIVE/PARALLEL akan menggunakan multiple token
 *              (parent-child) untuk track cabang yang berjalan paralel.
 *
 * token_status: ACTIVE, WAITING, COMPLETED, CANCELLED, ERROR
 */
class TblProcessToken extends Model
{
    protected $table      = 'tblprocess_token';
    protected $primaryKey = 'idtblprocess_token';
    public $incrementing  = true;
    protected $keyType    = 'int';

    public $timestamps  = true;
    const UPDATED_AT    = null;

    protected $fillable = [
        'idtblprocess_instance',
        'idtblapproval_request',
        'idtblflow_step_current',
        'idtblprocess_token_parent',
        'token_key',
        'token_status',
        'branch_key',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'idtblprocess_instance'      => 'integer',
        'idtblapproval_request'      => 'integer',
        'idtblflow_step_current'     => 'integer',
        'idtblprocess_token_parent'  => 'integer',
        'created_at'                 => 'datetime',
        'completed_at'               => 'datetime',
        'cancelled_at'               => 'datetime',
    ];

    public const STATUS_ACTIVE    = 'ACTIVE';
    public const STATUS_WAITING   = 'WAITING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_ERROR     = 'ERROR';

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(
            TblProcessInstance::class,
            'idtblprocess_instance',
            'idtblprocess_instance'
        );
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(
            TblApprovalRequest::class,
            'idtblapproval_request',
            'idtblapproval_request'
        );
    }

    public function flowStepCurrent(): BelongsTo
    {
        return $this->belongsTo(
            TblFlowStep::class,
            'idtblflow_step_current',
            'idtblflow_step'
        );
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            TblProcessToken::class,
            'idtblprocess_token_parent',
            'idtblprocess_token'
        );
    }

    public function children(): HasMany
    {
        return $this->hasMany(
            TblProcessToken::class,
            'idtblprocess_token_parent',
            'idtblprocess_token'
        );
    }
}
