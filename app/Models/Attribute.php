<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    protected $fillable = [
        'code',
        'type',
        'is_filterable',
        'is_variant',
        'sort_order',
        'erp_lastchange',
    ];

    protected $casts = [
        'is_filterable'  => 'boolean',
        'is_variant'     => 'boolean',
        'sort_order'     => 'integer',
        'erp_lastchange' => 'datetime',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(AttributeTranslation::class, 'attribute_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class, 'attribute_id')
            ->orderBy('sort_order');
    }

    public function translation(string $locale): ?AttributeTranslation
    {
        return $this->translations()->where('locale', $locale)->first();
    }

    public function translationOrFallback(string $locale, ?string $fallback = null): ?AttributeTranslation
    {
        $fallback ??= config('app.fallback_locale', 'en');

        return $this->translation($locale)
            ?: $this->translation($fallback)
            ?: $this->translations()->first();
    }
}