<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Antrian notifikasi.
 *
 * Tabel : tblnotification_queue
 * PK    : idtblnotification_queue
 *
 * channel: EMAIL, TELEGRAM, WHATSAPP, WEB_PUSH, IN_APP
 * Versi awal yang aktif: IN_APP + EMAIL (lihat config approval_center).
 *
 * status: PENDING, SENT, FAILED, CANCELLED
 *
 * FK: task_id → tbltask.idtbltask (skema asli)
 */
class TblNotificationQueue extends Model
{
    protected $table      = 'tblnotification_queue';
    protected $primaryKey = 'idtblnotification_queue';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'idtblapproval_request',
        'task_id',
        'idtbluser',
        'channel',
        'recipient',
        'subject',
        'message',
        'status',
        'retry_count',
        'next_retry_at',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'idtblapproval_request' => 'integer',
        'task_id'               => 'integer',
        'idtbluser'             => 'integer',
        'retry_count'           => 'integer',
        'next_retry_at'         => 'datetime',
        'sent_at'               => 'datetime',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
    ];

    public const CHANNEL_EMAIL    = 'EMAIL';
    public const CHANNEL_TELEGRAM = 'TELEGRAM';
    public const CHANNEL_WHATSAPP = 'WHATSAPP';
    public const CHANNEL_WEB_PUSH = 'WEB_PUSH';
    public const CHANNEL_IN_APP   = 'IN_APP';

    public const STATUS_PENDING   = 'PENDING';
    public const STATUS_SENT      = 'SENT';
    public const STATUS_FAILED    = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';

    // ---------------------------------------------------------------------
    // SCOPES
    // ---------------------------------------------------------------------

    public function scopeReadyForDispatch(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $at = $at ?? now();

        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_FAILED])
                     ->where(function ($q) use ($at) {
                         $q->whereNull('next_retry_at')
                           ->orWhere('next_retry_at', '<=', $at);
                     })
                     ->orderBy('next_retry_at');
    }

    public function scopeInbox(Builder $query, int $idtbluser): Builder
    {
        return $query->where('idtbluser', $idtbluser)
                     ->where('channel', self::CHANNEL_IN_APP)
                     ->orderByDesc('created_at');
    }

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

    public function task(): BelongsTo
    {
        return $this->belongsTo(TblTask::class, 'task_id', 'idtbltask');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser', 'idtbluser');
    }
}
