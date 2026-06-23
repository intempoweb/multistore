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
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
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

    public function index(Request $request): View
    {
        $store = $this->storefrontContext->store();
        $locale = $this->storefrontContext->locale();
        $customerContext = $this->storefrontContext->customerCatalogContext($store);
        $tipocf = $customerContext->tipocf;
        $clifor = $customerContext->clifor;
        $sort = $this->requestNormalizer->sort($request->query('sort', 'default'));

        $categories = $this->catalogRepository->getRootCategories($store, $locale);
        $baseFilterFacets = $this->catalogRepository->getCategoryFilterFacets(
            $store,
            $locale,
            null,
            null,
            null,
            null,
            $tipocf,
            $clifor,
            []
        );

        $activeFilters = $this->requestNormalizer->filters($request, $baseFilterFacets);

        $filterFacets = empty($activeFilters)
            ? $baseFilterFacets
            : $this->catalogRepository->getCategoryFilterFacets(
                $store,
                $locale,
                null,
                null,
                null,
                null,
                $tipocf,
                $clifor,
                $activeFilters
            );

        $products = $this->catalogRepository->getCategoryProducts(
            $store,
            $locale,
            null,
            null,
            null,
            null,
            $tipocf,
            $clifor,
            24,
            $activeFilters,
            $sort
        );

        $listingCardsByProductSku = $this->listingCardFactory->forProducts($products->items());
        $contextParams = $request->filled('agent_context') ? ['agent_context' => (string) $request->input('agent_context')] : [];
        $listingViewData = $this->listingViewDataBuilder->build(
            request: $request,
            products: $products,
            listingCardsByProductSku: $listingCardsByProductSku,
            filterFacets: $filterFacets,
            activeFilters: $activeFilters,
            childrenCategories: $categories,
            currentSort: $sort,
            actionUrl: route('storefront.catalog.index', $contextParams),
            context: 'catalog',
        );

        return view($this->themeResolver->view('catalog.index', $store), array_merge([
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'locale' => $locale,
            'categories' => $categories,
            'childrenCategories' => $categories,
            'products' => $products,
            'listingCardsByProductSku' => $listingCardsByProductSku,
            'filterFacets' => $filterFacets,
            'activeFilters' => $activeFilters,
            'currentSort' => $sort,
            'seo' => $this->seoService->catalog($store, $locale),
        ], $listingViewData));
    }
}
