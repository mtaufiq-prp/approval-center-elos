<?php

namespace App\Http\Requests\Workflow;

use App\Models\TblFlowStep;
use App\Models\TblFlowTransition;
use App\Models\TblFlowVersion;
use App\Support\ConditionJsonValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FlowEdgeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasAnyRole('ADMIN_APPROVAL'); }

    public function rules(): array
    {
        $version   = $this->route('flow_version');
        $versionId = is_object($version) ? $version->idtblflow_version : (int) $version;
        $edge      = $this->route('flow_transition');
        $edgeId    = is_object($edge) ? $edge->idtblflow_transition : $edge;

        // Node IDs yang valid dalam version ini
        $validNodeIds = TblFlowStep::where('idtblflow_version', $versionId)
            ->pluck('idtblflow_step')->toArray();

        return [
            'transition_code' => ['nullable', 'string', 'max:100',
                Rule::unique('tblflow_transition', 'transition_code')
                    ->where(fn($q) => $q->where('idtblflow_version', $versionId))
                    ->ignore($edgeId, 'idtblflow_transition'),
            ],
            'transition_name'    => ['nullable', 'string', 'max:150'],
            'transition_type'    => ['required', Rule::in(TblFlowTransition::ALL_TYPES)],
            'idtblflow_step_from'=> ['required', 'integer', Rule::in($validNodeIds)],
            'action_code'        => ['required', 'string', 'max:50'],
            'idtblflow_step_to'  => ['nullable', 'integer', Rule::in($validNodeIds)],
            'final_status'       => ['nullable', 'string', 'max:50'],
            'priority_no'        => ['required', 'integer', 'min:0'],
            'is_default'         => ['sometimes', 'boolean'],
            'is_active'          => ['sometimes', 'boolean'],
            'condition_json_raw' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // R10: is_default max 1 per (from, action_code)
            if ($this->boolean('is_default')) {
                $version   = $this->route('flow_version');
                $versionId = is_object($version) ? $version->idtblflow_version : (int) $version;
                $edge      = $this->route('flow_transition');
                $edgeId    = is_object($edge) ? $edge->idtblflow_transition : null;

                $existing = TblFlowTransition::where('idtblflow_version', $versionId)
                    ->where('idtblflow_step_from', $this->input('idtblflow_step_from'))
                    ->where('action_code', $this->input('action_code'))
                    ->where('is_default', 1)
                    ->when($edgeId, fn($q) => $q->where('idtblflow_transition', '!=', $edgeId))
                    ->exists();

                if ($existing) {
                    $v->errors()->add('is_default', 'Sudah ada default transition lain untuk from_node + action_code yang sama.');
                }
            }

            // R12: condition_json struktural
            $raw = $this->input('condition_json_raw');
            if ($raw !== null && trim($raw) !== '') {
                $cv = app(ConditionJsonValidator::class);
                if (! $cv->validateRaw($raw)) {
                    foreach ($cv->errors() as $err) {
                        $v->errors()->add('condition_json_raw', $err);
                    }
                }
            }
        });
    }
}
