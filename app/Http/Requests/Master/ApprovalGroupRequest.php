<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovalGroupRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasAnyRole('ADMIN_APPROVAL'); }

    public function rules(): array
    {
        $id = $this->route('approval_group');
        $idValue = is_object($id) ? $id->idtblapproval_group : $id;

        return [
            'group_code' => ['required', 'string', 'max:50',
                Rule::unique('tblapproval_group', 'group_code')->ignore($idValue, 'idtblapproval_group')],
            'group_name'  => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
