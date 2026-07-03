<?php
namespace App\Services\Storefront;

use App\Models\Store;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class LegalProfileResolver
{
    public function resolve(?Store $store = null): array
    {
        $profileKey = $this->resolveProfileKey($store);
        $profile = config("legal.profiles.{$profileKey}", []);

        if (empty($profile)) {
            $profileKey = (string) config('legal.default_profile', 'intempo');
            $profile = config("legal.profiles.{$profileKey}", []);
        }

        return array_merge([
            'key' => $profileKey,
            'company' => null,
            'address' => null,
            'city' => null,
            'country' => 'Italia',
            'vat' => null,
            'tax_code' => null,
            'sdi' => null,
            'email' => null,
            'pec' => null,
            'phone' => null,
            'rea' => null,
            'company_register' => null,
            'website' => null,
        ], array_filter($profile, static fn ($value) => $value !== null && $value !== ''));
    }

    public function resolveProfileKey(?Store $store = null): string
    {
        $defaultProfile = (string) config('legal.default_profile', 'intempo');
        $storeProfiles = (array) config('legal.store_profiles', []);

        foreach ($this->storeTokens($store) as $token) {
            if (isset($storeProfiles[$token])) {
                return (string) $storeProfiles[$token];
            }
        }

        return $defaultProfile;
    }

    private function storeTokens(?Store $store): array
    {
        if (!$store) {
            return [];
        }

        $tokens = [
            $store->theme ?? null,
            $store->site_code ?? null,
            $store->company_code ?? null,
            $store->domain ?? null,
            $store->name ?? null,
        ];

        return collect($tokens)
            ->filter(fn ($value) => filled($value))
            ->flatMap(function ($value) {
                $value = Str::lower((string) $value);

                return [
                    $value,
                    Str::slug($value),
                    str_replace(['_', '-', '.', ' '], '', $value),
                    Arr::first(explode('.', $value)),
                ];
            })
            ->filter(fn ($value) => filled($value))
            ->unique()
            ->values()
            ->all();
    }
}