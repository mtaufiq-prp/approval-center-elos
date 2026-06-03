<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasAnyRole('ADMIN_APPROVAL');
    }

    public function rules(): array
    {
        $id = $this->route('role');
        $idValue = is_object($id) ? $id->idtblrole : $id;

        return [
            'role_code' => [
                'required', 'string', 'max:50',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('tblrole', 'role_code')->ignore($idValue, 'idtblrole'),
            ],
            'role_name'   => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
