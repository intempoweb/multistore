<?php

namespace App\Http\Requests\Admin;

use App\Models\Newsletter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewsletterStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'preview_text' => ['nullable', 'string', 'max:255'],
            'locale' => ['required', 'string', 'max:8'],
            'provider' => ['required', Rule::in(array_keys(Newsletter::PROVIDER_LABELS))],
            'selection_type' => ['required', Rule::in(array_keys(Newsletter::SELECTION_LABELS))],
            'selection_types' => ['nullable', 'array'],
            'selection_types.*' => [Rule::in([
                Newsletter::SELECTION_OFFERS,
                Newsletter::SELECTION_PROMOTIONS,
                Newsletter::SELECTION_NEW_PRODUCTS,
                Newsletter::SELECTION_CAMPAIGNS,
            ])],
            'listino_id' => ['nullable', 'integer', 'min:1'],
            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_text' => ['nullable', 'string', 'max:2000'],
            'hero_image_url' => ['nullable', 'string', 'max:2048'],
            'intro_html' => ['nullable', 'string', 'max:8000'],
            'manual_skus' => ['nullable', 'string', 'max:12000'],
            'filters' => ['nullable', 'array'],
            'filters.*' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'listino_id' => $this->filled('listino_id') ? (int) $this->input('listino_id') : null,
            'filters' => collect((array) $this->input('filters', []))
                ->map(fn ($value) => is_string($value) ? trim($value) : $value)
                ->filter(fn ($value) => filled($value))
                ->all(),
        ]);
    }
}
