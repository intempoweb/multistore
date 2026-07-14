<?php

namespace App\Services\Storefront\ViewData;

use App\Models\Store;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\LegalProfileResolver;
use App\Services\Storefront\StorefrontContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Throwable;

final class StorefrontChromeDataBuilder
{
    /**
     * Cache runtime separata per store, lingua e cliente.
     *
     * @var array<string, Collection>
     */
    private array $navigationByContext = [];

    public function __construct(
        private StorefrontContext $context,
        private CatalogRepository $catalogRepository,
        private Request $request,
        private LegalProfileResolver $legalProfileResolver,
    ) {
    }

    public function build(array $existing = []): array
    {
        $store = $existing['store']
            ?? $this->context->store();

        $locale = (string) (
            $existing['locale']
            ?? $this->context->locale()
        );

        $agentContextId = (string) (
            $existing['agentContextId']
            ?? $this->request->input('agent_context', '')
        );

        $contextParams = $existing['contextParams']
            ?? (
                $agentContextId !== ''
                    ? ['agent_context' => $agentContextId]
                    : []
            );

        $navigationTree = collect(
            $existing['navigationTree'] ?? []
        );

        if ($navigationTree->isEmpty()) {
            $navigationTree = $this->navigationTree(
                $store,
                $locale
            );
        }

        $legalProfile = $this->legalProfileResolver
            ->resolve($store);

        $privacyUpdatedAt = (string) config(
            'legal.privacy_updated_at',
            '2026-07-03'
        );

        $cookieUpdatedAt = (string) config(
            'legal.cookie_updated_at',
            $privacyUpdatedAt
        );

        $googleAnalyticsEnabled =
            filled(
                config(
                    'services.google_analytics.measurement_id'
                )
            )
            || filled(env('GOOGLE_ANALYTICS_ID'))
            || filled(env('GOOGLE_TAG_ID'))
            || filled(env('GA_MEASUREMENT_ID'));

        $googleAdsEnabled =
            filled(
                config(
                    'services.google_ads.conversion_id'
                )
            )
            || filled(env('GOOGLE_ADS_ID'))
            || filled(env('GOOGLE_ADS_CONVERSION_ID'))
            || filled(env('AW_CONVERSION_ID'));

        $googleMapsEnabled =
            filled(
                config(
                    'services.google_maps.api_key'
                )
            )
            || filled(
                config(
                    'services.google_maps.geocoding_api_key'
                )
            )
            || filled(env('GOOGLE_MAPS_API_KEY'));

        $instagramEnabled =
            filled(
                config(
                    'services.instagram.access_token'
                )
            )
            || filled(env('INSTAGRAM_ACCESS_TOKEN'));

        $availableLocales = $this->availableLocales(
            $existing,
            $store
        );

        $supportedLocales = collect(
            $store->supportedLocales($locale)
        )
            ->filter()
            ->unique()
            ->values();

        $localizedLocaleUrls = $supportedLocales
            ->mapWithKeys(
                fn (string $supportedLocale) => [
                    $supportedLocale =>
                        LaravelLocalization::getLocalizedURL(
                            $supportedLocale,
                            $this->request->url()
                        ),
                ]
            );

        $visibleNavigationTree = $navigationTree
            ->filter(
                fn ($category) =>
                    filled($category['label'] ?? null)
                    && filled($category['slug'] ?? null)
            )
            ->values();

        $splitAt = (int) ceil(
            $visibleNavigationTree->count() / 2
        );

        return [
            'store' => $store,
            'locale' => $locale,

            'storeName' => $store->name
                ?? config('app.name', 'Store'),

            'storeLogo' => media_url(
                $store->logo_url
            ),

            'isB2b' => $store->isB2B(),

            'cartCount' => (float) (
                $existing['cartCount'] ?? 0
            ),

            'searchQuery' => trim(
                (string) $this->request->query('q', '')
            ),

            'agentContextId' => $agentContextId,
            'contextParams' => $contextParams,

            'navigationTree' => $navigationTree,

            'availableLocales' => $availableLocales,
            'supportedLocales' => $supportedLocales,

            'localizedLocaleUrls' =>
                $existing['localizedLocaleUrls']
                ?? $localizedLocaleUrls,

            'currentUrl' => $this->request->url(),

            'leftCategories' =>
                $visibleNavigationTree->take($splitAt),

            'rightCategories' =>
                $visibleNavigationTree->slice($splitAt),

            'footerCategories' =>
                $visibleNavigationTree->take(4),

            'legalProfile' => $legalProfile,

            'companyName' =>
                $legalProfile['company']
                ?? $store->name,

            'companyAddress' => trim(
                implode(
                    ' ',
                    array_filter([
                        $legalProfile['address'] ?? null,
                        $legalProfile['city'] ?? null,
                        $legalProfile['country'] ?? null,
                    ])
                )
            ) ?: null,

            'companyVat' =>
                $legalProfile['vat'] ?? null,

            'companyTaxCode' =>
                $legalProfile['tax_code'] ?? null,

            'companySdi' =>
                $legalProfile['sdi'] ?? null,

            'companyPec' =>
                $legalProfile['pec'] ?? null,

            'companyEmail' =>
                $legalProfile['email'] ?? null,

            'companyPhone' =>
                $legalProfile['phone'] ?? null,

            'companyWebsite' =>
                $legalProfile['website'] ?? null,

            'companyRea' =>
                $legalProfile['rea'] ?? null,

            'companyRegister' =>
                $legalProfile['company_register'] ?? null,

            'storeEmail' =>
                $legalProfile['email'] ?? null,

            'storePhone' =>
                $legalProfile['phone'] ?? null,

            'storeVat' =>
                $legalProfile['vat'] ?? null,

            'storeTaxCode' =>
                $legalProfile['tax_code'] ?? null,

            'legalPrivacyUpdatedAt' =>
                $privacyUpdatedAt,

            'legalCookieUpdatedAt' =>
                $cookieUpdatedAt,

            'legalGoogleAnalyticsEnabled' =>
                $googleAnalyticsEnabled,

            'legalGoogleAdsEnabled' =>
                $googleAdsEnabled,

            'legalGoogleMapsEnabled' =>
                $googleMapsEnabled,

            'legalInstagramEnabled' =>
                $instagramEnabled,

            'documentsUrl' => route(
                'storefront.account.documents.index',
                $contextParams
            ),

            'footerSocials' => $this->footerSocials(
                $existing,
                $store
            ),

            'currentYear' => (int) date('Y'),
        ];
    }

