<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Repositories\Storefront\SearchRepository;
use App\Services\Storefront\Catalog\CatalogRequestNormalizer;
use App\Services\Storefront\Catalog\ProductListingCardDataFactory;
use App\Services\Storefront\StorefrontContext;
use App\Services\Storefront\ThemeResolver;
use App\Services\Storefront\ViewData\ProductListingViewDataBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __construct(
        private ThemeResolver $themeResolver,
        private SearchRepository $searchRepository,
        private StorefrontContext $storefrontContext,
        private CatalogRequestNormalizer $requestNormalizer,
        private ProductListingCardDataFactory $listingCardFactory,
        private ProductListingViewDataBuilder $listingViewDataBuilder,
    ) {}

    public function index(Request $request): View
    {
        $store = $this->storefrontContext->store();
        $locale = $this->storefrontContext->locale();
        $query = trim((string) $request->query('q', ''));
        $sort = $this->requestNormalizer->sort($request->query('sort', 'default'));
        $customerContext = $this->storefrontContext->customerCatalogContext($store);
        $tipocf = $customerContext->tipocf;
        $clifor = $customerContext->clifor;

        $baseFilterFacets = $this->searchRepository->facets(
            store: $store,
            locale: $locale,
            query: $query,
            tipocf: $tipocf,
            clifor: $clifor,
            activeFilters: []
        );

        $activeFilters = $this->requestNormalizer->filters($request, $baseFilterFacets, ['q']);

        $filterFacets = empty($activeFilters)
            ? $baseFilterFacets
            : $this->searchRepository->facets(
                store: $store,
                locale: $locale,
                query: $query,
                tipocf: $tipocf,
                clifor: $clifor,
                activeFilters: $activeFilters
            );

        $products = $this->searchRepository->search(
            store: $store,
            locale: $locale,
            query: $query,
            tipocf: $tipocf,
            clifor: $clifor,
            perPage: 24,
            sort: $sort,
            filters: $activeFilters
        );

        $listingCardsByProductSku = $this->listingCardFactory->forProducts($products->items());
        $contextParams = $request->filled('agent_context') ? ['agent_context' => (string) $request->input('agent_context')] : [];
        $searchUrl = route('storefront.search.index', array_merge(['q' => $query], $contextParams));
        $listingViewData = $this->listingViewDataBuilder->build(
            request: $request,
            products: $products,
            listingCardsByProductSku: $listingCardsByProductSku,
            filterFacets: $filterFacets,
            activeFilters: $activeFilters,
            childrenCategories: collect(),
            currentSort: $sort,
            actionUrl: $searchUrl,
            context: 'search',
        );

        return view($this->themeResolver->view('search.index', $store), array_merge([
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'locale' => $locale,
            'query' => $query,
            'products' => $products,
            'listingCardsByProductSku' => $listingCardsByProductSku,
            'filterFacets' => $filterFacets,
            'activeFilters' => $activeFilters,
            'currentSort' => $sort,
            'searchActionUrl' => route('storefront.search.index', $contextParams),
            'searchSidebarUrl' => $searchUrl,
        ], $listingViewData));
    }

    public function suggest(Request $request): JsonResponse
    {
        $store = $this->storefrontContext->store();

        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'query' => $query,
                'items' => [],
            ]);
        }

        $customerContext = $this->storefrontContext->customerCatalogContext($store);
        $tipocf = $customerContext->tipocf;
        $clifor = $customerContext->clifor;

        $items = $this->searchRepository->suggest(
            store: $store,
            locale: $this->storefrontContext->locale(),
            query: $query,
            tipocf: $tipocf,
            clifor: $clifor,
            limit: 8
        );

        $contextParams = request()->filled('agent_context')
            ? ['agent_context' => (string) request('agent_context')]
            : [];

        return response()->json([
            'query' => $query,
            'items' => $items,
            'search_url' => route('storefront.search.index', array_merge(['q' => $query], $contextParams)),
        ]);
    }
}
