<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validasi form change password.
 *
 * Aturan password:
 *  - Min 8 karakter
 *  - Mengandung huruf, angka, dan simbol (mixed)
 *  - confirmation: password_confirmation harus sama
 *
 * Aturan dipisah dari hardcode supaya mudah disesuaikan policy
 * keamanan perusahaan kemudian.
 */
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password'         => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                Password::min(8)->letters()->numbers()->symbols(),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password' => 'Password saat ini',
            'password'         => 'Password baru',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required'      => 'Password saat ini wajib diisi.',
            'current_password.current_password' => 'Password saat ini salah.',
            'password.required'              => 'Password baru wajib diisi.',
            'password.confirmed'             => 'Konfirmasi password baru tidak sama.',
            'password.different'             => 'Password baru tidak boleh sama dengan password lama.',
        ];
    }
}
