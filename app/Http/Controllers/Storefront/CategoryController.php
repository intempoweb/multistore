<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\Catalog\CatalogRequestNormalizer;
use App\Services\Storefront\Catalog\ProductListingCardDataFactory;
use App\Services\Storefront\Seo\StorefrontSeoService;
use App\Services\Storefront\StorefrontContext;
use App\Services\Storefront\ThemeResolver;
use App\Services\Storefront\ViewData\ProductListingViewDataBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class CategoryController extends Controller
{
    public function __construct(
        private ThemeResolver $themeResolver,
        private CatalogRepository $catalogRepository,
        private StorefrontSeoService $seoService,
        private StorefrontContext $storefrontContext,
        private CatalogRequestNormalizer $requestNormalizer,
        private ProductListingCardDataFactory $listingCardFactory,
        private ProductListingViewDataBuilder $listingViewDataBuilder,
    ) {}

    public function legacy(Request $request, string $slug): RedirectResponse
    {
        $store = $this->storefrontContext->store();
        $locale = $this->storefrontContext->locale();

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
        $store = $this->storefrontContext->store();
        $locale = $this->storefrontContext->locale();
        $sort = $this->requestNormalizer->sort($request->query('sort', 'default'));

        $path = $this->catalogRepository->parseCategorySlug(
            $store,
            $locale,
            $slug
        );

        abort_if(empty($path['fam']), 404, 'Categoria non trovata.');

        $queryParams = $request->query();
        unset($queryParams['page']);

        $localizedLocaleUrls = collect(config('laravellocalization.supportedLocales'))
            ->keys()
            ->mapWithKeys(function (string $targetLocale) use ($store, $path, $queryParams) {
                $targetSlug = $this->catalogRepository->buildCategorySlug(
                    store: $store,
                    locale: $targetLocale,
                    fam: $path['fam'] ?? null,
                    sfam: $path['sfam'] ?? null,
                    gruppo: $path['gruppo'] ?? null,
                    sgruppo: $path['sgruppo'] ?? null,
                );

                return [
                    $targetLocale => LaravelLocalization::getLocalizedURL(
                        $targetLocale,
                        route('storefront.category.show', array_merge(['slug' => $targetSlug], $queryParams), false)
                    ),
                ];
            })
            ->all();

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

        $activeFilters = $this->requestNormalizer->filters($request, $baseFilterFacets);

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

        $listingCardsByProductSku = $this->listingCardFactory->forProducts($products->items());
        $effectiveSlug = $category['slug'] ?? $slug;
        $contextParams = $request->filled('agent_context') ? ['agent_context' => (string) $request->input('agent_context')] : [];
        $listingViewData = $this->listingViewDataBuilder->build(
            request: $request,
            products: $products,
            listingCardsByProductSku: $listingCardsByProductSku,
            filterFacets: $filterFacets,
            activeFilters: $activeFilters,
            childrenCategories: $children,
            currentSort: $sort,
            actionUrl: route('storefront.category.show', array_merge(['slug' => $effectiveSlug], $contextParams)),
            context: 'category',
        );

        return view(
            $this->themeResolver->view('category.show', $store),
            array_merge([
                'store' => $store,
                'storefrontLayout' => $this->themeResolver->layout($store),
                'locale' => $locale,
                'slug' => $effectiveSlug,
                'path' => $path,
                'category' => $category,
                'childrenCategories' => $children,
                'products' => $products,
                'listingCardsByProductSku' => $listingCardsByProductSku,
                'filterFacets' => $filterFacets,
                'activeFilters' => $activeFilters,
                'currentSort' => $sort,
                'localizedLocaleUrls' => $localizedLocaleUrls,
                'seo' => $this->seoService->category($store, $locale, $path, $category),
            ], $listingViewData)
        );
    }
}
