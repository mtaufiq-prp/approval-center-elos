<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Instance runtime dari sebuah flow approval.
 *
 * Tabel : tblprocess_instance
 * PK    : idtblprocess_instance
 *
 * 1 approval_request ⇄ 1 process_instance (uq_tbl_instance_request).
 *
 * instance_status: RUNNING, COMPLETED, REJECTED, CANCELLED, ERROR.
 *
 * idtblflow_step_current di-sync dengan
 * tblapproval_request.idtblflow_step_current setiap kali step berpindah.
 */
class TblProcessInstance extends Model
{
    protected $table      = 'tblprocess_instance';
    protected $primaryKey = 'idtblprocess_instance';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'idtblapproval_request',
        'idtblflow_version',
        'instance_status',
        'idtblflow_step_current',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'idtblapproval_request'  => 'integer',
        'idtblflow_version'      => 'integer',
        'idtblflow_step_current' => 'integer',
        'started_at'             => 'datetime',
        'ended_at'               => 'datetime',
        'created_at'             => 'datetime',
        'updated_at'             => 'datetime',
    ];

    public const STATUS_RUNNING   = 'RUNNING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_REJECTED  = 'REJECTED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_ERROR     = 'ERROR';

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

    public function flowVersion(): BelongsTo
    {
        return $this->belongsTo(TblFlowVersion::class, 'idtblflow_version', 'idtblflow_version');
    }

    public function flowStepCurrent(): BelongsTo
    {
        return $this->belongsTo(
            TblFlowStep::class,
            'idtblflow_step_current',
            'idtblflow_step'
        );
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TblTask::class, 'idtblprocess_instance', 'idtblprocess_instance');
    }

    // ---------------------------------------------------------------------
    // BPMN-lite relationships
    // ---------------------------------------------------------------------

    public function routeLogs(): HasMany
    {
        return $this->hasMany(
            TblProcessRouteLog::class,
            'idtblprocess_instance',
            'idtblprocess_instance'
        )->orderBy('created_at');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(
            TblProcessToken::class,
            'idtblprocess_instance',
            'idtblprocess_instance'
        );
    }

    public function activeTokens(): HasMany
    {
        return $this->tokens()->where('token_status', 'ACTIVE');
    }
}
