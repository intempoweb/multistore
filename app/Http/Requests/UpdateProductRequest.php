<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin.only già blocca
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            ]);
        }

        if ($this->has('no_backorder')) {
            $this->merge([
                'no_backorder' => filter_var($this->input('no_backorder'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            // parent_code si può aggiornare SOLO se vuoi “riparare” relazioni,
            // ma meglio limitarlo: massimo 108 come migration
            'parent_code' => ['sometimes', 'nullable', 'string', 'max:108'],

            // NON permettere type (deriva da ERP)
            // 'type' => ...

            'is_active' => ['sometimes', 'boolean'],
            'no_backorder' => ['sometimes', 'boolean'],
            'stock_qty' => ['sometimes', 'numeric', 'min:0'],

            'codgrupfis_mg61' => ['sometimes', 'nullable', 'string', 'max:12'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:40'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:4'],

            'erp_lastchange' => ['sometimes', 'nullable', 'date_format:Y-m-d H:i:s'],
            'erp_dataultimoagg' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Models\Product|null $product */
            $product = $this->route('product');

            if (!$product) {
                return;
            }

            // Se è configurable, parent_code deve restare null
            if ($product->type === 'configurable' && $this->has('parent_code') && $this->input('parent_code')) {
                $validator->errors()->add('parent_code', 'Un prodotto configurable non deve avere parent_code.');
            }
        });
    }
}