<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrgUnitRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasAnyRole('ADMIN_APPROVAL'); }

    public function rules(): array
    {
        $id = $this->route('org_unit');
        $idValue = is_object($id) ? $id->idtblorg_unit : $id;

        return [
            'org_code' => ['required', 'string', 'max:50',
                Rule::unique('tblorg_unit', 'org_code')->ignore($idValue, 'idtblorg_unit')],
            'org_name'             => ['required', 'string', 'max:150'],
            'idtblorg_unit_parent' => ['nullable', 'integer',
                Rule::exists('tblorg_unit', 'idtblorg_unit'),
                function ($attr, $value, $fail) use ($idValue) {
                    if ($idValue && (int) $value === (int) $idValue) {
                        $fail('Org Unit tidak boleh menjadi parent dirinya sendiri.');
                    }
                },
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
