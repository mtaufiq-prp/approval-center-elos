<?php

namespace App\Services;

use App\Models\TblFlowStep;
use App\Models\TblFlowTransition;
use App\Models\TblFlowVersion;
use App\Models\TblStepAssigneeRule;
use App\Support\FlowValidationResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * FlowVersionDeploymentService
 *
 * Mengelola transition state flow_version:
 *  - validate()       → jalankan FlowValidationService + simpan status validation
 *  - deploy()         → pastikan version VALID, lalu atomically set ACTIVE
 *                       dan ubah version ACTIVE lainnya menjadi INACTIVE
 *                       di dalam flow_definition yang sama.
 *  - clone()          → duplicate version (steps, transitions, assignee rules)
 *                       dengan remap ID, version_no naik, status DRAFT.
 */
class FlowVersionDeploymentService
{
    public function __construct(
        private FlowValidationService $validator,
        private AuditTrailService     $audit,
    ) {}

    /**
     * Jalankan validasi & simpan hasilnya di kolom validation_*.
     * Tidak mengubah status (DRAFT tetap DRAFT).
     */
    public function runValidation(TblFlowVersion $version): FlowValidationResult
    {
        $result = $this->validator->validate($version);

        $version->validation_status  = $result->isValid
            ? TblFlowVersion::VALIDATION_VALID
            : TblFlowVersion::VALIDATION_INVALID;
        $version->validation_message = $result->summary();
        $version->validated_at       = now();
        $version->save();

        $this->audit->recordEvent(
            entityType: 'tblflow_version',
            entityId:   $version->idtblflow_version,
            eventCode:  'FLOW_VALIDATED',
            message:    $result->summary(),
            newValues:  [
                'errors'   => $result->errors,
                'warnings' => $result->warnings,
            ],
        );

        return $result;
    }

    /**
     * Deploy version → ACTIVE.
     *
     * Aturan:
     *  - Wajib lulus validasi pada saat deploy (re-validasi otomatis).
     *  - Atomic: dalam 1 transaksi, set version target ACTIVE & set ACTIVE
     *    lainnya (di flow_definition yg sama) jadi INACTIVE.
     *
     * @throws RuntimeException jika validasi gagal.
     */
    public function deploy(TblFlowVersion $version, ?string $deployNote = null): TblFlowVersion
    {
        $result = $this->runValidation($version);
        if (! $result->isValid) {
            throw new RuntimeException("Deploy gagal: " . $result->summary());
        }

        DB::transaction(function () use ($version, $deployNote) {
            // Set version lain di flow_definition yang sama menjadi INACTIVE
            TblFlowVersion::where('idtblflow_definition', $version->idtblflow_definition)
                ->where('idtblflow_version', '!=', $version->idtblflow_version)
                ->where('status', TblFlowVersion::STATUS_ACTIVE)
                ->update(['status' => TblFlowVersion::STATUS_INACTIVE]);

            // Aktifkan version target
            $version->status                = TblFlowVersion::STATUS_ACTIVE;
            $version->idtbluser_deployed_by = auth()->id();
            $version->deployed_at           = now();
            $version->deployment_note       = $deployNote;
            $version->save();
        });

        $this->audit->recordEvent(
            entityType: 'tblflow_version',
            entityId:   $version->idtblflow_version,
            eventCode:  'FLOW_DEPLOYED',
            message:    "Flow version #{$version->idtblflow_version} (v{$version->version_no}) di-deploy menjadi ACTIVE.",
            newValues:  ['deployment_note' => $deployNote],
        );

        return $version->fresh();
    }

