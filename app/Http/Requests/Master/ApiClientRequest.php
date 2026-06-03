<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasAnyRole('ADMIN_APPROVAL');
    }

    public function rules(): array
    {
        // Client key tidak boleh diubah setelah dibuat (untuk rotate, hanya secret)
        // jadi rules sama saja antara create & update; controller akan handle.
        return [
            'idtblsource_app'  => ['required', 'integer', Rule::exists('tblsource_app', 'idtblsource_app')->where('is_active', 1)],
            'allowed_ip'       => ['nullable', 'string', 'max:255'],
            'token_expired_at' => ['nullable', 'date'],
            'is_active'        => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'idtblsource_app'  => 'Source App',
            'allowed_ip'       => 'Allowed IP',
            'token_expired_at' => 'Token Expired At',
        ];
    }
}
