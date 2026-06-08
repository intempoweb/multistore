<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\Pricing\ProductPriceService;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        private ThemeResolver $themeResolver,
        private CatalogRepository $catalogRepository,
        private ProductPriceService $productPriceService,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $store = app()->bound('currentStore') ? app('currentStore') : null;

        abort_unless($store, 404, 'Store corrente non disponibile.');

        if (($store?->is_b2b ?? false) && !auth('customer')->check()) {
            return redirect()->route('storefront.login');
        }

        $locale = app()->getLocale();
        $sort = $this->normalizeSort((string) $request->query('sort', 'default'));

        $baseFilterFacets = $this->catalogRepository->getCategoryFilterFacets(
            $store,
            $locale,
            null,
            null,
            null,
            null,
            null,
            null,
            []
        );

        $activeFilters = $this->normalizeSeoFilters($request, $baseFilterFacets);

        $filterFacets = $this->catalogRepository->getCategoryFilterFacets(
            $store,
            $locale,
            null,
            null,
            null,
            null,
            null,
            null,
            $activeFilters
        );

        $products = $this->catalogRepository->getCategoryProducts(
            $store,
            $locale,
            null,
            null,
            null,
            null,
            null,
            null,
            24,
            $activeFilters,
            $sort
        );

        $listingCardsByProductSku = collect($products->items())
            ->mapWithKeys(function ($product) use ($store, $locale) {
                return [
                    (string) $product->sku => $this->buildListingCardData(
                        product: $product,
                        store: $store,
                        locale: $locale,
                    ),
                ];
            });

        return view($this->themeResolver->view('home', $store), [
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
        ], true) ? $sort : 'default';
    }

    private function normalizeSeoFilters(Request $request, Collection $filterFacets): array
    {
        $query = collect($request->query())
            ->reject(fn ($value, string|int $key) => in_array((string) $key, ['page', 'filters', 'sort', 'grid'], true));

        if ($query->isEmpty()) {
            return [];
        }

        $facetsBySlug = $filterFacets
            ->filter(fn ($facet) => !empty($facet['slug']) && !empty($facet['code']))
            ->keyBy(fn ($facet) => (string) $facet['slug']);

        $filters = [];

        foreach ($query as $attributeSlug => $values) {
            $attributeSlug = Str::slug((string) $attributeSlug);
            $facet = $facetsBySlug->get($attributeSlug);

            if (!$facet) {
                continue;
            }

            $attributeCode = (string) ($facet['code'] ?? '');
            $facetValues = collect($facet['values'] ?? [])
                ->filter(fn ($value) => !empty($value['slug']) && !empty($value['key']))
                ->keyBy(fn ($value) => (string) $value['slug']);

            $values = collect(is_array($values) ? $values : [$values])
                ->map(fn ($value) => Str::slug((string) $value))
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
            ->map(fn ($values) => collect($values)->unique()->values()->all())
            ->filter(fn ($values) => !empty($values))
            ->all();
    }

    private function buildListingCardData(mixed $product, mixed $store, string $locale): array
    {
        $targetSku = $this->resolveListingTargetSku($product);

        $resolvedProduct = $this->catalogRepository->getProductBySku(
            $store,
            $locale,
            $targetSku,
            null,
            null
        );

        if (!$resolvedProduct instanceof Product) {
            return [
                'target_sku' => $targetSku,
                'image' => $product->main_image_url ?? null,
                'hover_image' => null,
                'price' => $product->effective_price ?? $product->public_price,
                'selected_color_value' => null,
                'selected_format_value' => null,
                'price_payload' => null,
                'price_breaks' => collect(),
            ];
        }

        $this->loadResolvedProductGraph($resolvedProduct);

        $resolvedSelectedProduct = $resolvedProduct->getAttribute('resolved_selected_product');
        $resolvedVariantProducts = $resolvedProduct->getAttribute('resolved_variant_products');

        $selectedProduct = $resolvedSelectedProduct instanceof Product
            ? $resolvedSelectedProduct
            : $resolvedProduct;

        $variantProducts = $resolvedVariantProducts instanceof EloquentCollection
            ? $resolvedVariantProducts->where('is_active', true)->values()
            : collect();

        if ($variantProducts->isEmpty()) {
            $variantProducts = collect([$selectedProduct]);
        }

        $selectedAttributePresentation = $this->mapProductAttributePresentation($selectedProduct, $locale);
        $selectedColorValue = $this->findPresentationRow($selectedAttributePresentation, ['colore', 'color'])['value'] ?? null;
        $selectedFormatValue = $this->findPresentationRow($selectedAttributePresentation, ['formato', 'format'])['value'] ?? null;
        $selectedImages = $this->resolveListingImages($selectedProduct);

        $variantPresentation = $variantProducts
            ->map(function (Product $variant) use ($locale, $store) {
                $presentation = $this->mapProductAttributePresentation($variant, $locale);
                $priceData = $this->productPriceService->resolveForListing($store, $variant, 1);
                $images = $this->resolveListingImages($variant);

                return [
                    'sku' => $variant->sku,
                    'image' => $images['image'],
                    'hover_image' => $images['hover_image'],
                    'price' => $priceData['price'],
                    'price_payload' => $priceData['price_payload'],
                    'price_breaks' => collect($priceData['price_breaks'] ?? []),
                    'color' => $this->findPresentationRow($presentation, ['colore', 'color']),
                    'format' => $this->findPresentationRow($presentation, ['formato', 'format']),
                ];
            })
            ->values();

        $selectedVariant = $variantPresentation->first(
            fn (array $item) => ($item['sku'] ?? null) === $selectedProduct->sku
        ) ?? $variantPresentation->first();

        return [
            'target_sku' => $selectedProduct->sku ?? $targetSku,
            'image' => $selectedVariant['image']
                ?? $selectedImages['image']
                ?? $resolvedProduct->main_image_url
                ?? null,
            'hover_image' => $selectedVariant['hover_image']
                ?? $selectedImages['hover_image']
                ?? null,
            'price' => $selectedVariant['price']
                ?? $selectedProduct->effective_price
                ?? $selectedProduct->public_price,
            'selected_color_value' => $selectedColorValue,
            'selected_format_value' => $selectedFormatValue,
            'price_payload' => $selectedVariant['price_payload'] ?? null,
            'price_breaks' => collect($selectedVariant['price_breaks'] ?? []),
        ];
    }

    private function resolveListingTargetSku(mixed $product): string
    {
        $variantOptions = collect($product->listing_variant_options ?? []);
        $targetSku = (string) ($product->listing_target_sku ?? $product->sku);

        $selectedVariantOption = $variantOptions->first(
            fn ($item) => (string) ($item['sku'] ?? '') === $targetSku
        );

        if (!$selectedVariantOption && $variantOptions->isNotEmpty()) {
            $selectedVariantOption = $variantOptions->first(fn ($item) => !empty($item['sku']));
            $targetSku = (string) ($selectedVariantOption['sku'] ?? $targetSku);
        }

        return $targetSku;
    }

    private function loadResolvedProductGraph(Product $product): void
    {
        $product->load([
            'mediaAssets',
            'productAttributeValues.attribute',
            'productAttributeValues.value.mediaAssets',
        ]);

        $resolvedBaseProduct = $product->getAttribute('resolved_base_product');
        $resolvedSelectedProduct = $product->getAttribute('resolved_selected_product');
        $resolvedVariantProducts = $product->getAttribute('resolved_variant_products');

        if ($resolvedBaseProduct instanceof Product) {
            $resolvedBaseProduct->loadMissing([
                'mediaAssets',
                'productAttributeValues.attribute',
                'productAttributeValues.value.mediaAssets',
            ]);
        }

        if ($resolvedSelectedProduct instanceof Product) {
            $resolvedSelectedProduct->loadMissing([
                'mediaAssets',
                'productAttributeValues.attribute',
                'productAttributeValues.value.mediaAssets',
            ]);
        }

        if ($resolvedVariantProducts instanceof EloquentCollection && $resolvedVariantProducts->isNotEmpty()) {
            $resolvedVariantProducts->load([
                'mediaAssets',
                'productAttributeValues.attribute',
                'productAttributeValues.value.mediaAssets',
            ]);
        }
    }

    private function mapProductAttributePresentation(Product $product, string $locale): Collection
    {
        return collect($product->productAttributeValues ?? [])
            ->sortBy(function ($item) {
                return [
                    (int) ($item->attribute->sort_order ?? 0),
                    (string) ($item->attribute->code ?? ''),
                ];
            })
            ->map(function ($row) use ($locale) {
                $attributeTranslation = $row->attribute?->translationOrFallback($locale);
                $valueTranslation = $row->value?->translationOrFallback($locale);

                $attributeLabel = $attributeTranslation?->label
                    ?? $row->attribute?->code
                    ?? 'Attributo';

                $attributeValue = $valueTranslation?->label
                    ?? $row->value?->value_code
                    ?? $row->raw_value
                    ?? '—';

                $attributeCode = trim((string) ($row->attribute?->code ?? ''));
                $swatchAsset = $row->value?->mediaAssets?->firstWhere('role', MediaAsset::ROLE_SWATCH);

                return [
                    'code' => $attributeCode !== '' ? $attributeCode : null,
                    'label' => $attributeLabel,
                    'value' => $attributeValue,
                    'normalized_label' => Str::lower(trim((string) $attributeLabel)),
                    'normalized_code' => Str::lower($attributeCode),
                    'swatch_url' => $swatchAsset?->url ?? $row->value?->swatch()?->url,
                ];
            })
            ->values();
    }

    private function findPresentationRow(Collection $presentation, array $keys): ?array
    {
        return $presentation->first(function (array $row) use ($keys) {
            return in_array($row['normalized_label'] ?? null, $keys, true)
                || in_array($row['normalized_code'] ?? null, $keys, true);
        });
    }

    private function resolveListingImages(Product $product): array
    {
        $mediaUrls = collect($product->mediaAssets ?? [])
            ->filter(fn ($asset) => in_array($asset->role, [
                MediaAsset::ROLE_MAIN,
                MediaAsset::ROLE_GALLERY,
            ], true))
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn ($asset) => $asset->url ?? null)
            ->filter()
            ->unique()
            ->values();

        $mainImage = $product->mainImage()?->url ?? $mediaUrls->first();
        $hoverImage = $mediaUrls->first(fn ($url) => $mainImage && $url !== $mainImage);

        return [
            'image' => $mainImage,
            'hover_image' => $hoverImage,
        ];
    }
}