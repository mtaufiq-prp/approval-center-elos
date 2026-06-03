<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Attachment dokumen approval.
 *
 * Tabel : tblapproval_attachment
 * PK    : idtblapproval_attachment
 *
 * Catatan:
 * - Tabel ini hanya punya created_at (tidak ada updated_at).
 * - file_url disarankan menyimpan reference / signed URL ke file storage
 *   eksternal (S3, MinIO, atau internal file server), bukan file biner.
 */
class TblApprovalAttachment extends Model
{
    protected $table      = 'tblapproval_attachment';
    protected $primaryKey = 'idtblapproval_attachment';
    public $incrementing  = true;
    protected $keyType    = 'int';

    public $timestamps  = true;
    const UPDATED_AT    = null;

    protected $fillable = [
        'idtblapproval_request',
        'source_file_id',
        'file_name',
        'file_url',
        'mime_type',
        'file_size',
        'uploaded_by_ref',
    ];

    protected $casts = [
        'idtblapproval_request' => 'integer',
        'file_size'             => 'integer',
        'created_at'            => 'datetime',
    ];

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(
            TblApprovalRequest::class,
            'idtblapproval_request',
            'idtblapproval_request'
        );
    }
}
