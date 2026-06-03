<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log eskalasi SLA — dicatat ketika task melewati due_at.
 *
 * Tabel : tblsla_escalation_log
 * PK    : idtblsla_escalation_log
 *
 * status: TRIGGERED, NOTIFIED, RESOLVED, CANCELLED
 *
 * Catatan: Tabel ini punya created_at + resolved_at, TIDAK punya
 * updated_at. Ditandai append-only.
 *
 * FK: task_id → tbltask.idtbltask (skema asli)
 *
 * Eskalasi TIDAK otomatis reject task; hanya melakukan notifikasi
 * ke target eskalasi (user atau role tertentu). Auto-reject hanya
 * dilakukan jika rule flow_transition action_code=TIMEOUT diset
 * eksplisit oleh admin.
 */
class TblSlaEscalationLog extends Model
{
    protected $table      = 'tblsla_escalation_log';
    protected $primaryKey = 'idtblsla_escalation_log';
    public $incrementing  = true;
    protected $keyType    = 'int';

    public $timestamps  = true;
    const UPDATED_AT    = null;

    protected $fillable = [
        'task_id',
        'idtblapproval_request',
        'escalation_level',
        'idtbluser_escalated_to',
        'idtblrole_escalated_to',
        'escalation_message',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'task_id'                 => 'integer',
        'idtblapproval_request'   => 'integer',
        'escalation_level'        => 'integer',
        'idtbluser_escalated_to'  => 'integer',
        'idtblrole_escalated_to'  => 'integer',
        'resolved_at'             => 'datetime',
        'created_at'              => 'datetime',
    ];

    public const STATUS_TRIGGERED = 'TRIGGERED';
    public const STATUS_NOTIFIED  = 'NOTIFIED';
    public const STATUS_RESOLVED  = 'RESOLVED';
    public const STATUS_CANCELLED = 'CANCELLED';

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function task(): BelongsTo
    {
        return $this->belongsTo(TblTask::class, 'task_id', 'idtbltask');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(
            TblApprovalRequest::class,
            'idtblapproval_request',
            'idtblapproval_request'
        );
    }

    public function escalatedToUser(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser_escalated_to', 'idtbluser');
    }

    public function escalatedToRole(): BelongsTo
    {
        return $this->belongsTo(TblRole::class, 'idtblrole_escalated_to', 'idtblrole');
    }
}
