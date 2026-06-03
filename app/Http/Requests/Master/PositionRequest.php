<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PositionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasAnyRole('ADMIN_APPROVAL'); }

    public function rules(): array
    {
        $id = $this->route('position');
        $idValue = is_object($id) ? $id->idtblposition : $id;

        return [
            'position_code' => ['required', 'string', 'max:50',
                Rule::unique('tblposition', 'position_code')->ignore($idValue, 'idtblposition')],
            'position_name' => ['required', 'string', 'max:150'],
            'level_no'=> ['nullable', 'integer', 'min:0', 'max:50'],
            'idtblorg_unit' => ['nullable', 'integer', Rule::exists('tblorg_unit', 'idtblorg_unit')],
            'is_active'     => ['sometimes', 'boolean'],
        ];
    }
}