    private function navigationTree(
        Store $store,
        string $locale
    ): Collection {
        /*
         * Il contesto cliente è fondamentale nel B2B:
         * clienti diversi possono avere gruppi visibili diversi.
         */
        $customerContext = $this->context
            ->customerCatalogContext($store);

        $tipocf = $customerContext->tipocf;
        $clifor = $customerContext->clifor;

        /*
         * Cache soltanto per la durata della richiesta.
         * Include il cliente per evitare contaminazioni tra contesti.
         */
        $runtimeKey = implode(':', [
            (int) $store->id,
            (int) $store->ditta_cg18,
            (int) $store->erp_site_code,
            $locale,
            $tipocf !== null
                ? (int) $tipocf
                : 0,
            $clifor !== null
                ? (int) $clifor
                : 0,
        ]);

        if (
            array_key_exists(
                $runtimeKey,
                $this->navigationByContext
            )
        ) {
            return $this->navigationByContext[
                $runtimeKey
            ];
        }

        /*
         * Anche la cache Laravel deve essere separata
         * per cliente, non soltanto per store e lingua.
         */
        $cacheKey = implode(':', [
            'storefront-navigation-tree',
            (int) $store->id,
            (int) $store->ditta_cg18,
            (int) $store->erp_site_code,
            $locale,
            $tipocf !== null
                ? (int) $tipocf
                : 0,
            $clifor !== null
                ? (int) $clifor
                : 0,
        ]);

        try {
            $items = Cache::remember(
                $cacheKey,
                now()->addMinutes(30),
                fn () => $this->catalogRepository
                ->getNavigationTree(
                    $store,
                    $locale,
                    $tipocf,
                    $clifor
                )
                ->all()
            );
        } catch (Throwable) {
            $items = [];
        }

        return $this->navigationByContext[
            $runtimeKey
        ] = collect($items);
    }

    private function availableLocales(
        array $existing,
        Store $store
    ): Collection {
        return collect(
            $existing['availableLocales']
            ?? $store->locales
            ?? $store->available_locales
            ?? []
        )
            ->map(
                function ($localeItem, $key): array {
                    if (is_array($localeItem)) {
                        $code = trim(
                            (string) (
                                $localeItem['code']
                                ?? $key
                            )
                        );

                        return [
                            'code' => $code,
                            'label' => (string) (
                                $localeItem['label']
                                ?? strtoupper($code)
                            ),
                            'url' =>
                                $localeItem['url']
                                ?? null,
                        ];
                    }

                    $code = trim(
                        (string) $localeItem
                    );

                    return [
                        'code' => $code,
                        'label' => strtoupper($code),
                        'url' => null,
                    ];
                }
            )
            ->filter(
                fn (array $localeItem) =>
                    ($localeItem['code'] ?? '') !== ''
            )
            ->values();
    }

    private function footerSocials(
        array $existing,
        Store $store
    ): Collection {
        return collect(
            $existing['footerSocials']
            ?? $store->social_links
            ?? $store->socials
            ?? []
        )
            ->map(
                function ($item, $key): array {
                    $label = is_array($item)
                        ? (string) (
                            $item['label'] ?? $key
                        )
                        : (
                            is_string($key)
                                ? $key
                                : 'Social'
                        );

                    $icon = is_array($item)
                        ? (string) (
                            $item['icon'] ?? ''
                        )
                        : '';

                    return [
                        'label' => $label,

                        'url' => is_array($item)
                            ? ($item['url'] ?? null)
                            : (
                                is_string($item)
                                    ? $item
                                    : null
                            ),

                        'icon_class' => $icon !== ''
                            ? $icon
                            : $this->socialIcon($label),
                    ];
                }
            )
            ->filter(
                fn (array $item) =>
                    filled($item['url'] ?? null)
            )
            ->values();
    }

    private function socialIcon(
        string $label
    ): string {
        return match (strtolower($label)) {
            'facebook' =>
                'fa-brands fa-facebook-f',

            'instagram' =>
                'fa-brands fa-instagram',

            'linkedin' =>
                'fa-brands fa-linkedin-in',

            'youtube' =>
                'fa-brands fa-youtube',

            'tiktok' =>
                'fa-brands fa-tiktok',

            'x', 'twitter' =>
                'fa-brands fa-x-twitter',

            default =>
                'fa-solid fa-link',
        };
    }
}