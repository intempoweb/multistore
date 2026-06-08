<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Store;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin.only già blocca
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'no_backorder' => filter_var($this->input('no_backorder'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
        ]);
    }

    public function rules(): array
    {
        return [
            'ditta_cg18' => ['required', 'integer', 'min:1'],
            'site_type'  => ['required', 'integer', 'min:1'],

            'sku'        => ['required', 'string', 'max:25'],
            'parent_code'=> ['nullable', 'string', 'max:108'],

            'type'       => ['required', Rule::in(['simple', 'configurable'])],

            'is_active'    => ['required', 'boolean'],
            'no_backorder' => ['required', 'boolean'],

            'stock_qty' => ['nullable', 'numeric', 'min:0'],

            'codgrupfis_mg61' => ['nullable', 'string', 'max:12'], // ERP varchar(12)
            'barcode' => ['nullable', 'string', 'max:40'],         // ERP varchar(40)
            'unit'    => ['nullable', 'string', 'max:4'],          // ERP char(4)

            'erp_lastchange'    => ['nullable', 'date_format:Y-m-d H:i:s'],
            'erp_dataultimoagg' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');
            $parent = $this->input('parent_code');

            // Coerenza type/parent
            if ($type === 'configurable' && $parent) {
                $validator->errors()->add('parent_code', 'Un prodotto configurable non deve avere parent_code.');
            }

            // Validazione store ERP (ditta + site_type devono esistere in stores)
            $ditta = (int) $this->input('ditta_cg18');
            $site  = (int) $this->input('site_type');

            $exists = Store::query()
                ->where('ditta_cg18', $ditta)
                ->where('erp_site_code', $site)
                ->exists();

            if (!$exists) {
                $validator->errors()->add('site_type', "Store ERP non valido: ditta_cg18={$ditta} + site_type={$site} non esiste in stores.");
            }
        });
    }
}