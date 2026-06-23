<?php

namespace App\Services\Storefront\ViewData;

use App\Models\Store;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\StorefrontContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Throwable;

final class StorefrontChromeDataBuilder
{
    private array $navigationByStoreAndLocale = [];

    public function __construct(
        private StorefrontContext $context,
        private CatalogRepository $catalogRepository,
        private Request $request,
    ) {}

    public function build(array $existing = []): array
    {
        $store = $existing['store'] ?? $this->context->store();
        $locale = (string) ($existing['locale'] ?? $this->context->locale());
        $agentContextId = (string) ($existing['agentContextId'] ?? $this->request->input('agent_context', ''));
        $contextParams = $existing['contextParams'] ?? ($agentContextId !== '' ? ['agent_context' => $agentContextId] : []);
        $navigationTree = collect($existing['navigationTree'] ?? []);

        if ($navigationTree->isEmpty()) {
            $navigationTree = $this->navigationTree($store, $locale);
        }

        $availableLocales = $this->availableLocales($existing, $store);
        $supportedLocales = collect($store->supported_locales ?: [$store->default_locale ?: $locale])
            ->filter()
            ->unique()
            ->values();
        $localizedLocaleUrls = $supportedLocales->mapWithKeys(fn (string $supportedLocale) => [
            $supportedLocale => LaravelLocalization::getLocalizedURL($supportedLocale, $this->request->url()),
        ]);
        $visibleNavigationTree = $navigationTree
            ->filter(fn ($category) => filled($category['label'] ?? null) && filled($category['slug'] ?? null))
            ->values();
        $splitAt = (int) ceil($visibleNavigationTree->count() / 2);

        return [
            'store' => $store,
            'locale' => $locale,
            'storeName' => $store->name ?? config('app.name', 'Store'),
            'storeLogo' => media_url($store->logo_url),
            'isB2b' => (bool) $store->is_b2b,
            'cartCount' => (float) ($existing['cartCount'] ?? 0),
            'searchQuery' => trim((string) $this->request->query('q', '')),
            'agentContextId' => $agentContextId,
            'contextParams' => $contextParams,
            'navigationTree' => $navigationTree,
            'availableLocales' => $availableLocales,
            'supportedLocales' => $supportedLocales,
            'localizedLocaleUrls' => $localizedLocaleUrls,
            'currentUrl' => $this->request->url(),
            'leftCategories' => $visibleNavigationTree->take($splitAt),
            'rightCategories' => $visibleNavigationTree->slice($splitAt),
            'footerCategories' => $visibleNavigationTree->take(4),
            'companyName' => $store->company_name ?? $store->ragione_sociale ?? $store->name,
            'companyAddress' => $store->address ?? $store->company_address ?? null,
            'companyVat' => $store->vat_number ?? $store->piva ?? $store->partita_iva ?? null,
            'companyEmail' => $store->email ?? $store->company_email ?? null,
            'companyPhone' => $store->phone ?? $store->company_phone ?? null,
            'storeEmail' => $store->email ?? $store->support_email ?? $store->customer_service_email ?? null,
            'storePhone' => $store->phone ?? $store->telephone ?? $store->customer_service_phone ?? null,
            'storeVat' => $store->vat_number ?? $store->piva ?? $store->vat ?? null,
            'documentsUrl' => route('storefront.account.documents.index', $contextParams),
            'footerSocials' => $this->footerSocials($existing, $store),
            'currentYear' => (int) date('Y'),
        ];
    }

    private function navigationTree(Store $store, string $locale): Collection
    {
        $runtimeKey = implode(':', [(int) $store->id, $locale]);

        if (isset($this->navigationByStoreAndLocale[$runtimeKey])) {
            return $this->navigationByStoreAndLocale[$runtimeKey];
        }

        $cacheKey = implode(':', [
            'storefront-navigation-tree',
            (int) $store->id,
            (int) $store->ditta_cg18,
            (int) $store->erp_site_code,
            $locale,
        ]);

        try {
            $items = Cache::remember($cacheKey, now()->addMinutes(30), fn () => $this->catalogRepository->getNavigationTree($store, $locale)->all()
            );
        } catch (Throwable) {
            $items = [];
        }

        return $this->navigationByStoreAndLocale[$runtimeKey] = collect($items);
    }

    private function availableLocales(array $existing, Store $store): Collection
    {
        return collect($existing['availableLocales'] ?? $store->locales ?? $store->available_locales ?? [])
            ->map(function ($localeItem, $key) {
                if (is_array($localeItem)) {
                    $code = trim((string) ($localeItem['code'] ?? $key));

                    return [
                        'code' => $code,
                        'label' => (string) ($localeItem['label'] ?? strtoupper($code)),
                        'url' => $localeItem['url'] ?? null,
                    ];
                }

                $code = trim((string) $localeItem);

                return ['code' => $code, 'label' => strtoupper($code), 'url' => null];
            })
            ->filter(fn (array $localeItem) => ($localeItem['code'] ?? '') !== '')
            ->values();
    }

    private function footerSocials(array $existing, Store $store): Collection
    {
        return collect($existing['footerSocials'] ?? $store->social_links ?? $store->socials ?? [])
            ->map(function ($item, $key) {
                $label = is_array($item)
                    ? (string) ($item['label'] ?? $key)
                    : (is_string($key) ? $key : 'Social');
                $icon = is_array($item) ? (string) ($item['icon'] ?? '') : '';

                return [
                    'label' => $label,
                    'url' => is_array($item) ? ($item['url'] ?? null) : (is_string($item) ? $item : null),
                    'icon_class' => $icon !== '' ? $icon : $this->socialIcon($label),
                ];
            })
            ->filter(fn (array $item) => filled($item['url'] ?? null))
            ->values();
    }

    private function socialIcon(string $label): string
    {
        return match (strtolower($label)) {
            'facebook' => 'fa-brands fa-facebook-f',
            'instagram' => 'fa-brands fa-instagram',
            'linkedin' => 'fa-brands fa-linkedin-in',
            'youtube' => 'fa-brands fa-youtube',
            'tiktok' => 'fa-brands fa-tiktok',
            'x', 'twitter' => 'fa-brands fa-x-twitter',
            default => 'fa-solid fa-link',
        };
    }
}
