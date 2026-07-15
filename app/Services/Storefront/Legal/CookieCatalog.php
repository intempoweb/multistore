<?php

namespace App\Services\Storefront\Legal;

use App\Models\Store;

class CookieCatalog
{
    public function forStore(?Store $store = null): array
    {
        $categories = $this->categories();
        $cookies = collect($this->technicalCookies());
        $clearPatterns = [
            'analytics' => [],
            'marketing' => [],
            'third_party' => [],
        ];

        if ($this->googleAnalyticsEnabled()) {
            $cookies = $cookies->merge($this->googleAnalyticsCookies());
            $clearPatterns['analytics'] = array_merge($clearPatterns['analytics'], ['_ga', '_ga_', '_gid', '_gat']);
        }

        if ($this->googleAdsEnabled()) {
            $cookies = $cookies->merge($this->googleAdsCookies());
            $clearPatterns['marketing'] = array_merge($clearPatterns['marketing'], ['_gcl_', '_gac_', 'IDE']);
        }

        if ($this->googleMapsEnabled()) {
            $cookies = $cookies->merge($this->googleMapsCookies());
        }

        if ($this->instagramEnabled($store)) {
            $cookies = $cookies->merge($this->instagramCookies());
        }

        if ($this->recaptchaEnabled()) {
            $cookies = $cookies->merge($this->recaptchaCookies());
        }

        if ($this->stripeEnabled()) {
            $cookies = $cookies->merge($this->stripeCookies());
            $clearPatterns['third_party'] = array_merge($clearPatterns['third_party'], ['__stripe_mid', '__stripe_sid']);
        }

        if ($this->paypalEnabled()) {
            $cookies = $cookies->merge($this->paypalCookies());
        }

        $availableCategories = $cookies
            ->pluck('category')
            ->unique()
            ->values()
            ->all();

        return [
            'categories' => collect($categories)
                ->filter(fn (array $category) => in_array($category['key'], $availableCategories, true))
                ->values()
                ->all(),
            'cookies' => $cookies->values()->all(),
            'clear_patterns' => $clearPatterns,
        ];
    }

    private function categories(): array
    {
        return [
            [
                'key' => 'necessary',
                'label' => __('legal.cookies.categories.necessary.label'),
                'description' => __('legal.cookies.categories.necessary.description'),
                'required' => true,
            ],
            [
                'key' => 'analytics',
                'label' => __('legal.cookies.categories.analytics.label'),
                'description' => __('legal.cookies.categories.analytics.description'),
                'required' => false,
            ],
            [
                'key' => 'marketing',
                'label' => __('legal.cookies.categories.marketing.label'),
                'description' => __('legal.cookies.categories.marketing.description'),
                'required' => false,
            ],
            [
                'key' => 'third_party',
                'label' => __('legal.cookies.categories.third_party.label'),
                'description' => __('legal.cookies.categories.third_party.description'),
                'required' => false,
            ],
        ];
    }

    private function technicalCookies(): array
    {
        return [
            [
                'category' => 'necessary',
                'service' => __('legal.cookies.services.platform'),
                'provider' => config('app.name', 'Storefront'),
                'names' => collect([
                    config('session.cookie', 'laravel_session'),
                    'XSRF-TOKEN',
                    'cart_token',
                    config('legal.cookie_consent.name', 'storefront_cookie_consent'),
                ])->filter()->unique()->values()->all(),
                'duration' => __('legal.cookies.durations.session_or_configured'),
                'purpose' => __('legal.cookies.purposes.technical'),
            ],
        ];
    }

    private function googleAnalyticsCookies(): array
    {
        return [[
            'category' => 'analytics',
            'service' => __('legal.cookies.services.google_analytics'),
            'provider' => 'Google Ireland Limited',
            'names' => ['_ga', '_ga_*', '_gid', '_gat'],
            'duration' => __('legal.cookies.durations.google_analytics'),
            'purpose' => __('legal.cookies.purposes.analytics'),
        ]];
    }

