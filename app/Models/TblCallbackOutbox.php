<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Outbox callback — menyimpan event hasil approval yang harus dikirim
 * ke aplikasi sumber. WAJIB pakai pola outbox; tidak boleh callback
 * langsung dari controller.
 *
 * Tabel : tblcallback_outbox
 * PK    : idtblcallback_outbox
 *
 * event_type: APPROVED, REJECTED, RETURNED, CANCELLED, ERROR, TASK_CREATED
 *
 * status:
 *   PENDING : siap dikirim, atau menunggu next_retry_at
 *   SENT    : sukses HTTP 2xx
 *   FAILED  : gagal, akan retry sesuai exponential backoff
 *   DEAD    : gagal terus sampai max_retry, butuh tindakan manual admin
 *
 * Worker (SendCallbackJob) mengambil row dengan:
 *   status IN ('PENDING','FAILED') AND next_retry_at <= NOW()
 *   ORDER BY next_retry_at
 *   FOR UPDATE SKIP LOCKED  (anti-double-pick antar worker)
 */
class TblCallbackOutbox extends Model
{
    protected $table      = 'tblcallback_outbox';
    protected $primaryKey = 'idtblcallback_outbox';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'idtblapproval_request',
        'idtblsource_app',
        'event_type',
        'target_url',
        'payload_json',
        'status',
        'retry_count',
        'max_retry',
        'next_retry_at',
        'last_response_code',
        'last_response_body',
        'last_error_message',
        'sent_at',
    ];

    protected $casts = [
        'idtblapproval_request' => 'integer',
        'idtblsource_app'       => 'integer',
        'payload_json'          => 'array',
        'retry_count'           => 'integer',
        'max_retry'             => 'integer',
        'next_retry_at'         => 'datetime',
        'last_response_code'    => 'integer',
        'sent_at'               => 'datetime',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
    ];

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SENT    = 'SENT';
    public const STATUS_FAILED  = 'FAILED';
    public const STATUS_DEAD    = 'DEAD';

    // ---------------------------------------------------------------------
    // SCOPES
    // ---------------------------------------------------------------------

    /**
     * Row yang siap dikirim oleh worker.
     */
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

    public function scopeDead(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DEAD);
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

    public function sourceApp(): BelongsTo
    {
        return $this->belongsTo(TblSourceApp::class, 'idtblsource_app', 'idtblsource_app');
    }
}
