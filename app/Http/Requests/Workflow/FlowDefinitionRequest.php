<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FlowDefinitionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasAnyRole('ADMIN_APPROVAL'); }

    public function rules(): array
    {
        $id = $this->route('flow_definition');
        $idValue = is_object($id) ? $id->idtblflow_definition : $id;

        return [
            'flow_code' => ['required', 'string', 'max:80',
                Rule::unique('tblflow_definition', 'flow_code')->ignore($idValue, 'idtblflow_definition')],
            'flow_name'          => ['required', 'string', 'max:180'],
            'idtblsource_app'    => ['required', 'integer', Rule::exists('tblsource_app', 'idtblsource_app')],
            'idtbldocument_type' => ['required', 'integer', Rule::exists('tbldocument_type', 'idtbldocument_type')],
            'description'        => ['nullable', 'string', 'max:1000'],
            'is_active'          => ['sometimes', 'boolean'],
        ];
    }
}
