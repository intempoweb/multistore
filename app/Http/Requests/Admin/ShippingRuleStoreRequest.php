<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShippingRuleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::in(['fixed', 'free_over', 'table']),
            ],
            'country' => [
                'nullable',
                'string',
                'min:2',
                'max:3',
            ],
            'province' => [
                'nullable',
                'string',
                'max:20',
            ],
            'cap' => [
                'nullable',
                'string',
                'max:20',
            ],
            'weight_from' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'min_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'max_amount' => [
                'nullable',
                'numeric',
                'gte:min_amount',
            ],
            'amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'priority' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'type' => strtolower(trim((string) $this->input('type', 'fixed'))),
            'country' => $this->normalizeNullableString($this->input('country'), true),
            'province' => $this->normalizeNullableString($this->input('province'), true),
            'cap' => $this->normalizeNullableString($this->input('cap'), true),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = (string) $this->input('type');

            if ($type === 'free_over' && !$this->filled('min_amount')) {
                $validator->errors()->add('min_amount', 'Per la regola free_over la soglia minima è obbligatoria.');
            }

            if (in_array($type, ['fixed', 'table'], true) && !$this->filled('amount')) {
                $validator->errors()->add('amount', 'Per questa regola il costo spedizione è obbligatorio.');
            }

            if ($type === 'table' && !$this->filled('weight_from')) {
                $validator->errors()->add('weight_from', 'Per la regola table il peso minimo è obbligatorio.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Il tipo regola è obbligatorio.',
            'type.in' => 'Il tipo regola non è valido.',
            'country.min' => 'Il paese deve essere un codice ISO a 2 o 3 lettere.',
            'country.max' => 'Il paese deve essere un codice ISO a 2 o 3 lettere.',
            'province.max' => 'La provincia non può superare 20 caratteri.',
            'cap.max' => 'Il CAP non può superare 20 caratteri.',
            'weight_from.numeric' => 'Il peso minimo deve essere numerico.',
            'min_amount.numeric' => 'La soglia minima deve essere numerica.',
            'max_amount.numeric' => 'La soglia massima deve essere numerica.',
            'max_amount.gte' => 'La soglia massima deve essere maggiore o uguale alla soglia minima.',
            'amount.numeric' => 'L\'importo deve essere numerico.',
            'priority.integer' => 'La priorità deve essere un numero intero.',
        ];
    }

    private function normalizeNullableString(mixed $value, bool $uppercase = false): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $normalized = $uppercase ? strtoupper($value) : $value;
        $upper = strtoupper($normalized);

        if (in_array($upper, ['ALL', '*', '•', '-', '--'], true)) {
            return null;
        }

        return $normalized;
    }
}