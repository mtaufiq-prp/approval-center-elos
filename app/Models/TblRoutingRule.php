<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Routing rule — penentuan flow approval berdasarkan context_json
 * dari approval request.
 *
 * Tabel : tblrouting_rule
 * PK    : idtblrouting_rule
 *
 * priority_no : rule dengan angka lebih kecil dievaluasi lebih dulu.
 * condition_json : kondisi (evaluasi terhadap context_json).
 * idtblflow_version : opsional. Jika NULL, gunakan versi ACTIVE terbaru
 *                     dari flow_definition.
 */
class TblRoutingRule extends Model
{
    protected $table      = 'tblrouting_rule';
    protected $primaryKey = 'idtblrouting_rule';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'idtblsource_app',
        'idtbldocument_type',
        'rule_code',
        'rule_name',
        'priority_no',
        'condition_json',
        'idtblflow_definition',
        'idtblflow_version',
        'is_active',
    ];

    protected $casts = [
        'idtblsource_app'      => 'integer',
        'idtbldocument_type'   => 'integer',
        'priority_no'          => 'integer',
        'condition_json'       => 'array',
        'idtblflow_definition' => 'integer',
        'idtblflow_version'    => 'integer',
        'is_active'            => 'boolean',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    // ---------------------------------------------------------------------
    // SCOPES
    // ---------------------------------------------------------------------

    /**
     * Filter rule aktif untuk pasangan source_app + document_type tertentu,
     * sudah ter-order dari priority_no terkecil.
     */
    public function scopeMatchableFor(Builder $query, int $idSourceApp, int $idDocumentType): Builder
    {
        return $query->where('idtblsource_app', $idSourceApp)
                     ->where('idtbldocument_type', $idDocumentType)
                     ->where('is_active', 1)
                     ->orderBy('priority_no');
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

    public function flowDefinition(): BelongsTo
    {
        return $this->belongsTo(
            TblFlowDefinition::class,
            'idtblflow_definition',
            'idtblflow_definition'
        );
    }

    public function flowVersion(): BelongsTo
    {
        return $this->belongsTo(TblFlowVersion::class, 'idtblflow_version', 'idtblflow_version');
    }
}
