<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Task approval — unit pekerjaan untuk approver.
 *
 * Tabel : tbltask
 * PK    : idtbltask
 *
 * task_status:
 *   OPEN, CLAIMED, APPROVED, REJECTED, RETURNED, CANCELLED, SKIPPED, EXPIRED
 * decision_code:
 *   APPROVE, REJECT, RETURN, CANCEL, SKIP, AUTO_APPROVE
 *
 * Salah satu (idtbluser_assigned, idtblrole_assigned,
 * idtblapproval_group_assigned) yang terisi, sesuai assignee_type
 * dari step assignee rule. Daftar kandidat detail disimpan di
 * tbltask_candidate.
 *
 * PENTING - kolom child:
 *   tbltask_candidate, tblaction_log, tblcomment, tblnotification_queue,
 *   tblsla_escalation_log → menggunakan FK bernama `task_id`
 *   (bukan idtbltask). Tidak diubah; di-handle via foreignKey override.
 */
class TblTask extends Model
{
    protected $table      = 'tbltask';
    protected $primaryKey = 'idtbltask';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'idtblprocess_instance',
        'idtblapproval_request',
        'idtblflow_step',
        'task_no',
        'task_status',
        'idtbluser_assigned',
        'idtblrole_assigned',
        'idtblapproval_group_assigned',
        'idtbluser_claimed_by',
        'idtbluser_completed_by',
        'idtbluser_delegated_from',
        'decision_code',
        'decision_note',
        'due_at',
        'claimed_at',
        'completed_at',
    ];

    protected $casts = [
        'idtblprocess_instance'        => 'integer',
        'idtblapproval_request'        => 'integer',
        'idtblflow_step'               => 'integer',
        'idtbluser_assigned'           => 'integer',
        'idtblrole_assigned'           => 'integer',
        'idtblapproval_group_assigned' => 'integer',
        'idtbluser_claimed_by'         => 'integer',
        'idtbluser_completed_by'       => 'integer',
        'idtbluser_delegated_from'     => 'integer',
        'due_at'                       => 'datetime',
        'claimed_at'                   => 'datetime',
        'completed_at'                 => 'datetime',
        'created_at'                   => 'datetime',
        'updated_at'                   => 'datetime',
    ];

    public const STATUS_OPEN      = 'OPEN';
    public const STATUS_CLAIMED   = 'CLAIMED';
    public const STATUS_APPROVED  = 'APPROVED';
    public const STATUS_REJECTED  = 'REJECTED';
    public const STATUS_RETURNED  = 'RETURNED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_SKIPPED   = 'SKIPPED';
    public const STATUS_EXPIRED   = 'EXPIRED';

    public const OPEN_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CLAIMED,
    ];

    // ---------------------------------------------------------------------
    // SCOPES
    // ---------------------------------------------------------------------

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('task_status', self::OPEN_STATUSES);
    }

    public function scopeOverdue(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $at = $at ?? now();
        return $query->open()
                     ->whereNotNull('due_at')
                     ->where('due_at', '<', $at);
    }

    // ---------------------------------------------------------------------
    // HELPERS
    // ---------------------------------------------------------------------

    public function isOpen(): bool
    {
        return in_array($this->task_status, self::OPEN_STATUSES, true);
    }

    // ---------------------------------------------------------------------
    // RELATIONSHIPS (parents)
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

    public function flowStep(): BelongsTo
    {
        return $this->belongsTo(TblFlowStep::class, 'idtblflow_step', 'idtblflow_step');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser_assigned', 'idtbluser');
    }

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(TblRole::class, 'idtblrole_assigned', 'idtblrole');
    }

    public function assignedGroup(): BelongsTo
    {
        return $this->belongsTo(
            TblApprovalGroup::class,
            'idtblapproval_group_assigned',
            'idtblapproval_group'
        );
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser_claimed_by', 'idtbluser');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser_completed_by', 'idtbluser');
    }

    public function delegatedFrom(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser_delegated_from', 'idtbluser');
    }

    // ---------------------------------------------------------------------
    // RELATIONSHIPS (children) — FK bernama `task_id`
    // (Tidak diubah ke idtbltask sesuai instruksi.)
    // ---------------------------------------------------------------------

    public function candidates(): HasMany
    {
        return $this->hasMany(TblTaskCandidate::class, 'task_id', 'idtbltask');
    }

    public function activeCandidates(): HasMany
    {
        return $this->hasMany(TblTaskCandidate::class, 'task_id', 'idtbltask')
                    ->where('is_active', 1);
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(TblActionLog::class, 'task_id', 'idtbltask');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TblComment::class, 'task_id', 'idtbltask');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(TblNotificationQueue::class, 'task_id', 'idtbltask');
    }

    public function slaEscalationLogs(): HasMany
    {
        return $this->hasMany(TblSlaEscalationLog::class, 'task_id', 'idtbltask');
    }
}
