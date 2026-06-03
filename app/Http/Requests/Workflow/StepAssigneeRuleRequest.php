<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StepAssigneeRuleRequest extends FormRequest
{
    /** Tipe assignee yang tersedia — HARUS sesuai ENUM di schema. */
    public const ASSIGNEE_TYPES = [
        'USER', 'ROLE', 'GROUP', 'POSITION', 'SUPERIOR',
        'FIELD_USER', 'FIELD_POSITION', 'API_RESOLVER',
    ];

    public function authorize(): bool { return $this->user()?->hasAnyRole('ADMIN_APPROVAL'); }

    public function rules(): array
    {
        return [
            'assignee_type'  => ['required', Rule::in(self::ASSIGNEE_TYPES)],
            'assignee_value' => ['nullable', 'string', 'max:150'],
            'priority_no'    => ['required', 'integer', 'min:0'],
            'is_required'    => ['sometimes', 'boolean'],
            'is_active'      => ['sometimes', 'boolean'],
            'condition_json_raw' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'assignee_type.in' => 'assignee_type tidak valid. Pilih: ' . implode(', ', self::ASSIGNEE_TYPES),
        ];
    }
}
