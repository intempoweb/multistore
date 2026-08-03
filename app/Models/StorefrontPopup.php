<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class StorefrontPopup extends Model
{
    public const SCOPE_ALL = 'all';
    public const SCOPE_HOME = 'home';
    public const SCOPE_CATALOG = 'catalog';
    public const SCOPE_PRODUCT = 'product';
    public const SCOPE_CATEGORY = 'category';
    public const SCOPE_CART = 'cart';
    public const SCOPE_CHECKOUT = 'checkout';
    public const SCOPE_ACCOUNT = 'account';

    public const FREQUENCY_ALWAYS = 'always';
    public const FREQUENCY_ONCE_SESSION = 'once_session';
    public const FREQUENCY_ONCE_DAY = 'once_day';
    public const FREQUENCY_ONCE_FOREVER = 'once_forever';

    public const SCOPES = [
        self::SCOPE_ALL => 'Tutte le pagine',
        self::SCOPE_HOME => 'Solo homepage',
        self::SCOPE_CATALOG => 'Catalogo e ricerca',
        self::SCOPE_CATEGORY => 'Categorie',
        self::SCOPE_PRODUCT => 'Schede prodotto',
        self::SCOPE_CART => 'Carrello',
        self::SCOPE_CHECKOUT => 'Checkout',
        self::SCOPE_ACCOUNT => 'Area personale',
    ];

    public const FREQUENCIES = [
        self::FREQUENCY_ALWAYS => 'Sempre',
        self::FREQUENCY_ONCE_SESSION => 'Una volta per sessione',
        self::FREQUENCY_ONCE_DAY => 'Una volta al giorno',
        self::FREQUENCY_ONCE_FOREVER => 'Una volta finche non cambia',
    ];

    protected $fillable = [
        'store_id',
        'name',
        'display_scope',
        'frequency',
        'position',
        'image_url',
        'cta_url',
        'open_in_new_tab',
        'background_color',
        'text_color',
        'button_background_color',
        'button_text_color',
        'delay_ms',
        'priority',
        'is_active',
        'starts_at',
        'ends_at',
        'meta',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'open_in_new_tab' => 'boolean',
        'delay_ms' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'meta' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(
            Store::class,
            'storefront_popup_store',
            'storefront_popup_id',
            'store_id'
        )->withTimestamps();
    }

    public function translations(): HasMany
    {
        return $this->hasMany(StorefrontPopupTranslation::class);
    }

    public function scopeForStore(Builder $query, Store $store): Builder
    {
        return $query->where(function (Builder $q) use ($store) {
            $q->where('store_id', $store->id)
                ->orWhereHas('stores', fn (Builder $storeQuery) => $storeQuery->whereKey($store->id));
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeValid(Builder $query, ?Carbon $now = null): Builder
    {
        $now ??= now();

        return $query
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('priority')->orderByDesc('id');
    }

    public function localized(string $locale, ?string $fallbackLocale = null): array
    {
        $fallbackLocale = $fallbackLocale ?: 'it';
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        $translation = $translations->firstWhere('locale', $locale)
            ?: $translations->firstWhere('locale', $fallbackLocale)
            ?: $translations->first();

        return [
            'title' => $translation?->title,
            'subtitle' => $translation?->subtitle,
            'body' => $translation?->body,
            'cta_label' => $translation?->cta_label,
        ];
    }
}
