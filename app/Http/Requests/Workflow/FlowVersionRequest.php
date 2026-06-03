<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class FlowVersionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasAnyRole('ADMIN_APPROVAL'); }

    public function rules(): array
    {
        return [
            'version_no'      => ['required', 'integer', 'min:1'],
            'version_name'    => ['required', 'string', 'max:120'],
            'effective_start' => ['nullable', 'date'],
            'effective_end'   => ['nullable', 'date', 'after:effective_start'],
        ];
    }
}
