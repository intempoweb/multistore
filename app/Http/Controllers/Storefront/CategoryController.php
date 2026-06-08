<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        private ThemeResolver $themeResolver,
        private CatalogRepository $catalogRepository,
    ) {
    }

    public function legacy(Request $request, string $slug): RedirectResponse
    {
        $store = app()->bound('currentStore')
            ? app('currentStore')
            : null;

        abort_unless($store, 404, 'Store corrente non disponibile.');

        $locale = app()->getLocale();

        $path = $this->catalogRepository->parseLegacyCategorySlug($slug);

        $seoSlug = $this->catalogRepository->buildCategorySlug(
            store: $store,
            locale: $locale,
            fam: $path['fam'] ?? null,
            sfam: $path['sfam'] ?? null,
            gruppo: $path['gruppo'] ?? null,
            sgruppo: $path['sgruppo'] ?? null,
        );

        abort_if($seoSlug === '', 404, 'Categoria non trovata.');

        return redirect()
            ->route('storefront.category.show', $seoSlug, 301);
    }

    public function show(Request $request, string $slug): View
    {
        $store = app()->bound('currentStore')
            ? app('currentStore')
            : null;

        abort_unless($store, 404, 'Store corrente non disponibile.');

        $locale = app()->getLocale();

        $sort = $this->normalizeSort(
            (string) $request->query('sort', 'default')
        );

        $path = $this->catalogRepository->parseCategorySlug(
            $store,
            $locale,
            $slug
        );

        abort_if(empty($path['fam']), 404, 'Categoria non trovata.');

        $baseFilterFacets = $this->catalogRepository->getCategoryFilterFacets(
            $store,
            $locale,
            $path['fam'] ?? null,
            $path['sfam'] ?? null,
            $path['gruppo'] ?? null,
            $path['sgruppo'] ?? null,
            null,
            null,
            []
        );

        $activeFilters = $this->normalizeSeoFilters(
            $request,
            $baseFilterFacets
        );

        $category = $this->catalogRepository->getCategoryMeta(
            $store,
            $locale,
            $path['fam'] ?? null,
            $path['sfam'] ?? null,
            $path['gruppo'] ?? null,
            $path['sgruppo'] ?? null
        );

        $children = $this->catalogRepository->getChildrenCategories(
            $store,
            $locale,
            $path['fam'] ?? null,
            $path['sfam'] ?? null,
            $path['gruppo'] ?? null
        );

        $filterFacets = $this->catalogRepository->getCategoryFilterFacets(
            $store,
            $locale,
            $path['fam'] ?? null,
            $path['sfam'] ?? null,
            $path['gruppo'] ?? null,
            $path['sgruppo'] ?? null,
            null,
            null,
            $activeFilters
        );

        $products = $this->catalogRepository->getCategoryProducts(
            $store,
            $locale,
            $path['fam'] ?? null,
            $path['sfam'] ?? null,
            $path['gruppo'] ?? null,
            $path['sgruppo'] ?? null,
            null,
            null,
            24,
            $activeFilters,
            $sort
        );

        return view(
            $this->themeResolver->view('category.show', $store),
            [
                'store' => $store,
                'storefrontLayout' => $this->themeResolver->layout($store),
                'locale' => $locale,
                'slug' => $category['slug'] ?? $slug,
                'path' => $path,
                'category' => $category,
                'childrenCategories' => $children,
                'products' => $products,
                'filterFacets' => $filterFacets,
                'activeFilters' => $activeFilters,
                'currentSort' => $sort,
            ]
        );
    }

    private function normalizeSort(string $sort): string
    {
        return in_array($sort, [
            'default',
            'sku_asc',
            'sku_desc',
            'name_asc',
            'name_desc',
            'price_asc',
            'price_desc',
            'newest',
        ], true)
            ? $sort
            : 'default';
    }

    private function normalizeSeoFilters(
        Request $request,
        Collection $filterFacets
    ): array {
        $query = collect($request->query())
            ->reject(
                fn ($value, string|int $key) => in_array(
                    (string) $key,
                    ['page', 'filters', 'sort', 'grid'],
                    true
                )
            );

        if ($query->isEmpty()) {
            return [];
        }

        $facetsBySlug = $filterFacets
            ->filter(
                fn ($facet) => !empty($facet['slug'])
                    && !empty($facet['code'])
            )
            ->keyBy(
                fn ($facet) => (string) $facet['slug']
            );

        $filters = [];

        foreach ($query as $attributeSlug => $values) {

            $attributeSlug = Str::slug((string) $attributeSlug);

            $facet = $facetsBySlug->get($attributeSlug);

            if (!$facet) {
                continue;
            }

            $attributeCode = (string) ($facet['code'] ?? '');

            $facetValues = collect($facet['values'] ?? [])
                ->filter(
                    fn ($value) => !empty($value['slug'])
                        && !empty($value['key'])
                )
                ->keyBy(
                    fn ($value) => (string) $value['slug']
                );

            $values = collect(
                is_array($values) ? $values : [$values]
            )
                ->map(
                    fn ($value) => Str::slug((string) $value)
                )
                ->filter()
                ->unique()
                ->values();

            foreach ($values as $valueSlug) {

                $value = $facetValues->get($valueSlug);

                if (!$value) {
                    continue;
                }

                $filters[$attributeCode] ??= [];

                $filters[$attributeCode][] = (string) $value['key'];
            }
        }

        return collect($filters)
            ->map(
                fn ($values) => collect($values)
                    ->unique()
                    ->values()
                    ->all()
            )
            ->filter(
                fn ($values) => !empty($values)
            )
            ->all();
    }
}