<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Support\MediaUrl;
use Illuminate\Support\Str;

class Store extends Model
{
    protected $fillable = [
        'ditta_cg18',
        'erp_site_code',
        'company_code',
        'site_code',
        'domain',
        'name',
        'is_b2b',
        'theme',
        'default_locale',
        'supported_locales',
        'is_active',
    ];

    protected $casts = [
        'ditta_cg18' => 'integer',
        'erp_site_code' => 'integer',
        'is_b2b' => 'boolean',
        'is_active' => 'boolean',
        'supported_locales' => 'array',
    ];

    protected $appends = [
        'logo_url',
    ];

    public function shippingRules(): BelongsToMany
    {
        return $this->belongsToMany(
            ShippingRule::class,
            'shipping_rule_store',
            'store_id',
            'shipping_rule_id'
        )->withTimestamps();
    }

    public function getLogoUrlAttribute(): string
    {
        return MediaUrl::url($this->logoPath()) ?? '';
    }

    private function logoPath(): string
    {
        $siteCode = strtoupper((string) ($this->site_code ?? ''));
        $companyCode = strtoupper((string) ($this->company_code ?? ''));
        $theme = strtolower(trim((string) ($this->theme ?? '')));

        return match (true) {
            str_contains($siteCode, 'CIAK'),
            str_contains($companyCode, 'CIAK'),
            $theme === 'ciak' => 'loghi/ciak/ciak.png',

            str_contains($siteCode, 'TEKNIKO'),
            str_contains($companyCode, 'TEKNIKO'),
            $theme === 'teknikoshop',
            $theme === 'tekniko' => trim((string) config('mail.storefront.stores.teknikoshop.logo')) ?: 'loghi/tekniko/tekniko.png',

            str_contains($siteCode, 'FIPELL'),
            str_contains($companyCode, 'FIPELL'),
            $theme === 'fipell' => 'loghi/fipell/fipell.png',

            str_contains($siteCode, 'INTEMPO'),
            str_contains($companyCode, 'INTEMPO'),
            $theme === 'intempodistribution',
            $theme === 'intempo' => 'loghi/intempo/INTEMPO-LOGO-blu.png',

            default => 'loghi/intempo/INTEMPO-LOGO-blu.png',
        };
    }

    public function supportsLocale(string $locale): bool
    {
        $list = $this->supportedLocales();

        return in_array($locale, $list, true);
    }

    public function defaultLocale(?string $fallback = null): string
    {
        $locale = trim((string) ($this->default_locale ?: $fallback ?: 'it'));

        return $locale !== '' ? $locale : 'it';
    }

    public function supportedLocales(?string $fallback = null): array
    {
        $locales = $this->supported_locales ?: [$this->defaultLocale($fallback)];

        $supportedLocales = collect($locales)
            ->map(fn ($locale) => trim((string) $locale))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $supportedLocales ?: [$this->defaultLocale($fallback)];
    }

    public function isB2B(): bool
    {
        return (bool) $this->is_b2b;
    }

    public function isB2C(): bool
    {
        return !$this->isB2B();
    }

    public function channel(): string
    {
        return $this->isB2B() ? 'b2b' : 'b2c';
    }

    public function channelLabel(): string
    {
        return strtoupper($this->channel());
    }

    public function cartLifetimeDays(): int
    {
        return $this->isB2B() ? 30 : 7;
    }

    public function priceDecimals(): int
    {
        return $this->isB2B() ? 3 : 2;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
