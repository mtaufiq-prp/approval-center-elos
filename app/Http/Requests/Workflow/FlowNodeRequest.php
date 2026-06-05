<?php

namespace App\Http\Requests\Workflow;

use App\Models\TblFlowStep;
use App\Support\ConditionJsonValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FlowNodeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasAnyRole('ADMIN_APPROVAL'); }

    public function rules(): array
    {
        $version = $this->route('flow_version');
        $versionId = is_object($version) ? $version->idtblflow_version : (int) $version;

        $node = $this->route('flow_step');
        $nodeId = is_object($node) ? $node->idtblflow_step : $node;

        return [
            'node_code' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9_]+$/',
                Rule::unique('tblflow_step', 'node_code')
                    ->where(fn($q) => $q->where('idtblflow_version', $versionId))
                    ->ignore($nodeId, 'idtblflow_step'),
            ],
            'step_code'      => ['nullable', 'string', 'max:80'],
            'step_name'      => ['required', 'string', 'max:180'],
            'step_type'      => ['required', Rule::in(TblFlowStep::ALL_NODE_TYPES)],
            'gateway_type'   => ['required', Rule::in(TblFlowStep::ALL_GATEWAY_TYPES)],
            'step_order'     => ['nullable', 'integer', 'min:0'],
            'approval_mode'  => ['nullable', Rule::in(['ANY', 'ALL', 'SEQUENTIAL'])],
            'reject_behavior'=> ['nullable', 'string', 'max:50'],
            'allow_delegate' => ['sometimes', 'boolean'],
            'allow_edit_payload' => ['sometimes', 'boolean'],
            // Daftar path field yang boleh diedit approver di node ini (1 path per baris,
            // gaya form_schema mis. "header.keterangan"). Disimpan ke node_config_json.editable_fields.
            'editable_fields_raw' => ['nullable', 'string', 'max:4000'],
            // Per-node callback: kirim callback ke source app saat flow MASUK node ini.
            'callback_on_enter'   => ['sometimes', 'boolean'],
            'callback_event_code' => ['nullable', 'string', 'max:80'],
            'sla_hours'      => ['nullable', 'integer', 'min:0'],
            'instruction'    => ['nullable', 'string', 'max:1000'],
            'pos_x'          => ['nullable', 'integer'],
            'pos_y'          => ['nullable', 'integer'],
            'condition_json_raw' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // R11: gateway_type konsisten dengan step_type
            $type    = $this->input('step_type');
            $gateway = $this->input('gateway_type');
            if ($type === TblFlowStep::TYPE_DECISION && $gateway === TblFlowStep::GATEWAY_NONE) {
                $v->errors()->add('gateway_type', 'DECISION node wajib EXCLUSIVE/INCLUSIVE/PARALLEL, bukan NONE.');
            }
            if ($type !== TblFlowStep::TYPE_DECISION && $gateway !== TblFlowStep::GATEWAY_NONE) {
                $v->errors()->add('gateway_type', "Node {$type} hanya boleh gateway_type NONE.");
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
