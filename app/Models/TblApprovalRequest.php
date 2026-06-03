<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Permohonan approval dari aplikasi sumber.
 *
 * Tabel : tblapproval_request
 * PK    : idtblapproval_request
 *
 * Catatan penting:
 * - idempotency_key + (source_app, doc_type, source_request_id) dipakai
 *   untuk menjamin idempotency submit (lihat ApprovalSubmitService).
 * - context_json: ringkasan untuk rule evaluator & filter monitoring.
 * - payload_json: payload lengkap dari aplikasi sumber, ditampilkan
 *   ke approver di Approval Detail.
 * - idtblflow_version_selected: snapshot versi flow yang dipakai
 *   request ini. Tidak ikut perubahan master flow.
 * - idtblflow_step_current: pointer step aktif (sinkron dengan
 *   tblprocess_instance.idtblflow_step_current).
 */
class TblApprovalRequest extends Model
{
    protected $table      = 'tblapproval_request';
    protected $primaryKey = 'idtblapproval_request';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'idtblsource_app',
        'idtbldocument_type',
        'source_request_id',
        'source_request_no',
        'idempotency_key',
        'title',
        'requester_ref',
        'requester_name',
        'requester_org_code',
        'requester_org_name',
        'amount',
        'currency_code',
        'priority',
        'request_status',
        'source_status',
        'callback_url',
        'context_json',
        'payload_json',
        'idtblflow_version_selected',
        'idtblflow_step_current',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'idtblsource_app'            => 'integer',
        'idtbldocument_type'         => 'integer',
        'amount'                     => 'decimal:2',
        'context_json'               => 'array',
        'payload_json'               => 'array',
        'idtblflow_version_selected' => 'integer',
        'idtblflow_step_current'     => 'integer',
        'submitted_at'               => 'datetime',
        'completed_at'               => 'datetime',
        'created_at'                 => 'datetime',
        'updated_at'                 => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // CONSTANTS
    // ---------------------------------------------------------------------
    public const STATUS_DRAFT       = 'DRAFT';
    public const STATUS_SUBMITTED   = 'SUBMITTED';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_APPROVED    = 'APPROVED';
    public const STATUS_REJECTED    = 'REJECTED';
    public const STATUS_RETURNED    = 'RETURNED';
    public const STATUS_CANCELLED   = 'CANCELLED';
    public const STATUS_ERROR       = 'ERROR';

    public const FINAL_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    public const CANCELLABLE_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RETURNED,
    ];

    // ---------------------------------------------------------------------
    // SCOPES
    // ---------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('request_status', [
            self::STATUS_SUBMITTED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_RETURNED,
        ]);
    }

    public function scopeForSourceDocument(
        Builder $query,
        int $idSourceApp,
        int $idDocumentType,
        string $sourceRequestId
    ): Builder {
        return $query->where('idtblsource_app', $idSourceApp)
                     ->where('idtbldocument_type', $idDocumentType)
                     ->where('source_request_id', $sourceRequestId);
    }

    // ---------------------------------------------------------------------
    // HELPERS
    // ---------------------------------------------------------------------

    public function isFinal(): bool
    {
        return in_array($this->request_status, self::FINAL_STATUSES, true);
    }

    public function isCancellable(): bool
    {
        return in_array($this->request_status, self::CANCELLABLE_STATUSES, true);
    }

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function sourceApp(): BelongsTo
    {
        return $this->belongsTo(TblSourceApp::class, 'idtblsource_app', 'idtblsource_app');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(TblDocumentType::class, 'idtbldocument_type', 'idtbldocument_type');
    }

    public function flowVersionSelected(): BelongsTo
    {
        return $this->belongsTo(
            TblFlowVersion::class,
            'idtblflow_version_selected',
            'idtblflow_version'
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

    public function processInstance(): HasOne
    {
        return $this->hasOne(
            TblProcessInstance::class,
            'idtblapproval_request',
            'idtblapproval_request'
        );
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(
            TblApprovalAttachment::class,
            'idtblapproval_request',
            'idtblapproval_request'
        );
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TblTask::class, 'idtblapproval_request', 'idtblapproval_request');
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(
            TblActionLog::class,
            'idtblapproval_request',
            'idtblapproval_request'
        )->orderBy('created_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(
            TblComment::class,
            'idtblapproval_request',
            'idtblapproval_request'
        )->orderBy('created_at');
    }

    public function callbackOutboxes(): HasMany
    {
        return $this->hasMany(
            TblCallbackOutbox::class,
            'idtblapproval_request',
            'idtblapproval_request'
        );
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(
            TblNotificationQueue::class,
            'idtblapproval_request',
            'idtblapproval_request'
        );
    }

    public function integrationLogs(): HasMany
    {
        return $this->hasMany(
            TblIntegrationMessageLog::class,
            'idtblapproval_request',
            'idtblapproval_request'
        );
    }

    // ---------------------------------------------------------------------
    // BPMN-lite relationships
    // ---------------------------------------------------------------------

    public function routeLogs(): HasMany
    {
        return $this->hasMany(
            TblProcessRouteLog::class,
            'idtblapproval_request',
            'idtblapproval_request'
        )->orderBy('created_at');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(
            TblProcessToken::class,
            'idtblapproval_request',
            'idtblapproval_request'
        );
    }
}
