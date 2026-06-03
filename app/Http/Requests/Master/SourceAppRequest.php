<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SourceAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasAnyRole('ADMIN_APPROVAL');
    }

    public function rules(): array
    {
        $id = $this->route('source_app');
        $idValue = is_object($id) ? $id->idtblsource_app : $id;

        return [
            'app_code' => [
                'required', 'string', 'max:50',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('tblsource_app', 'app_code')->ignore($idValue, 'idtblsource_app'),
            ],
            'app_name'             => ['required', 'string', 'max:150'],
            'description'          => ['nullable', 'string', 'max:1000'],
            'base_url'             => ['nullable', 'url', 'max:255'],
            'default_callback_url' => ['nullable', 'url', 'max:255'],
            'is_active'            => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'app_code.regex' => 'app_code hanya boleh huruf kapital, angka, dan underscore.',
        ];
    }
}
