<?php

namespace App\Http\Controllers\Storefront;

use App\Data\Storefront\HomePageInput;
use App\Http\Controllers\Controller;
use App\Models\StorefrontPage;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\Catalog\CatalogRequestNormalizer;
use App\Services\Storefront\Catalog\ProductListingCardDataFactory;
use App\Services\Storefront\Home\HomePageViewDataBuilder;
use App\Services\Storefront\StorefrontContext;
use App\Services\Storefront\StorefrontPageTranslationResolver;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        private ThemeResolver $themeResolver,
        private CatalogRepository $catalogRepository,
        private StorefrontContext $storefrontContext,
        private CatalogRequestNormalizer $requestNormalizer,
        private ProductListingCardDataFactory $listingCardFactory,
        private HomePageViewDataBuilder $homePageViewDataBuilder,
        private StorefrontPageTranslationResolver $pageTranslationResolver,
    ) {}

    public function index(Request $request): View|RedirectResponse|Response
    {
        $store = $this->storefrontContext->store();

        if (($store?->is_b2b ?? false) && ! auth('customer')->check()) {
            return redirect()->route('storefront.login');
        }

        $locale = $this->storefrontContext->locale();
        $customerContext = $this->storefrontContext->customerCatalogContext($store);
        $tipocf = $customerContext->tipocf;
        $clifor = $customerContext->clifor;
        $sort = $this->requestNormalizer->sort($request->query('sort', 'default'));

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
            72,
            $activeFilters,
            $sort
        );

        $listingCardsByProductSku = $this->listingCardFactory->forProducts($products->items());

        $storefrontPage = StorefrontPage::query()
            ->with(['translations', 'activeBlocks.translations', 'activeBlocks.activeMedia'])
            ->where('store_id', $store->id)
            ->where('slug', 'home')
            ->active()
            ->first();

        if ($storefrontPage) {
            $storefrontPage = $this->pageTranslationResolver->apply($storefrontPage, $store, $locale);
        }

        $rootCategories = $this->catalogRepository->getRootCategories($store, $locale);
        $storefrontPageBlocks = $storefrontPage?->activeBlocks ?? collect();
        $baseViewData = [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'locale' => $locale,
            'slug' => null,
            'products' => $products,
            'listingCardsByProductSku' => $listingCardsByProductSku,
            'filterFacets' => $filterFacets,
            'activeFilters' => $activeFilters,
            'currentSort' => $sort,
            'childrenCategories' => collect(),
            'rootCategories' => $rootCategories,
            'storefrontPage' => $storefrontPage,
            'storefrontPageBlocks' => $storefrontPageBlocks,
        ];
        $presentationData = $this->homePageViewDataBuilder->build(new HomePageInput(
            store: $store,
            locale: $locale,
            request: $request,
            products: $products,
            listingCardsByProductSku: $listingCardsByProductSku,
            filterFacets: $filterFacets,
            activeFilters: $activeFilters,
            currentSort: $sort,
            rootCategories: $rootCategories,
            storefrontPage: $storefrontPage,
            storefrontPageBlocks: $storefrontPageBlocks,
        ));

        return response()
            ->view($this->themeResolver->view('home', $store), array_merge($baseViewData, $presentationData))
            ->header('Cache-Control', 'private, no-store');
    }
}
