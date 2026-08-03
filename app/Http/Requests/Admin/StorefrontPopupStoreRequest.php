<?php

namespace App\Http\Requests\Admin;

use App\Models\StorefrontPopup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorefrontPopupStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'store_ids' => ['nullable', 'array'],
            'store_ids.*' => ['integer', 'exists:stores,id'],
            'display_scope' => ['required', Rule::in(array_keys(StorefrontPopup::SCOPES))],
            'frequency' => ['required', Rule::in(array_keys(StorefrontPopup::FREQUENCIES))],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'cta_url' => ['nullable', 'string', 'max:2048'],
            'open_in_new_tab' => ['boolean'],
            'background_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'text_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'button_background_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'button_text_color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'delay_ms' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'priority' => ['nullable', 'integer', 'min:-100000', 'max:100000'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'translations' => ['required', 'array'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.subtitle' => ['nullable', 'string', 'max:255'],
            'translations.*.body' => ['nullable', 'string', 'max:5000'],
            'translations.*.cta_label' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'store_ids' => collect((array) $this->input('store_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'open_in_new_tab' => $this->boolean('open_in_new_tab'),
            'is_active' => $this->boolean('is_active'),
            'delay_ms' => $this->filled('delay_ms') ? (int) $this->input('delay_ms') : 0,
            'priority' => $this->filled('priority') ? (int) $this->input('priority') : 0,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $store = app()->bound('adminStore') ? app('adminStore') : null;
            $defaultLocale = $store?->defaultLocale(config('app.fallback_locale', 'it')) ?? 'it';
            $title = data_get($this->input('translations', []), $defaultLocale . '.title');

            if (!filled($title)) {
                $validator->errors()->add(
                    'translations.' . $defaultLocale . '.title',
                    'Il titolo nella lingua principale e obbligatorio.'
                );
            }
        });
    }
}
