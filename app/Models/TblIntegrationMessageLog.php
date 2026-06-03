<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log pesan integrasi — semua inbound API dari aplikasi sumber dan
 * semua outbound callback ke aplikasi sumber dicatat di sini.
 *
 * Tabel : tblintegration_message_log
 * PK    : idtblintegration_message_log
 *
 * Append-only: hanya created_at.
 *
 * direction: INBOUND (aplikasi asal → Approval Center)
 *            OUTBOUND (Approval Center → aplikasi asal callback)
 *
 * status: SUCCESS, FAILED, PENDING
 *
 * PENTING: header rahasia (Authorization, X-Signature, Cookie) WAJIB
 * di-redact sebelum disimpan ke request_header_json. Lihat
 * config/approval_center.php audit.redact_headers.
 */
class TblIntegrationMessageLog extends Model
{
    protected $table      = 'tblintegration_message_log';
    protected $primaryKey = 'idtblintegration_message_log';
    public $incrementing  = true;
    protected $keyType    = 'int';

    public $timestamps  = true;
    const UPDATED_AT    = null;

    protected $fillable = [
        'idtblsource_app',
        'idtblapproval_request',
        'direction',
        'endpoint',
        'http_method',
        'request_header_json',
        'request_body_json',
        'response_code',
        'response_body',
        'status',
        'idempotency_key',
        'error_message',
    ];

    protected $casts = [
        'idtblsource_app'       => 'integer',
        'idtblapproval_request' => 'integer',
        'request_header_json'   => 'array',
        'request_body_json'     => 'array',
        'response_code'         => 'integer',
        'created_at'            => 'datetime',
    ];

    public const DIRECTION_INBOUND  = 'INBOUND';
    public const DIRECTION_OUTBOUND = 'OUTBOUND';

    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAILED  = 'FAILED';
    public const STATUS_PENDING = 'PENDING';

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function sourceApp(): BelongsTo
    {
        return $this->belongsTo(TblSourceApp::class, 'idtblsource_app', 'idtblsource_app');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(
            TblApprovalRequest::class,
            'idtblapproval_request',
            'idtblapproval_request'
        );
    }
}
