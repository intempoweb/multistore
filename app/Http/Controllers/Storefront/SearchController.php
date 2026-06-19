<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Repositories\Storefront\SearchRepository;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

        [$tipocf, $clifor] = $this->customerContextForStore($store);

        $baseFilterFacets = $this->searchRepository->facets(
            store: $store,
            locale: $locale,
            query: $query,
            tipocf: $tipocf,
            clifor: $clifor,
            activeFilters: []
        );

        $activeFilters = $this->normalizeSeoFilters($request, $baseFilterFacets);

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

        $listingCardsByProductSku = collect($products->items())
            ->mapWithKeys(fn ($product) => [
                (string) $product->sku => $this->buildListingCardData($product),
            ]);

        return view($this->themeResolver->view('search.index', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'locale' => $locale,
            'query' => $query,
            'products' => $products,
            'listingCardsByProductSku' => $listingCardsByProductSku,
            'filterFacets' => $filterFacets,
            'activeFilters' => $activeFilters,
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

        [$tipocf, $clifor] = $this->customerContextForStore($store);

        $items = $this->searchRepository->suggest(
            store: $store,
            locale: app()->getLocale(),
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

    private function normalizeSeoFilters(Request $request, Collection $filterFacets): array
    {
        $query = collect($request->query())
            ->reject(fn ($value, string|int $key) => in_array((string) $key, ['page', 'filters', 'sort', 'grid', 'q'], true));

        if ($query->isEmpty() || $filterFacets->isEmpty()) {
            return [];
        }

        $facetsBySlug = $filterFacets
            ->filter(fn ($facet) => !empty($facet['slug']) && !empty($facet['code']))
            ->keyBy(fn ($facet) => (string) $facet['slug']);

        $filters = [];

        foreach ($query as $attributeSlug => $values) {
            $facet = $facetsBySlug->get(Str::slug((string) $attributeSlug));

            if (!$facet) {
                continue;
            }

            $attributeCode = (string) ($facet['code'] ?? '');
            $facetValues = collect($facet['values'] ?? [])
                ->filter(fn ($value) => !empty($value['slug']) && !empty($value['key']))
                ->keyBy(fn ($value) => (string) $value['slug']);

            foreach (collect(is_array($values) ? $values : [$values])->map(fn ($value) => Str::slug((string) $value))->filter()->unique() as $valueSlug) {
                $value = $facetValues->get($valueSlug);

                if ($value) {
                    $filters[$attributeCode] ??= [];
                    $filters[$attributeCode][] = (string) $value['key'];
                }
            }
        }

        return collect($filters)
            ->map(fn ($values) => collect($values)->unique()->values()->all())
            ->filter(fn ($values) => !empty($values))
            ->all();
    }

    private function customerContextForStore(mixed $store): array
    {
        if (!($store?->is_b2b ?? false)) {
            return [null, null];
        }

        $customer = auth('customer')->user();

        if (!$customer) {
            return [null, null];
        }

        $tipocf = (int) ($customer->tipocf_cg44 ?? $customer->tipocf ?? $customer->tipo_cf ?? 0);
        $clifor = (int) ($customer->clifor_cg44 ?? $customer->clifor ?? $customer->codice_cg16 ?? 0);

        return [$tipocf >= 0 ? $tipocf : 0, $clifor > 0 ? $clifor : null];
    }

    private function buildListingCardData(mixed $product): Collection
    {
        $targetSku = $this->resolveListingTargetSku($product);
        $variantOptions = collect($product->listing_variant_options ?? []);

        $selectedVariant = $variantOptions->first(fn (array $variant) => (string) ($variant['sku'] ?? '') === $targetSku)
            ?? $variantOptions->first();

        $selectedVariant = is_array($selectedVariant) ? $selectedVariant : [];

        return collect([
            'target_sku' => $targetSku,
            'image' => $selectedVariant['image'] ?? $product->main_image_url ?? null,
            'hover_image' => $selectedVariant['hover_image'] ?? $product->listing_hover_image_url ?? null,
            'price' => $selectedVariant['price'] ?? $selectedVariant['effective_price'] ?? $product->effective_price ?? $product->public_price ?? null,
            'selected_color_value' => $selectedVariant['color']['value'] ?? $product->listing_selected_color_value ?? null,
            'selected_format_value' => $selectedVariant['format']['value'] ?? $product->listing_selected_format_value ?? null,
            'price_payload' => null,
            'price_breaks' => collect(),
        ]);
    }

    private function resolveListingTargetSku(mixed $product): string
    {
        $variantOptions = collect($product->listing_variant_options ?? []);
        $targetSku = (string) ($product->listing_target_sku ?? $product->sku);

        if (!$variantOptions->first(fn ($item) => (string) ($item['sku'] ?? '') === $targetSku) && $variantOptions->isNotEmpty()) {
            $targetSku = (string) ($variantOptions->first(fn ($item) => !empty($item['sku']))['sku'] ?? $targetSku);
        }

        return $targetSku;
    }
}
