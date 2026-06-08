<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AttributeValue extends Model
{
    protected $fillable = [
        'attribute_id',
        'value_code',
        'sort_order',
        'erp_lastchange',
    ];

    protected $casts = [
        'attribute_id'   => 'integer',
        'sort_order'     => 'integer',
        'erp_lastchange' => 'datetime',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(AttributeValueTranslation::class, 'attribute_value_id');
    }

    /**
     * Pivot: product_attribute_values
     * NB: se nella pivot tieni anche attribute_id, va bene, ma deve essere coerente.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
                Product::class,
                'product_attribute_values',
                'attribute_value_id',
                'product_id'
            )
            ->withPivot(['attribute_id', 'raw_value', 'value_key', 'erp_lastchange'])
            ->withTimestamps();
    }

    public function mediaAssets(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'mediable')
            ->orderBy('sort_order');
    }

    public function swatch(): ?MediaAsset
    {
        return $this->mediaAssets()
            ->where('role', MediaAsset::ROLE_SWATCH)
            ->first();
    }

    public function translation(string $locale): ?AttributeValueTranslation
    {
        return $this->translations()->where('locale', $locale)->first();
    }

    public function translationOrFallback(string $locale, ?string $fallback = null): ?AttributeValueTranslation
    {
        $fallback ??= config('app.fallback_locale', 'en');

        return $this->translation($locale)
            ?: $this->translation($fallback)
            ?: $this->translations()->first();
    }
}