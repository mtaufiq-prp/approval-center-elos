<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DelegationRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasAnyRole('ADMIN_APPROVAL'); }

    public function rules(): array
    {
        return [
            'idtbluser_delegator' => ['required', 'integer', 'different:idtbluser_delegate',
                                       Rule::exists('tbluser', 'idtbluser')->where('is_active', 1)],
            'idtbluser_delegate'  => ['required', 'integer',
                                       Rule::exists('tbluser', 'idtbluser')->where('is_active', 1)],
            'idtblsource_app'     => ['nullable', 'integer', Rule::exists('tblsource_app', 'idtblsource_app')],
            'idtbldocument_type'  => ['nullable', 'integer', Rule::exists('tbldocument_type', 'idtbldocument_type')],
            'start_at'            => ['required', 'date'],
            'end_at'              => ['required', 'date', 'after:start_at'],
            'reason'              => ['nullable', 'string', 'max:1000'],
            'is_active'           => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'idtbluser_delegator.different' => 'Delegator dan Delegate tidak boleh sama.',
            'end_at.after'                  => 'Tanggal selesai harus setelah tanggal mulai.',
        ];
    }
}
