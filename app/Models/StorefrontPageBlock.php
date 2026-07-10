<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorefrontPageBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'storefront_page_id',
        'type',
        'name',
        'sort_order',
        'is_active',
        'title',
        'subtitle',
        'content',
        'image_path',
        'mobile_image_path',
        'video_path',
        'button_label',
        'button_url',
        'button_new_tab',
        'background_color',
        'text_color',
        'overlay_color',
        'overlay_opacity',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'button_new_tab' => 'boolean',
        'overlay_opacity' => 'integer',
        'sort_order' => 'integer',
        'settings' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(StorefrontPage::class, 'storefront_page_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(StorefrontPageBlockMedia::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activeMedia(): HasMany
    {
        return $this->hasMany(StorefrontPageBlockMedia::class)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(StorefrontPageBlockTranslation::class);
    }

    public function translation(?string $locale): ?StorefrontPageBlockTranslation
    {
        $locale = $this->normalizeLocale($locale);

        if ($locale === null) {
            return null;
        }

        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('locale', $locale);
        }

        return $this->translations()->where('locale', $locale)->first();
    }

    public function translationOrFallback(?string $locale): ?StorefrontPageBlockTranslation
    {
        $locales = array_values(array_unique(array_filter([
            $this->normalizeLocale($locale),
            'it',
            $this->normalizeLocale(config('app.fallback_locale', 'it')),
        ])));

        foreach ($locales as $candidate) {
            $translation = $this->translation($candidate);

            if ($translation) {
                return $translation;
            }
        }

        if ($this->relationLoaded('translations')) {
            return $this->translations->first();
        }

        return $this->translations()->orderBy('locale')->first();
    }

    public function applyTranslation(?string $locale): self
    {
        $translation = $this->translationOrFallback($locale);

        if (! $translation) {
            return $this;
        }

        foreach (['title', 'subtitle', 'content', 'button_label'] as $field) {
            if (filled($translation->{$field})) {
                $this->setAttribute($field, $translation->{$field});
            }
        }

        return $this;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    private function normalizeLocale(?string $locale): ?string
    {
        $locale = strtolower(trim((string) $locale));

        return $locale === '' ? null : $locale;
    }
}
