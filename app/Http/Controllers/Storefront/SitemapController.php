<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Repositories\Storefront\CatalogRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    private const PER_FILE = 20000;

    public function __construct(private CatalogRepository $catalogRepository)
    {
    }

    public function index(): Response
    {
        $store = current_store();
        $locales = $store->supportedLocales('it');
        $productCount = $this->productQuery($store)->count();
        $items = [];

        foreach ($locales as $locale) {
            $items[] = url("sitemaps/catalog/{$locale}.xml");
            foreach (range(1, max(1, (int) ceil($productCount / self::PER_FILE))) as $page) {
                $items[] = url("sitemaps/products/{$locale}/{$page}.xml");
            }
        }

        $body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $body .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($items as $item) {
            $body .= '<sitemap><loc>' . e($item) . '</loc></sitemap>';
        }
        $body .= '</sitemapindex>';

        return $this->xml($body);
    }

    public function catalog(string $locale): Response
    {
        $store = current_store();
        abort_unless($store->supportsLocale($locale), 404);
        $urls = [url("{$locale}/catalog")];

        foreach ($this->catalogRepository->getRootCategories($store, $locale) as $root) {
            $urls[] = url("{$locale}/{$root['slug']}");
            foreach ($this->catalogRepository->getChildrenCategories($store, $locale, $root['fam_code']) as $child) {
                $urls[] = url("{$locale}/{$child['slug']}");
                foreach ($this->catalogRepository->getChildrenCategories($store, $locale, $root['fam_code'], $child['sfam_code']) as $group) {
                    $urls[] = url("{$locale}/{$group['slug']}");
                }
            }
        }

        return $this->urlset(collect($urls)->unique()->values()->all());
    }

    public function products(string $locale, int $page): Response
    {
        $store = current_store();
        abort_unless($store->supportsLocale($locale) && $page > 0, 404);
        $products = $this->productQuery($store)
            ->orderBy('id')
            ->forPage($page, self::PER_FILE)
            ->get(['sku', 'updated_at']);
        abort_if($products->isEmpty() && $page > 1, 404);

        $body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $body .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($products as $product) {
            $body .= '<url><loc>' . e(url("{$locale}/product/" . rawurlencode((string) $product->sku))) . '</loc>';
            if ($product->updated_at) {
                $body .= '<lastmod>' . $product->updated_at->toAtomString() . '</lastmod>';
            }
            $body .= '</url>';
        }
        $body .= '</urlset>';

        return $this->xml($body);
    }

    private function productQuery(Store $store): Builder
    {
        return Product::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('site_type', (int) $store->erp_site_code)
            ->where('is_active', true)
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
        $body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $body .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            $body .= '<url><loc>' . e($url) . '</loc></url>';
        }
        $body .= '</urlset>';

        return $this->xml($body);
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
