<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin.only già blocca
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'in:select,text,number,boolean'],
            'is_filterable' => ['sometimes', 'boolean'],
            'is_variant' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'erp_lastchange' => ['sometimes', 'nullable', 'date'],

            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required_with:translations', 'string', 'max:5', 'distinct'],
            'translations.*.label' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.help_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}