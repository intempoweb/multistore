<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CouponStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80'],
            'promotion_id' => ['nullable', 'exists:promotions,id'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->normalizeCode($this->input('code')),
            'promotion_id' => $this->filled('promotion_id') ? $this->input('promotion_id') : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $code = (string) $this->input('code');

            if (!$this->filled('promotion_id') && !preg_match('/\d+$/', $code)) {
                $validator->errors()->add(
                    'code',
                    'Se non associ una promozione, il codice coupon deve terminare con un valore numerico. Esempio: MTBUONO50.'
                );
            }

            if (preg_match('/\d+$/', $code, $matches)) {
                $value = (int) $matches[0];

                if ($value <= 0) {
                    $validator->errors()->add(
                        'code',
                        'Il valore numerico del coupon non è valido.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Il codice coupon è obbligatorio.',
            'code.max' => 'Il codice coupon non può superare 80 caratteri.',
            'promotion_id.exists' => 'La promozione selezionata non è valida.',
            'usage_limit.integer' => 'Il limite utilizzi deve essere un numero intero.',
            'usage_limit.min' => 'Il limite utilizzi deve essere almeno 1.',
            'usage_limit_per_customer.integer' => 'Il limite per cliente deve essere un numero intero.',
            'usage_limit_per_customer.min' => 'Il limite per cliente deve essere almeno 1.',
            'starts_at.date' => 'La data inizio non è valida.',
            'expires_at.date' => 'La data fine non è valida.',
            'expires_at.after_or_equal' => 'La data fine deve essere successiva o uguale alla data inizio.',
        ];
    }

    private function normalizeCode(mixed $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }
}