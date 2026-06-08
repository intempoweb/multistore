<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:80'],

            'type' => [
                'nullable',
                'string',
                Rule::in([
                    'cart_percent',
                    'cart_percentage',
                    'cart_fixed',
                    'cart_amount',
                    'product_percent',
                    'line_percent',
                    'item_percent',
                    'product_fixed',
                    'line_fixed',
                    'item_fixed',
                ]),
            ],

            'discount_type' => [
                'required',
                'string',
                Rule::in(['percent', 'percentage', 'fixed']),
            ],

            'discount_value' => ['required', 'numeric', 'min:0.001'],

            'scope' => [
                'required',
                'string',
                Rule::in(['cart', 'line', 'item', 'product', 'row']),
            ],

            'minimum_subtotal' => ['nullable', 'numeric', 'min:0'],

            'requires_coupon' => ['nullable', 'boolean'],
            'coupon_codes' => ['nullable', 'string', 'max:1000'],

            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],

            'priority' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],

            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    public function prepareForValidation(): void
    {
        $discountType = strtolower(trim((string) $this->input('discount_type', 'fixed')));
        $scope = strtolower(trim((string) $this->input('scope', 'cart')));
        $type = $this->input('type');

        $this->merge([
            'code' => $this->normalizeNullableUpperString($this->input('code')),
            'type' => $type !== null && trim((string) $type) !== ''
                ? strtolower(trim((string) $type))
                : null,
            'discount_type' => $discountType === 'percentage' ? 'percent' : $discountType,
            'scope' => in_array($scope, ['line', 'item', 'product', 'row'], true) ? 'line' : 'cart',
            'requires_coupon' => $this->boolean('requires_coupon'),
            'coupon_codes' => $this->normalizeCouponCodesText($this->input('coupon_codes')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $discountType = (string) $this->input('discount_type');
            $discountValue = (float) $this->input('discount_value', 0);

            if ($discountType === 'percent' && $discountValue > 100) {
                $validator->errors()->add('discount_value', 'Lo sconto percentuale non può superare 100%.');
            }

            if ($this->boolean('requires_coupon') && !$this->filled('coupon_codes')) {
                $validator->errors()->add('coupon_codes', 'Inserisci almeno un codice coupon per una promozione con coupon obbligatorio.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Il nome promozione è obbligatorio.',
            'name.max' => 'Il nome promozione non può superare 255 caratteri.',
            'code.max' => 'Il codice promozione non può superare 80 caratteri.',
            'type.in' => 'Il tipo promozione non è valido.',
            'discount_type.required' => 'Il tipo sconto è obbligatorio.',
            'discount_type.in' => 'Il tipo sconto non è valido.',
            'discount_value.required' => 'Il valore sconto è obbligatorio.',
            'discount_value.numeric' => 'Il valore sconto deve essere numerico.',
            'discount_value.min' => 'Il valore sconto deve essere maggiore di 0.',
            'scope.required' => 'L’ambito dello sconto è obbligatorio.',
            'scope.in' => 'L’ambito dello sconto non è valido.',
            'minimum_subtotal.numeric' => 'Il minimo carrello deve essere numerico.',
            'minimum_subtotal.min' => 'Il minimo carrello deve essere maggiore o uguale a 0.',
            'coupon_codes.max' => 'I codici coupon non possono superare 1000 caratteri.',
            'usage_limit_per_customer.integer' => 'Il limite per cliente deve essere un numero intero.',
            'usage_limit_per_customer.min' => 'Il limite per cliente deve essere almeno 1.',
            'priority.integer' => 'La priorità deve essere un numero intero.',
            'priority.min' => 'La priorità deve essere maggiore o uguale a 0.',
            'starts_at.date' => 'La data inizio non è valida.',
            'ends_at.date' => 'La data fine non è valida.',
            'ends_at.after_or_equal' => 'La data fine deve essere successiva o uguale alla data inizio.',
        ];
    }

    private function normalizeNullableUpperString(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_strtoupper($value);
    }

    private function normalizeCouponCodesText(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $codes = preg_split('/[\s,;]+/', $value) ?: [];

        $normalized = collect($codes)
            ->map(fn ($code) => mb_strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->implode("\n");

        return $normalized !== '' ? $normalized : null;
    }
}