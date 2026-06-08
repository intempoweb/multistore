<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        $path = $this->logoPath();
        $domain = $this->normalizedDomain();

        if ($domain === null) {
            return '/storage/' . $path;
        }

        return $domain . '/storage/' . $path;
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
            $theme === 'teknikoshop' => 'loghi/tekniko/teknikoshop.png',

            str_contains($siteCode, 'FIPELL'),
            str_contains($companyCode, 'FIPELL'),
            $theme === 'fipell' => 'loghi/fipell/fipell.png',

            str_contains($siteCode, 'INTEMPO'),
            str_contains($companyCode, 'INTEMPO'),
            $theme === 'intempodistribution' => 'loghi/intempo/INTEMPO-LOGO-blu.svg',

            default => 'loghi/intempo/INTEMPO-LOGO-blu.svg',
        };
    }

    private function normalizedDomain(): ?string
    {
        $domain = trim((string) ($this->domain ?? ''));

        if ($domain === '') {
            return null;
        }

        if (!str_starts_with($domain, 'http://') && !str_starts_with($domain, 'https://')) {
            $domain = 'https://' . $domain;
        }

        return rtrim($domain, '/');
    }

    public function supportsLocale(string $locale): bool
    {
        $list = $this->supported_locales ?: [];

        return in_array($locale, $list, true);
    }

    public function isB2C(): bool
    {
        return !$this->is_b2b;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}