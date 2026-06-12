<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        private ThemeResolver $themeResolver,
        private CatalogRepository $catalogRepository,
    ) {
    }

    public function index(Request $request): View|RedirectResponse|Response
    {
        $store = app()->bound('currentStore') ? app('currentStore') : null;

        abort_unless($store, 404, 'Store corrente non disponibile.');

        if (($store?->is_b2b ?? false) && !auth('customer')->check()) {
            return redirect()->route('storefront.login');
        }

        $locale = app()->getLocale();
        [$tipocf, $clifor] = $this->customerContextForStore($store);
        $sort = $this->normalizeSort((string) $request->query('sort', 'default'));

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

        $activeFilters = $this->normalizeSeoFilters($request, $baseFilterFacets);

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
            60,
            $activeFilters,
            $sort
        );

        $listingCardsByProductSku = collect($products->items())
            ->mapWithKeys(fn ($product) => [
                (string) $product->sku => $this->buildListingCardData($product, $store, $locale),
            ]);

        return response()
            ->view($this->themeResolver->view('home', $store), [
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
            ])
            ->header('Cache-Control', 'private, no-store');
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

    private function normalizeSort(string $sort): string
    {
        return in_array($sort, ['default', 'sku_asc', 'sku_desc', 'name_asc', 'name_desc', 'price_asc', 'price_desc', 'newest'], true)
            ? $sort
            : 'default';
    }

    private function normalizeSeoFilters(Request $request, Collection $filterFacets): array
    {
        $query = collect($request->query())
            ->reject(fn ($value, string|int $key) => in_array((string) $key, ['page', 'filters', 'sort', 'grid'], true));

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

    private function buildListingCardData(mixed $product, mixed $store, string $locale): array
    {
        $targetSku = $this->resolveListingTargetSku($product);
        $variantOptions = collect($product->listing_variant_options ?? []);

        $selectedVariant = $variantOptions->first(fn (array $variant) => (string) ($variant['sku'] ?? '') === $targetSku)
            ?? $variantOptions->first();

        $selectedVariant = is_array($selectedVariant) ? $selectedVariant : [];

        return [
            'target_sku' => $targetSku,
            'image' => $selectedVariant['image'] ?? $product->main_image_url ?? null,
            'hover_image' => $selectedVariant['hover_image'] ?? $product->listing_hover_image_url ?? null,
            'price' => $selectedVariant['price'] ?? $selectedVariant['effective_price'] ?? $product->effective_price ?? $product->public_price ?? null,
            'selected_color_value' => $selectedVariant['color']['value'] ?? $product->listing_selected_color_value ?? null,
            'selected_format_value' => $selectedVariant['format']['value'] ?? $product->listing_selected_format_value ?? null,
            'price_payload' => null,
            'price_breaks' => collect(),
        ];
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
