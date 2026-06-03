<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log jalur runtime engine BPMN-lite.
 *
 * Tabel : tblprocess_route_log
 * PK    : idtblprocess_route_log
 *
 * Append-only. Mencatat traversal node & evaluasi transition agar
 * sistem dapat menjelaskan mengapa sebuah dokumen melewati / skip
 * step tertentu.
 *
 * route_event:
 *   ENTER_NODE, EXIT_NODE, SKIP_NODE, EVALUATE_TRANSITION,
 *   TRANSITION_MATCH, TRANSITION_NOT_MATCH, TASK_CREATED,
 *   PROCESS_COMPLETED, PROCESS_ERROR
 */
class TblProcessRouteLog extends Model
{
    protected $table      = 'tblprocess_route_log';
    protected $primaryKey = 'idtblprocess_route_log';
    public $incrementing  = true;
    protected $keyType    = 'int';

    public $timestamps  = true;
    const UPDATED_AT    = null; // hanya created_at

    protected $fillable = [
        'idtblapproval_request',
        'idtblprocess_instance',
        'idtblflow_step',
        'idtblflow_transition',
        'route_event',
        'node_type',
        'action_code',
        'condition_result',
        'condition_json',
        'message',
        'created_by',
    ];

    protected $casts = [
        'idtblapproval_request' => 'integer',
        'idtblprocess_instance' => 'integer',
        'idtblflow_step'        => 'integer',
        'idtblflow_transition'  => 'integer',
        'condition_result'      => 'boolean',
        'condition_json'        => 'array',
        'created_at'            => 'datetime',
    ];

    public const EV_ENTER_NODE           = 'ENTER_NODE';
    public const EV_EXIT_NODE            = 'EXIT_NODE';
    public const EV_SKIP_NODE            = 'SKIP_NODE';
    public const EV_EVALUATE_TRANSITION  = 'EVALUATE_TRANSITION';
    public const EV_TRANSITION_MATCH     = 'TRANSITION_MATCH';
    public const EV_TRANSITION_NOT_MATCH = 'TRANSITION_NOT_MATCH';
    public const EV_TASK_CREATED         = 'TASK_CREATED';
    public const EV_PROCESS_COMPLETED    = 'PROCESS_COMPLETED';
    public const EV_PROCESS_ERROR        = 'PROCESS_ERROR';

    // ---------------------------------------------------------------------
    // SCOPES
    // ---------------------------------------------------------------------

    public function scopeForRequest(Builder $q, int $idtblapproval_request): Builder
    {
        return $q->where('idtblapproval_request', $idtblapproval_request)
                 ->orderBy('created_at');
    }

    // ---------------------------------------------------------------------
    // RELATIONSHIPS
    // ---------------------------------------------------------------------

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(
            TblApprovalRequest::class,
            'idtblapproval_request',
            'idtblapproval_request'
        );
    }

    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(
            TblProcessInstance::class,
            'idtblprocess_instance',
            'idtblprocess_instance'
        );
    }

    public function flowStep(): BelongsTo
    {
        return $this->belongsTo(TblFlowStep::class, 'idtblflow_step', 'idtblflow_step');
    }

    public function flowTransition(): BelongsTo
    {
        return $this->belongsTo(TblFlowTransition::class, 'idtblflow_transition', 'idtblflow_transition');
    }
}
