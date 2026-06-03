<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi form login.
 *
 * Field 'login' menerima user_ref ATAU email — keputusan ada di
 * LoginController (try email dulu, lalu user_ref).
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // halaman publik
    }

    public function rules(): array
    {
        return [
            'login'    => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'login'    => 'User Ref / Email',
            'password' => 'Password',
        ];
    }

    public function messages(): array
    {
        return [
            'login.required'    => 'User Ref atau Email wajib diisi.',
            'password.required' => 'Password wajib diisi.',
            'password.min'      => 'Password minimal 6 karakter.',
        ];
    }
}
