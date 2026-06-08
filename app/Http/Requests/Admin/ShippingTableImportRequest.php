<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ShippingTableImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:10240',
            ],
            'replace_existing' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'replace_existing' => $this->boolean('replace_existing'),
        ]);
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Il file CSV è obbligatorio.',
            'file.file' => 'Il file caricato non è valido.',
            'file.mimes' => 'Il file deve essere un CSV valido.',
            'file.max' => 'Il file CSV supera la dimensione massima consentita.',
        ];
    }
}