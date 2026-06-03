<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aturan penentuan approver per step.
 *
 * Tabel : tblstep_assignee_rule
 * PK    : idtblstep_assignee_rule
 *
 * assignee_type:
 * - USER           : user_ref langsung
 * - ROLE           : role_code → semua user pemegang role
 * - GROUP          : group_code → semua member group aktif
 * - POSITION       : position_code → semua user pemegang position
 * - SUPERIOR       : atasan langsung dari requester (idtbluser_superior)
 * - FIELD_USER     : ambil user_ref dari context_json[assignee_value]
 * - FIELD_POSITION : ambil position_code dari context_json[assignee_value]
 * - API_RESOLVER   : panggil resolver eksternal (stub di awal)
 *
 * condition_json : evaluasi tambahan apakah rule ini dipakai
 *                  (mis. rule ini aktif hanya jika amount > 50jt).
 */
class TblStepAssigneeRule extends Model
{
    protected $table      = 'tblstep_assignee_rule';
    protected $primaryKey = 'idtblstep_assignee_rule';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'idtblflow_step',
        'assignee_type',
        'assignee_value',
        'priority_no',
        'condition_json',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'idtblflow_step' => 'integer',
        'priority_no'    => 'integer',
        'condition_json' => 'array',
        'is_required'    => 'boolean',
        'is_active'      => 'boolean',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public function flowStep(): BelongsTo
    {
        return $this->belongsTo(TblFlowStep::class, 'idtblflow_step', 'idtblflow_step');
    }
}
