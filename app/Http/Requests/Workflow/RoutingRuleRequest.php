<?php

namespace App\Http\Requests\Workflow;

use App\Support\ConditionJsonValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoutingRuleRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasAnyRole('ADMIN_APPROVAL'); }

    public function rules(): array
    {
        $id = $this->route('routing_rule');
        $idValue = is_object($id) ? $id->idtblrouting_rule : $id;

        return [
            'rule_code' => ['required', 'string', 'max:80',
                Rule::unique('tblrouting_rule', 'rule_code')->ignore($idValue, 'idtblrouting_rule')],
            'rule_name'           => ['required', 'string', 'max:180'],
            'idtblsource_app'     => ['required', 'integer', Rule::exists('tblsource_app', 'idtblsource_app')],
            'idtbldocument_type'  => ['required', 'integer', Rule::exists('tbldocument_type', 'idtbldocument_type')],
            'priority_no'         => ['required', 'integer', 'min:0'],
            'idtblflow_definition'=> ['required', 'integer', Rule::exists('tblflow_definition', 'idtblflow_definition')],
            'idtblflow_version'   => ['nullable', 'integer', Rule::exists('tblflow_version', 'idtblflow_version')],
            'condition_json_raw'  => ['nullable', 'string'],
            'is_active'           => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
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
