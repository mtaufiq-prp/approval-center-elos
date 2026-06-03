<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentTypeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasAnyRole('ADMIN_APPROVAL'); }

    public function rules(): array
    {
        $id = $this->route('document_type');
        $idValue = is_object($id) ? $id->idtbldocument_type : $id;

        return [
            'idtblsource_app' => ['required', 'integer', Rule::exists('tblsource_app', 'idtblsource_app')],
            'doc_code' => [
                'required', 'string', 'max:50',
                Rule::unique('tbldocument_type')->where(fn($q) => $q->where('idtblsource_app', (int) $this->input('idtblsource_app')))
                    ->ignore($idValue, 'idtbldocument_type'),
            ],
            'doc_name'            => ['required', 'string', 'max:150'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'form_schema'         => ['nullable', 'string'],
            'sample_context_json' => ['nullable', 'string'],
            'is_active'           => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['doc_code.unique' => 'Doc Code sudah dipakai di Source App yang sama.'];
    }
}
