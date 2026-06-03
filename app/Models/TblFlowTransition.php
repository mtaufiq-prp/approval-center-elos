<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Flow Transition = Edge BPMN-lite (panah antar node).
 *
 * Tabel : tblflow_transition
 * PK    : idtblflow_transition
 *
 * action_code : SUBMIT, APPROVE, REJECT, RETURN, CANCEL,
 *               AUTO, AUTO_APPROVE, TIMEOUT (string bebas, runtime
 *               yang menentukan semantik).
 *
 * idtblflow_step_to NULL → edge mengarah ke END virtual (jarang dipakai;
 * disarankan punya node END eksplisit).
 *
 * final_status (opsional): jika edge ini final, status request di-set
 * ke value ini (APPROVED/REJECTED/dst).
 *
 * BPMN-lite (migration 000005):
 *  - transition_code, transition_name, transition_type
 *  - priority_no  : urutan evaluasi (kecil = duluan) untuk EXCLUSIVE
 *  - is_default   : edge default jika tidak ada condition match
 *  - is_active    : nonaktif tanpa hapus
 *  - transition_config_json
 *
 * ATURAN is_default (di-enforce FormRequest, bukan DB):
 *   per (idtblflow_step_from, action_code) → maksimal 1 row is_default=1.
 */
class TblFlowTransition extends Model
{
    protected $table      = 'tblflow_transition';
    protected $primaryKey = 'idtblflow_transition';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'idtblflow_version',
        'transition_code',
        'transition_name',
        'transition_type',
        'idtblflow_step_from',
        'action_code',
        'idtblflow_step_to',
        'final_status',
        'condition_json',
        'priority_no',
        'is_default',
        'is_active',
        'transition_config_json',
    ];

    protected $casts = [
        'idtblflow_version'       => 'integer',
        'idtblflow_step_from'     => 'integer',
        'idtblflow_step_to'       => 'integer',
        'condition_json'          => 'array',
        'priority_no'             => 'integer',
        'is_default'              => 'boolean',
        'is_active'               => 'boolean',
        'transition_config_json'  => 'array',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];

    public const TYPE_NORMAL  = 'NORMAL';
    public const TYPE_DEFAULT = 'DEFAULT';
    public const TYPE_ERROR   = 'ERROR';
    public const TYPE_TIMEOUT = 'TIMEOUT';

    public const ALL_TYPES = [
        self::TYPE_NORMAL, self::TYPE_DEFAULT,
        self::TYPE_ERROR,  self::TYPE_TIMEOUT,
    ];

    // ---------------------------------------------------------------------
    // SCOPES
    // ---------------------------------------------------------------------

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', 1);
    }

    public function scopeOutgoingFor(Builder $q, int $stepFromId, ?string $actionCode = null): Builder
    {
        $q = $q->where('idtblflow_step_from', $stepFromId);
        if ($actionCode !== null) {
            $q = $q->where('action_code', $actionCode);
        }
        return $q->orderBy('priority_no');
    }

    public function scopeDefaultsFor(Builder $q, int $stepFromId, string $actionCode): Builder
    {
        return $q->where('idtblflow_step_from', $stepFromId)
                 ->where('action_code', $actionCode)
                 ->where('is_default', 1);
    }

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function flowVersion(): BelongsTo
    {
        return $this->belongsTo(TblFlowVersion::class, 'idtblflow_version', 'idtblflow_version');
    }

    public function stepFrom(): BelongsTo
    {
        return $this->belongsTo(TblFlowStep::class, 'idtblflow_step_from', 'idtblflow_step');
    }

    public function stepTo(): BelongsTo
    {
        return $this->belongsTo(TblFlowStep::class, 'idtblflow_step_to', 'idtblflow_step');
    }
}
