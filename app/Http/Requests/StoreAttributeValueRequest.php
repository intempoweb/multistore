<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin.only già blocca
    }

    public function rules(): array
    {
        return [
            'attribute_id' => ['required', 'integer', 'exists:attributes,id'],
            'value_code' => ['required', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'erp_lastchange' => ['nullable', 'date'],

            'translations' => ['nullable', 'array'],
            'translations.*.locale' => ['required_with:translations', 'string', 'max:5', 'distinct'],
            'translations.*.label' => ['required_with:translations', 'string', 'max:255'],
        ];
    }
}