    /**
     * Clone version baru dari version existing.
     *
     * Mapping:
     *  - flow_version       : version_no++ (max+1), status DRAFT, validation reset
     *  - flow_step          : duplicate dengan idtblflow_version baru. PK baru.
     *  - flow_transition    : duplicate, from/to di-remap ke step ID baru.
     *  - step_assignee_rule : duplicate, idtblflow_step di-remap.
     *
     * Audit event: FLOW_CLONED.
     */
    public function cloneVersion(TblFlowVersion $source, ?string $newName = null): TblFlowVersion
    {
        return DB::transaction(function () use ($source, $newName) {
            $maxVersionNo = TblFlowVersion::where('idtblflow_definition', $source->idtblflow_definition)
                ->max('version_no');

            $newVersion = TblFlowVersion::create([
                'idtblflow_definition' => $source->idtblflow_definition,
                'version_no'           => ($maxVersionNo ?? 0) + 1,
                'version_name'         => $newName ?? ($source->version_name . ' (clone)'),
                'status'               => TblFlowVersion::STATUS_DRAFT,
                'effective_start'      => $source->effective_start,
                'effective_end'        => $source->effective_end,
                'definition_json'      => $source->definition_json,
                'diagram_json'         => $source->diagram_json,
                'builder_version'      => $source->builder_version,
                'validation_status'    => TblFlowVersion::VALIDATION_DRAFT,
                'validation_message'   => null,
                'validated_at'         => null,
            ]);

            // Map old_step_id => new_step_id
            $stepMap = [];
            $sourceSteps = TblFlowStep::where('idtblflow_version', $source->idtblflow_version)->get();
            foreach ($sourceSteps as $s) {
                $new = TblFlowStep::create([
                    'idtblflow_version'  => $newVersion->idtblflow_version,
                    'step_code'          => $s->step_code,
                    'step_name'          => $s->step_name,
                    'node_code'          => $s->node_code,
                    'step_order'         => $s->step_order,
                    'step_type'          => $s->step_type,
                    'gateway_type'       => $s->gateway_type,
                    'pos_x'              => $s->pos_x,
                    'pos_y'              => $s->pos_y,
                    'node_width'         => $s->node_width,
                    'node_height'        => $s->node_height,
                    'node_style_json'    => $s->node_style_json,
                    'node_config_json'   => $s->node_config_json,
                    'approval_mode'      => $s->approval_mode,
                    'reject_behavior'    => $s->reject_behavior,
                    'allow_delegate'     => $s->allow_delegate,
                    'allow_edit_payload' => $s->allow_edit_payload,
                    'sla_hours'          => $s->sla_hours,
                    'condition_json'     => $s->condition_json,
                    'instruction'        => $s->instruction,
                ]);
                $stepMap[$s->idtblflow_step] = $new->idtblflow_step;
            }

            // Assignee Rules
            foreach ($sourceSteps as $s) {
                $rules = TblStepAssigneeRule::where('idtblflow_step', $s->idtblflow_step)->get();
                foreach ($rules as $r) {
                    TblStepAssigneeRule::create([
                        'idtblflow_step' => $stepMap[$s->idtblflow_step],
                        'assignee_type'  => $r->assignee_type,
                        'assignee_value' => $r->assignee_value,
                        'priority_no'    => $r->priority_no,
                        'condition_json' => $r->condition_json,
                        'is_required'    => $r->is_required,
                        'is_active'      => $r->is_active,
                    ]);
                }
            }

            // Transitions (remap from/to)
            $edges = TblFlowTransition::where('idtblflow_version', $source->idtblflow_version)->get();
            foreach ($edges as $e) {
                TblFlowTransition::create([
                    'idtblflow_version'      => $newVersion->idtblflow_version,
                    'transition_code'        => $e->transition_code,
                    'transition_name'        => $e->transition_name,
                    'transition_type'        => $e->transition_type,
                    'idtblflow_step_from'    => $stepMap[$e->idtblflow_step_from] ?? null,
                    'action_code'            => $e->action_code,
                    'idtblflow_step_to'      => $e->idtblflow_step_to ? ($stepMap[$e->idtblflow_step_to] ?? null) : null,
                    'final_status'           => $e->final_status,
                    'condition_json'         => $e->condition_json,
                    'priority_no'            => $e->priority_no,
                    'is_default'             => $e->is_default,
                    'is_active'              => $e->is_active,
                    'transition_config_json' => $e->transition_config_json,
                ]);
            }

            $this->audit->recordEvent(
                entityType: 'tblflow_version',
                entityId:   $newVersion->idtblflow_version,
                eventCode:  'FLOW_CLONED',
                message:    "Cloned dari version #{$source->idtblflow_version} (v{$source->version_no}) → version baru #{$newVersion->idtblflow_version} (v{$newVersion->version_no}).",
                newValues:  [
                    'source_version_id' => $source->idtblflow_version,
                    'new_version_id'    => $newVersion->idtblflow_version,
                    'steps_count'       => count($stepMap),
                    'edges_count'       => $edges->count(),
                ],
            );

            return $newVersion->fresh();
        });
    }
}
