<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Flow Step = Node BPMN-lite.
 *
 * Tabel : tblflow_step
 * PK    : idtblflow_step
 *
 * step_type (node type) — ENUM diperluas via migration 000004:
 *   START, APPROVAL, REVIEW, NOTIFICATION, SYSTEM, END, DECISION
 *
 * Versi awal yang diutamakan: START, APPROVAL, DECISION, END.
 * REVIEW/NOTIFICATION/SYSTEM tetap valid tapi belum di-runtime-kan.
 *
 * gateway_type (untuk DECISION):
 *   NONE       : default; wajib untuk non-DECISION
 *   EXCLUSIVE  : ambil 1 transition match dengan priority_no terkecil
 *   INCLUSIVE  : ambil semua transition match (TODO Tahap berikutnya)
 *   PARALLEL   : fork ke beberapa branch (TODO Tahap berikutnya)
 *
 * Field visual builder: pos_x, pos_y, node_width, node_height,
 * node_style_json, node_config_json — disiapkan untuk drag-and-drop.
 *
 * step_order tetap dipakai sebagai default sorting tampilan, BUKAN
 * sebagai urutan runtime. Runtime engine berbasis graph traversal.
 */
class TblFlowStep extends Model
{
    protected $table      = 'tblflow_step';
    protected $primaryKey = 'idtblflow_step';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'idtblflow_version',
        'step_code',
        'step_name',
        // BPMN-lite
        'node_code',
        'step_order',
        'step_type',
        'gateway_type',
        // visual builder
        'pos_x',
        'pos_y',
        'node_width',
        'node_height',
        'node_style_json',
        'node_config_json',
        // existing
        'approval_mode',
        'reject_behavior',
        'allow_delegate',
        'allow_edit_payload',
        'sla_hours',
        'condition_json',
        'instruction',
    ];

    protected $casts = [
        'idtblflow_version'   => 'integer',
        'step_order'          => 'integer',
        'pos_x'               => 'integer',
        'pos_y'               => 'integer',
        'node_width'          => 'integer',
        'node_height'         => 'integer',
        'node_style_json'     => 'array',
        'node_config_json'    => 'array',
        'allow_delegate'      => 'boolean',
        'allow_edit_payload'  => 'boolean',
        'sla_hours'           => 'integer',
        'condition_json'      => 'array',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    // Node types (BPMN-lite utama)
    public const TYPE_START    = 'START';
    public const TYPE_APPROVAL = 'APPROVAL';
    public const TYPE_DECISION = 'DECISION';
    public const TYPE_END      = 'END';

    // Node types tambahan (di schema tapi belum dipakai engine awal)
    public const TYPE_REVIEW       = 'REVIEW';
    public const TYPE_NOTIFICATION = 'NOTIFICATION';
    public const TYPE_SYSTEM       = 'SYSTEM';

    public const GATEWAY_NONE      = 'NONE';
    public const GATEWAY_EXCLUSIVE = 'EXCLUSIVE';
    public const GATEWAY_INCLUSIVE = 'INCLUSIVE';
    public const GATEWAY_PARALLEL  = 'PARALLEL';

    public const ALL_NODE_TYPES = [
        self::TYPE_START, self::TYPE_APPROVAL, self::TYPE_DECISION, self::TYPE_END,
        self::TYPE_REVIEW, self::TYPE_NOTIFICATION, self::TYPE_SYSTEM,
    ];

    public const ALL_GATEWAY_TYPES = [
        self::GATEWAY_NONE, self::GATEWAY_EXCLUSIVE,
        self::GATEWAY_INCLUSIVE, self::GATEWAY_PARALLEL,
    ];

    // ---------------------------------------------------------------------
    // SCOPES
    // ---------------------------------------------------------------------

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('step_type', $type);
    }

    public function scopeForVersion(Builder $q, int $idtblflow_version): Builder
    {
        return $q->where('idtblflow_version', $idtblflow_version);
    }

    // ---------------------------------------------------------------------
    // HELPERS
    // ---------------------------------------------------------------------

    public function isStart(): bool    { return $this->step_type === self::TYPE_START; }
    public function isApproval(): bool { return $this->step_type === self::TYPE_APPROVAL; }
    public function isDecision(): bool { return $this->step_type === self::TYPE_DECISION; }
    public function isEnd(): bool      { return $this->step_type === self::TYPE_END; }

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function flowVersion(): BelongsTo
    {
        return $this->belongsTo(TblFlowVersion::class, 'idtblflow_version', 'idtblflow_version');
    }

    public function assigneeRules(): HasMany
    {
        return $this->hasMany(TblStepAssigneeRule::class, 'idtblflow_step', 'idtblflow_step')
                    ->orderBy('priority_no');
    }

    public function activeAssigneeRules(): HasMany
    {
        return $this->assigneeRules()->where('is_active', 1);
    }

    /** Transition keluar (sebagai from). */
    public function transitionsOut(): HasMany
    {
        return $this->hasMany(TblFlowTransition::class, 'idtblflow_step_from', 'idtblflow_step');
    }

    /** Alias semantik. */
    public function outgoingEdges(): HasMany
    {
        return $this->transitionsOut();
    }

    /** Transition masuk (sebagai to). */
    public function transitionsIn(): HasMany
    {
        return $this->hasMany(TblFlowTransition::class, 'idtblflow_step_to', 'idtblflow_step');
    }

    public function incomingEdges(): HasMany
    {
        return $this->transitionsIn();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TblTask::class, 'idtblflow_step', 'idtblflow_step');
    }
}