    private function googleAdsCookies(): array
    {
        return [[
            'category' => 'marketing',
            'service' => __('legal.cookies.services.google_ads'),
            'provider' => 'Google Ireland Limited',
            'names' => ['_gcl_au', '_gcl_aw', '_gac_*', 'IDE'],
            'duration' => __('legal.cookies.durations.google_ads'),
            'purpose' => __('legal.cookies.purposes.marketing'),
        ]];
    }

    private function googleMapsCookies(): array
    {
        return [[
            'category' => 'third_party',
            'service' => __('legal.cookies.services.google_maps'),
            'provider' => 'Google Ireland Limited',
            'names' => ['NID', 'AEC', 'SOCS'],
            'duration' => __('legal.cookies.durations.third_party'),
            'purpose' => __('legal.cookies.purposes.maps'),
        ]];
    }

    private function instagramCookies(): array
    {
        return [[
            'category' => 'third_party',
            'service' => __('legal.cookies.services.instagram'),
            'provider' => 'Meta Platforms Ireland Limited',
            'names' => ['ig_did', 'mid', 'csrftoken'],
            'duration' => __('legal.cookies.durations.third_party'),
            'purpose' => __('legal.cookies.purposes.instagram'),
        ]];
    }

    private function recaptchaCookies(): array
    {
        return [[
            'category' => 'necessary',
            'service' => 'Google reCAPTCHA',
            'provider' => 'Google Ireland Limited',
            'names' => ['_GRECAPTCHA'],
            'duration' => __('legal.cookies.durations.third_party'),
            'purpose' => __('legal.cookies.purposes.security'),
        ]];
    }

    private function stripeCookies(): array
    {
        return [[
            'category' => 'third_party',
            'service' => 'Stripe',
            'provider' => 'Stripe Payments Europe, Ltd.',
            'names' => ['__stripe_mid', '__stripe_sid'],
            'duration' => __('legal.cookies.durations.stripe'),
            'purpose' => __('legal.cookies.purposes.payment'),
        ]];
    }

    private function paypalCookies(): array
    {
        return [[
            'category' => 'third_party',
            'service' => 'PayPal',
            'provider' => 'PayPal Europe S.à r.l. et Cie, S.C.A.',
            'names' => ['ts', 'ts_c', 'x-pp-s'],
            'duration' => __('legal.cookies.durations.third_party'),
            'purpose' => __('legal.cookies.purposes.payment'),
        ]];
    }

    private function googleAnalyticsEnabled(): bool
    {
        return filled(config('services.google_analytics.measurement_id'))
            || filled(env('GOOGLE_ANALYTICS_ID'))
            || filled(env('GOOGLE_TAG_ID'))
            || filled(env('GA_MEASUREMENT_ID'));
    }

    private function googleAdsEnabled(): bool
    {
        return filled(config('services.google_ads.conversion_id'))
            || filled(env('GOOGLE_ADS_ID'))
            || filled(env('GOOGLE_ADS_CONVERSION_ID'))
            || filled(env('AW_CONVERSION_ID'));
    }

    private function googleMapsEnabled(): bool
    {
        return filled(config('services.google_maps.api_key'))
            || filled(config('services.google_maps.geocoding_api_key'))
            || filled(env('GOOGLE_MAPS_API_KEY'));
    }

    private function instagramEnabled(?Store $store): bool
    {
        return filled(config('services.instagram.access_token'))
            && (!$store || (string) $store->theme === 'ciak');
    }

    private function recaptchaEnabled(): bool
    {
        return (bool) config('services.recaptcha.enabled')
            && filled(config('services.recaptcha.site_key'));
    }

    private function stripeEnabled(): bool
    {
        return filled(config('services.stripe.key'));
    }

    private function paypalEnabled(): bool
    {
        return filled(config('services.paypal.client_id'));
    }
}
