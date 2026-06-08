<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin.only già blocca
    }

    public function rules(): array
    {
        return [
            // NON permettere value_code: è identità
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'erp_lastchange' => ['sometimes', 'nullable', 'date'],

            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required_with:translations', 'string', 'max:5', 'distinct'],
            'translations.*.label' => ['required_with:translations', 'string', 'max:255'],
        ];
    }
}