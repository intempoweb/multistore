<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Repositories\Storefront\SearchRepository;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __construct(
        private ThemeResolver $themeResolver,
        private SearchRepository $searchRepository
    ) {
    }

    public function index(Request $request): View
    {
        $store = app()->bound('currentStore') ? app('currentStore') : null;

        abort_unless($store, 404, 'Store corrente non disponibile.');

        $locale = app()->getLocale();
        $query = trim((string) $request->query('q', ''));
        $sort = $this->normalizeSort((string) $request->query('sort', 'default'));

        $customer = auth('customer')->user();

        $products = $this->searchRepository->search(
            store: $store,
            locale: $locale,
            query: $query,
            tipocf: $customer?->tipocf_cg44,
            clifor: $customer?->clifor_cg44,
            perPage: 24,
            sort: $sort
        );

        return view($this->themeResolver->view('search.index', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'locale' => $locale,
            'query' => $query,
            'products' => $products,
            'currentSort' => $sort,
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $store = app()->bound('currentStore') ? app('currentStore') : null;

        abort_unless($store, 404, 'Store corrente non disponibile.');

        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'query' => $query,
                'items' => [],
            ]);
        }

        $customer = auth('customer')->user();

        $items = $this->searchRepository->suggest(
            store: $store,
            locale: app()->getLocale(),
            query: $query,
            tipocf: $customer?->tipocf_cg44,
            clifor: $customer?->clifor_cg44,
            limit: 6
        );

        return response()->json([
            'query' => $query,
            'items' => $items,
            'search_url' => route('storefront.search.index', ['q' => $query]),
        ]);
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
}