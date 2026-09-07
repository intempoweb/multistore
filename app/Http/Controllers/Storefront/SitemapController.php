<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\StorefrontSeoEntry;
use App\Models\StoreVisibleGroup;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\Seo\StorefrontSeoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class SitemapController extends Controller
{
    private const PER_FILE = 20000;

    public function __construct(private CatalogRepository $catalogRepository) {}

    public function index(): Response
    {
        $store = current_store();
        $locales = $store->supportedLocales('it');
        $productCount = $this->productQuery($store)->count();
        $items = [];

        foreach ($locales as $locale) {
            $items[] = url("sitemaps/catalog/{$locale}.xml");

            if ($productCount > 0) {
                foreach (range(1, (int) ceil($productCount / self::PER_FILE)) as $page) {
                    $items[] = url("sitemaps/products/{$locale}/{$page}.xml");
                }
            }
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $body .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($items as $item) {
            $body .= '<sitemap><loc>'.e($item).'</loc></sitemap>';
        }
        $body .= '</sitemapindex>';

        return $this->xml($body);
    }

    public function catalog(string $locale): Response
    {
        $store = current_store();
        abort_unless($store->supportsLocale($locale), 404);

        $noindexCollectionKeys = $this->noindexKeys($store, $locale, 'collection');
        $urls = [['loc' => $this->localizedUrl($locale, '/'), 'changefreq' => 'weekly', 'priority' => '1.0']];

        if (! $this->isNoindex($store, $locale, 'catalog', 'catalog')) {
            $urls[] = ['loc' => $this->localizedUrl($locale, '/catalog'), 'changefreq' => 'weekly', 'priority' => '0.8'];
        }

        foreach ($this->catalogRepository->getRootCategories($store, $locale) as $root) {
            $rootKey = StorefrontSeoService::categoryKey($root['path']);
            if (! $noindexCollectionKeys->contains($rootKey)) {
                $urls[] = ['loc' => $this->localizedUrl($locale, $root['slug']), 'changefreq' => 'weekly', 'priority' => '0.7'];
            }

            foreach ($this->catalogRepository->getChildrenCategories($store, $locale, $root['fam_code']) as $child) {
                $childKey = StorefrontSeoService::categoryKey($child['path']);
                if (! $noindexCollectionKeys->contains($childKey)) {
                    $urls[] = ['loc' => $this->localizedUrl($locale, $child['slug']), 'changefreq' => 'weekly', 'priority' => '0.6'];
                }

                foreach ($this->catalogRepository->getChildrenCategories($store, $locale, $root['fam_code'], $child['sfam_code']) as $group) {
                    $groupKey = StorefrontSeoService::categoryKey($group['path']);
                    if (! $noindexCollectionKeys->contains($groupKey)) {
                        $urls[] = ['loc' => $this->localizedUrl($locale, $group['slug']), 'changefreq' => 'weekly', 'priority' => '0.5'];
                    }
                }
            }
        }

        foreach ($this->staticPages($store, $locale) as $page) {
            $urls[] = $page;
        }

        return $this->urlset(collect($urls)->unique('loc')->values()->all());
    }

    public function products(string $locale, int $page): Response
    {
        $store = current_store();
        abort_unless($store->supportsLocale($locale) && $page > 0, 404);
        $products = $this->productQuery($store)
            ->with(['translations' => fn ($query) => $query->whereIn('locale', $this->localesForProductSlug($locale))])
            ->orderBy('id')
            ->forPage($page, self::PER_FILE)
            ->get(['id', 'ditta_cg18', 'site_type', 'sku', 'type', 'updated_at', 'flgofferta_webt01', 'flgpromo_webt01']);
        abort_if($products->isEmpty() && $page > 1, 404);

        $urls = $products->map(function (Product $product) use ($store, $locale) {
            $canonicalProduct = $this->canonicalProductForSitemap($store, $product, $locale);

            if (! $canonicalProduct instanceof Product) {
                return null;
            }

            $onPromo = (bool) $canonicalProduct->flgofferta_webt01 || (bool) $canonicalProduct->flgpromo_webt01;

            return [
                'loc' => $this->localizedUrl($locale, '/product/'.$this->catalogRepository->buildProductSlug($canonicalProduct, $locale)),
                'lastmod' => $canonicalProduct->updated_at?->toAtomString(),
                'changefreq' => $onPromo ? 'weekly' : 'monthly',
                'priority' => $onPromo ? '0.8' : '0.6',
            ];
        })->filter()->unique('loc')->values()->all();

        return $this->urlset($urls);
    }

    /**
     * Pagine statiche (StorefrontPage) attive per lo store/locale, escluse quelle marcate noindex.
     */
    private function staticPages(Store $store, string $locale): array
    {
        $pages = StorefrontPage::query()
            ->where('store_id', $store->id)
            // esclude wrapper funzionali non indicizzabili (login, home già gestita a parte)
            ->where('template', 'brand-page')
            ->when(! $store->isB2B(), fn (Builder $query) => $query->with('translations'))
            ->active()
            ->get();

        return $pages
            ->map(function (StorefrontPage $page) use ($store, $locale) {
                $slug = $store->isB2B()
                    ? $page->slug
                    : ($page->translation($locale)?->slug ?: $page->translations->first()?->slug);
                $slug = trim((string) $slug, '/');

                if ($slug === '') {
                    return null;
                }

                return [
                    'loc' => $this->localizedUrl($locale, $slug),
                    'lastmod' => $page->updated_at?->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.5',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Chiavi entity_key con robots "noindex" per un dato entity_type (es. "collection", "catalog").
     */
    private function noindexKeys(Store $store, string $locale, string $entityType): Collection
    {
        return StorefrontSeoEntry::query()
            ->where('store_id', $store->id)
            ->where('locale', $locale)
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->where('robots', 'like', 'noindex%')
            ->pluck('entity_key');
    }

    private function isNoindex(Store $store, string $locale, string $entityType, string $entityKey): bool
    {
        return StorefrontSeoEntry::query()
            ->where('store_id', $store->id)
            ->where('locale', $locale)
            ->where('entity_type', $entityType)
            ->where('entity_key', $entityKey)
            ->where('is_active', true)
            ->where('robots', 'like', 'noindex%')
            ->exists();
    }

    private function productQuery(Store $store): Builder
    {
        return Product::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('site_type', (int) $store->erp_site_code)
            ->where('is_active', true)
            ->when($store->isB2B(), function (Builder $query) use ($store) {
                $visibleGroupCodes = $this->visibleGroupCodes($store);

                $query->where(function (Builder $outer) use ($visibleGroupCodes) {
                    $outer->where(function (Builder $simple) use ($visibleGroupCodes) {
                        $simple->where('type', 'simple')
                            ->where(function (Builder $standalone) {
                                $standalone->whereNull('parent_code')
                                    ->orWhereNotExists(function ($parents) {
                                        $parents->selectRaw('1')
                                            ->from('products as sitemap_parents')
                                            ->whereColumn('sitemap_parents.sku', 'products.parent_code')
                                            ->whereColumn('sitemap_parents.ditta_cg18', 'products.ditta_cg18')
                                            ->whereColumn('sitemap_parents.site_type', 'products.site_type')
                                            ->where('sitemap_parents.type', 'configurable')
                                            ->where('sitemap_parents.is_active', true);
                                    });
                            })
                            ->where(function (Builder $visibility) use ($visibleGroupCodes) {
                                if ($visibleGroupCodes->isNotEmpty()) {
                                    $visibility->whereIn('codgrupfis_mg61', $visibleGroupCodes->all());
                                } else {
                                    $visibility->whereRaw('1 = 0');
                                }

                                $visibility->orWhere(fn (Builder $q) => $q->whereNull('codgrupfis_mg61')->where('fam_99', 'H'));
                            });
                    });

                    $outer->orWhere(function (Builder $configurable) use ($visibleGroupCodes) {
                        $configurable->where('type', 'configurable')
                            ->whereExists(function ($children) use ($visibleGroupCodes) {
                                $children->selectRaw('1')
                                    ->from('products as sitemap_children')
                                    ->whereColumn('sitemap_children.parent_code', 'products.sku')
                                    ->whereColumn('sitemap_children.ditta_cg18', 'products.ditta_cg18')
                                    ->whereColumn('sitemap_children.site_type', 'products.site_type')
                                    ->where('sitemap_children.type', 'simple')
                                    ->where('sitemap_children.is_active', true)
                                    ->where(function ($visibility) use ($visibleGroupCodes) {
                                        if ($visibleGroupCodes->isNotEmpty()) {
                                            $visibility->whereIn('sitemap_children.codgrupfis_mg61', $visibleGroupCodes->all());
                                        } else {
                                            $visibility->whereRaw('1 = 0');
                                        }

                                        $visibility->orWhere(fn ($q) => $q->whereNull('sitemap_children.codgrupfis_mg61')->where('sitemap_children.fam_99', 'H'));
                                    });
                            });
                    });
                });
            })
            ->when($store->isB2C(), function (Builder $query) {
                $query->where(function (Builder $visible) {
                    $visible->where(fn (Builder $simple) => $simple
                        ->where('type', 'simple')
                        ->where(function (Builder $standalone) {
                            $standalone->whereNull('parent_code')
                                ->orWhereNotExists(function ($parents) {
                                    $parents->selectRaw('1')
                                        ->from('products as sitemap_parents')
                                        ->whereColumn('sitemap_parents.sku', 'products.parent_code')
                                        ->whereColumn('sitemap_parents.ditta_cg18', 'products.ditta_cg18')
                                        ->whereColumn('sitemap_parents.site_type', 'products.site_type')
                                        ->where('sitemap_parents.type', 'configurable')
                                        ->where('sitemap_parents.is_active', true);
                                });
                        }))
                        ->orWhere(function (Builder $configurable) {
                            $configurable->where('type', 'configurable')
                                ->whereExists(function ($children) {
                                    $children->selectRaw('1')
                                        ->from('products as sitemap_children')
                                        ->whereColumn('sitemap_children.parent_code', 'products.sku')
                                        ->whereColumn('sitemap_children.ditta_cg18', 'products.ditta_cg18')
                                        ->whereColumn('sitemap_children.site_type', 'products.site_type')
                                        ->where('sitemap_children.is_active', true);
                                });
                        });
                });
            });
    }

    private function urlset(array $urls): Response
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $body .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            $body .= '<url><loc>'.e($url['loc']).'</loc>';
            if (! empty($url['lastmod'])) {
                $body .= '<lastmod>'.$url['lastmod'].'</lastmod>';
            }
            if (! empty($url['changefreq'])) {
                $body .= '<changefreq>'.$url['changefreq'].'</changefreq>';
            }
            if (! empty($url['priority'])) {
                $body .= '<priority>'.$url['priority'].'</priority>';
            }
            $body .= '</url>';
        }
        $body .= '</urlset>';

        return $this->xml($body);
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function localizedUrl(string $locale, string $path): string
    {
        return LaravelLocalization::getLocalizedURL($locale, '/'.ltrim($path, '/'));
    }

    private function localesForProductSlug(string $locale): array
    {
        return collect([$locale, config('app.fallback_locale', 'en')])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function visibleGroupCodes(Store $store): Collection
    {
        return StoreVisibleGroup::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
            ->pluck('codice_xx32')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values();
    }

    private function canonicalProductForSitemap(Store $store, Product $product, string $locale): ?Product
    {
        if ($product->type !== 'configurable') {
            return $product;
        }

        $query = Product::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('site_type', (int) $store->erp_site_code)
            ->where('parent_code', trim((string) $product->sku))
            ->where('type', 'simple')
            ->where('is_active', true)
            ->with(['translations' => fn ($translation) => $translation->whereIn('locale', $this->localesForProductSlug($locale))])
            ->orderBy('sku');

        if ($store->isB2B()) {
            $visibleGroupCodes = $this->visibleGroupCodes($store);

            $query->where(function (Builder $visibility) use ($visibleGroupCodes) {
                if ($visibleGroupCodes->isNotEmpty()) {
                    $visibility->whereIn('codgrupfis_mg61', $visibleGroupCodes->all());
                } else {
                    $visibility->whereRaw('1 = 0');
                }

                $visibility->orWhere(fn (Builder $q) => $q->whereNull('codgrupfis_mg61')->where('fam_99', 'H'));
            });
        }

        return $query->first(['id', 'sku', 'type', 'updated_at', 'flgofferta_webt01', 'flgpromo_webt01']);
    }
}
