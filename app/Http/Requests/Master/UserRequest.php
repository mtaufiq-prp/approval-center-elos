<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasAnyRole('ADMIN_APPROVAL');
    }

    public function rules(): array
    {
        $id = $this->route('user');
        $idValue = is_object($id) ? $id->idtbluser : $id;

        return [
            'user_ref' => [
                'required', 'string', 'max:80',
                Rule::unique('tbluser', 'user_ref')->ignore($idValue, 'idtbluser'),
            ],
            'full_name'         => ['required', 'string', 'max:150'],
            'email'             => ['nullable', 'email', 'max:150',
                                    Rule::unique('tbluser', 'email')->ignore($idValue, 'idtbluser')],
            'phone'             => ['nullable', 'string', 'max:50'],
            'idtblorg_unit'     => ['nullable', 'integer', Rule::exists('tblorg_unit', 'idtblorg_unit')],
            'idtblposition'     => ['nullable', 'integer', Rule::exists('tblposition', 'idtblposition')],
            'idtbluser_superior'=> ['nullable', 'integer', Rule::exists('tbluser', 'idtbluser')],
            'is_active'         => ['sometimes', 'boolean'],
            'role_ids'          => ['nullable', 'array'],
            'role_ids.*'        => ['integer', Rule::exists('tblrole', 'idtblrole')],
        ];
    }
}
