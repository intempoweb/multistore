<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorefrontPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'slug',
        'title',
        'description',
        'template',
        'layout',
        'meta_title',
        'meta_description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(StorefrontPageBlock::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activeBlocks(): HasMany
    {
        return $this->hasMany(StorefrontPageBlock::class)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(StorefrontPageTranslation::class);
    }

    public function translation(?string $locale): ?StorefrontPageTranslation
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

    public function translationOrFallback(?string $locale): ?StorefrontPageTranslation
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

        foreach (['slug', 'title', 'description', 'meta_title', 'meta_description'] as $field) {
            if (filled($translation->{$field})) {
                $this->setAttribute($field, $translation->{$field});
            }
        }

        if ($this->relationLoaded('activeBlocks')) {
            $this->setRelation('activeBlocks', $this->activeBlocks->map(function (StorefrontPageBlock $block) use ($locale) {
                return $block->applyTranslation($locale);
            }));
        }

        if ($this->relationLoaded('blocks')) {
            $this->setRelation('blocks', $this->blocks->map(function (StorefrontPageBlock $block) use ($locale) {
                return $block->applyTranslation($locale);
            }));
        }

        return $this;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    private function normalizeLocale(?string $locale): ?string
    {
        $locale = strtolower(trim((string) $locale));

        return $locale === '' ? null : $locale;
    }
}
