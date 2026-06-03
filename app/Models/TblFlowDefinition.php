<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Definisi flow approval.
 * Mendefinisikan jenis workflow per kombinasi (source_app, document_type).
 *
 * Tabel : tblflow_definition
 * PK    : idtblflow_definition
 *
 * Versioning dilakukan di tabel terpisah TblFlowVersion sehingga
 * perubahan flow tidak merusak histori dokumen yang sudah berjalan.
 */
class TblFlowDefinition extends Model
{
    protected $table      = 'tblflow_definition';
    protected $primaryKey = 'idtblflow_definition';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'flow_code',
        'flow_name',
        'idtblsource_app',
        'idtbldocument_type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'idtblsource_app'    => 'integer',
        'idtbldocument_type' => 'integer',
        'is_active'          => 'boolean',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

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

    public function versions(): HasMany
    {
        return $this->hasMany(TblFlowVersion::class, 'idtblflow_definition', 'idtblflow_definition');
    }

    /**
     * Versi flow yang ACTIVE saat ini. Digunakan oleh RoutingRuleService
     * ketika tblrouting_rule.idtblflow_version = NULL.
     */
    public function activeVersion(): HasMany
    {
        return $this->hasMany(TblFlowVersion::class, 'idtblflow_definition', 'idtblflow_definition')
                    ->where('status', 'ACTIVE')
                    ->orderByDesc('version_no');
    }

    public function routingRules(): HasMany
    {
        return $this->hasMany(TblRoutingRule::class, 'idtblflow_definition', 'idtblflow_definition');
    }
}
