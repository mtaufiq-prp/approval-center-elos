<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versi flow approval (BPMN-lite).
 *
 * Tabel : tblflow_version
 * PK    : idtblflow_version
 *
 * Status: DRAFT, ACTIVE, INACTIVE, ARCHIVED.
 *
 * Field BPMN-lite (ditambahkan via migration 000003):
 *  - diagram_json        : metadata visual builder
 *  - builder_version     : versi UI builder
 *  - validation_status   : DRAFT / VALID / INVALID
 *  - validation_message  : pesan validator
 *  - validated_at        : waktu validasi terakhir
 *
 * Hanya version yang lulus FlowValidationService yang boleh di-deploy
 * menjadi ACTIVE oleh FlowVersionDeploymentService.
 */
class TblFlowVersion extends Model
{
    protected $table      = 'tblflow_version';
    protected $primaryKey = 'idtblflow_version';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'idtblflow_definition',
        'version_no',
        'version_name',
        'status',
        'effective_start',
        'effective_end',
        'definition_json',
        // BPMN-lite
        'diagram_json',
        'builder_version',
        'validation_status',
        'validation_message',
        'validated_at',
        // Deployment
        'idtbluser_deployed_by',
        'deployed_at',
        'deployment_note',
    ];

    protected $casts = [
        'idtblflow_definition'  => 'integer',
        'version_no'            => 'integer',
        'effective_start'       => 'date',
        'effective_end'         => 'date',
        'definition_json'       => 'array',
        'diagram_json'          => 'array',
        'validated_at'          => 'datetime',
        'idtbluser_deployed_by' => 'integer',
        'deployed_at'           => 'datetime',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
    ];

    public const STATUS_DRAFT    = 'DRAFT';
    public const STATUS_ACTIVE   = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    public const VALIDATION_DRAFT   = 'DRAFT';
    public const VALIDATION_VALID   = 'VALID';
    public const VALIDATION_INVALID = 'INVALID';

    // ---------------------------------------------------------------------
    // HELPERS
    // ---------------------------------------------------------------------

    public function isActive(): bool   { return $this->status === self::STATUS_ACTIVE; }
    public function isDraft(): bool    { return $this->status === self::STATUS_DRAFT; }
    public function isValidated(): bool{ return $this->validation_status === self::VALIDATION_VALID; }

    /**
     * Apakah version ini sudah dipakai oleh approval request manapun?
     * Dipakai untuk lock edit (Anda minta clone wajib jika sudah dipakai).
     */
    public function isInUse(): bool
    {
        return $this->approvalRequestsUsingThisVersion()->exists();
    }

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function flowDefinition(): BelongsTo
    {
        return $this->belongsTo(
            TblFlowDefinition::class,
            'idtblflow_definition',
            'idtblflow_definition'
        );
    }

    public function deployedBy(): BelongsTo
    {
        return $this->belongsTo(TblUser::class, 'idtbluser_deployed_by', 'idtbluser');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(TblFlowStep::class, 'idtblflow_version', 'idtblflow_version')
                    ->orderBy('step_order');
    }

    /** Alias semantik BPMN-lite. */
    public function nodes(): HasMany
    {
        return $this->steps();
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(TblFlowTransition::class, 'idtblflow_version', 'idtblflow_version');
    }

    /** Alias semantik BPMN-lite. */
    public function edges(): HasMany
    {
        return $this->transitions();
    }

    public function processInstances(): HasMany
    {
        return $this->hasMany(TblProcessInstance::class, 'idtblflow_version', 'idtblflow_version');
    }

    public function approvalRequestsUsingThisVersion(): HasMany
    {
        return $this->hasMany(
            TblApprovalRequest::class,
            'idtblflow_version_selected',
            'idtblflow_version'
        );
    }
}
