<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin.only già blocca
    }

    public function rules(): array
    {
        return [
            'ditta_cg18' => ['required', 'integer', 'min:1'],
            'code' => ['required', 'string', 'max:64'],
            'type' => ['required', 'in:select,text,number,boolean'],
            'is_filterable' => ['required', 'boolean'],
            'is_variant' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'erp_lastchange' => ['nullable', 'date'],

            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required_with:translations', 'string', 'max:5', 'distinct'],
            'translations.*.label' => ['required_with:translations', 'string', 'max:255'],
            'translations.*.help_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}