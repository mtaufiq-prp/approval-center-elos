<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipe dokumen per aplikasi sumber.
 * Contoh: RETUR_BARANG.RETUR, PR_ONLINE.PR_CAPEX, RPD.PERJALANAN_DINAS.
 *
 * Tabel : tbldocument_type
 * PK    : idtbldocument_type
 *
 * Unique : (idtblsource_app, doc_code)
 */
class TblDocumentType extends Model
{
    protected $table      = 'tbldocument_type';
    protected $primaryKey = 'idtbldocument_type';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'idtblsource_app',
        'doc_code',
        'doc_name',
        'description',
        'form_schema',
        'sample_context_json',
        'is_active',
    ];

    protected $casts = [
        'idtblsource_app'     => 'integer',
        'is_active'           => 'boolean',
        'form_schema'         => 'array',
        'sample_context_json' => 'array',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function sourceApp(): BelongsTo
    {
        return $this->belongsTo(TblSourceApp::class, 'idtblsource_app', 'idtblsource_app');
    }

    public function flowDefinitions(): HasMany
    {
        return $this->hasMany(TblFlowDefinition::class, 'idtbldocument_type', 'idtbldocument_type');
    }

    public function routingRules(): HasMany
    {
        return $this->hasMany(TblRoutingRule::class, 'idtbldocument_type', 'idtbldocument_type');
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(TblApprovalRequest::class, 'idtbldocument_type', 'idtbldocument_type');
    }
}
