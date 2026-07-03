<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\MediaAsset;
use App\Models\ProductComparison;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\Cart\CartItemService;
use App\Services\Storefront\Pricing\ProductPriceService;
use App\Services\Storefront\ThemeResolver;
use App\Services\Storefront\Seo\StorefrontSeoService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private ThemeResolver $themeResolver,
        private CatalogRepository $catalogRepository,
        private CartItemService $cartItemService,
        private ProductPriceService $productPriceService,
        private StorefrontSeoService $seoService,
    ) {
    }

    public function show(Request $request, string $sku): View|RedirectResponse
    {
        $store = app()->bound('currentStore') ? app('currentStore') : null;

        abort_unless($store, 404, __('themes_b2c.product.current_store_unavailable'));

        $locale = app()->getLocale();
        $requestedSlugOrSku = $sku;
        $sku = $this->catalogRepository->parseProductSku($sku);

        $product = $this->catalogRepository->getProductBySku(
            $store,
            $locale,
            $sku,
            null,
            null
        );

        abort_unless($product instanceof Product, 404, __('themes_b2c.product.product_not_found'));

        $this->loadResolvedProductGraph($product);

        $resolvedBaseProduct = $product->getAttribute('resolved_base_product');
        $resolvedSelectedProduct = $product->getAttribute('resolved_selected_product');
        $resolvedVariantProducts = $product->getAttribute('resolved_variant_products');

        $baseProduct = $resolvedBaseProduct instanceof Product ? $resolvedBaseProduct : $product;
        $selectedProduct = $resolvedSelectedProduct instanceof Product ? $resolvedSelectedProduct : $product;
        $variantProducts = $resolvedVariantProducts instanceof EloquentCollection
            ? $resolvedVariantProducts->where('is_active', true)->values()
            : collect();

        if ($variantProducts->isEmpty()) {
            $variantProducts = collect([$selectedProduct]);
        }

        $this->loadComparisonsForProducts(
            collect([$product, $baseProduct, $selectedProduct])
                ->merge($variantProducts)
                ->filter(fn ($item) => $item instanceof Product)
                ->unique(fn (Product $item) => implode('|', [
                    (int) $item->ditta_cg18,
                    (int) $item->site_type,
                    (string) $item->sku,
                ]))
                ->values()
        );

        $expectedSlug = $this->catalogRepository->buildProductSlug($selectedProduct, $locale);

        if ($requestedSlugOrSku !== $expectedSlug) {
            return redirect()->to(
                $this->catalogRepository->productUrl($selectedProduct, $locale, $request->query()),
                301
            );
        }

        $selectedTranslation = $selectedProduct->translationOrFallback($locale);
        $baseTranslation = $baseProduct->translationOrFallback($locale);
        $selectedAttributePresentation = $this->mapProductAttributePresentation($selectedProduct, $locale);
        $comparisonRows = $this->resolveProductComparisonRows($selectedProduct, $baseProduct, $selectedAttributePresentation);

        $quantityConstraints = $this->cartItemService->resolveQuantityConstraintsForProduct($selectedProduct);
        $quantityMin = max(1, (int) ($quantityConstraints['quantity_min'] ?? 1));
        $quantityStep = max(1, (int) ($quantityConstraints['quantity_step'] ?? 1));
        $packMultiple = max(1, (int) ($quantityConstraints['pack_multiple'] ?? 1));
        $showPackMultiple = (bool) ($quantityConstraints['show_pack_multiple'] ?? false);
        $quantityInputValue = $quantityMin;
        $displayQty = max(1, $quantityInputValue);

        $variantPresentation = $variantProducts
            ->map(function (Product $variant) use ($locale, $store, $quantityInputValue) {
                $presentation = $this->mapProductAttributePresentation($variant, $locale);
                $pricing = $this->resolveVariantPricing($store, $variant, $quantityInputValue);

                $colorRow = $this->findPresentationRow($presentation, ['colore', 'color'], ['a09', 'colore', 'color']);
                $formatRow = $this->findPresentationRow($presentation, ['formato', 'format', 'size'], ['a02', 'formato', 'format', 'size']);

                return [
                    'product' => $variant,
                    'sku' => $variant->sku,
                    'name' => $variant->translationOrFallback($locale)?->name ?? $variant->sku,
                    'image' => $this->mainImageUrlFromLoadedMedia($variant),
                    'price' => $pricing['price'],
                    'price_row' => $pricing['price_row'],
                    'price_breaks' => $pricing['price_breaks'],
                    'color' => $colorRow,
                    'format' => $formatRow,
                ];
            })
            ->values();

        $selectedColorRow = $this->findPresentationRow(
            $selectedAttributePresentation,
            ['colore', 'color'],
            ['a09', 'colore', 'color']
        );

        $selectedFormatRow = $this->findPresentationRow(
            $selectedAttributePresentation,
            ['formato', 'format', 'size'],
            ['a02', 'formato', 'format', 'size']
        );

        $hasColorVariants = $this->hasVariantAxis($variantPresentation, 'color');
        $hasFormatVariants = $this->hasVariantAxis($variantPresentation, 'format');

        $selectedColorValue = $hasColorVariants ? ($selectedColorRow['value'] ?? null) : null;
        $selectedFormatValue = $hasFormatVariants ? ($selectedFormatRow['value'] ?? null) : null;

        $technicalRows = $selectedAttributePresentation
            ->reject(fn (array $item) => $this->shouldHideFromTechnicalRows(
                $item,
                $hasColorVariants,
                $hasFormatVariants
            ))
            ->values();

        $selectedVariant = $variantPresentation->first(
            fn (array $item) => ($item['sku'] ?? null) === $selectedProduct->sku
        ) ?? $variantPresentation->first();

        $selectedVariantPriceBreaks = $this->normalizePriceBreaks(
            data_get($selectedVariant, 'price_breaks', [])
        );

        $selectedVariantPriceBreaksJson = $selectedVariantPriceBreaks->toJson();

        $galleryImages = $this->buildGalleryImages(
            selectedProduct: $selectedProduct,
            baseProduct: $baseProduct,
            selectedTranslationName: $selectedTranslation?->name,
            baseTranslationName: $baseTranslation?->name,
            fallbackImage: data_get($selectedVariant, 'image')
        );

        $mainImage = $galleryImages->first()['url'] ?? null;

        $image = $mainImage
            ?? data_get($selectedVariant, 'image')
            ?? $this->mainImageUrlFromLoadedMedia($selectedProduct)
            ?? $this->mainImageUrlFromLoadedMedia($baseProduct);

        $effectivePrice = $selectedVariant['price']
            ?? $selectedProduct->public_price
            ?? $selectedProduct->effective_price;

        $selectedPriceRow = $selectedVariant['price_row'] ?? null;

        $stockQty = $selectedProduct->stock_qty !== null ? (float) $selectedProduct->stock_qty : null;
        $noBackorder = (bool) ($selectedProduct->no_backorder ?? false);
        $b2cThemeCodes = ['ciak', 'intemposhop', 'tekniko', 'ready'];
        $storeTheme = trim((string) ($store->theme ?? ''));
        $siteType = (int) ($selectedProduct->site_type ?? $store->erp_site_code ?? 0);
        $isB2bStore = (bool) ($store->is_b2b ?? false)
            && $siteType === 1
            && !in_array($storeTheme, $b2cThemeCodes, true);
        $isBackorderAvailable = $stockQty !== null && $stockQty <= 0 && !$noBackorder;

        $stockLabel = match (true) {
            $stockQty === null => __('themes_b2c.product.availability_not_specified'),
            $stockQty > 0 => __('themes_b2c.product.in_stock'),
            $isBackorderAvailable => $isB2bStore
                ? __('themes_b2c.product.orderable')
                : __('themes_b2c.product.in_stock'),
            default => __('themes_b2c.product.out_of_stock'),
        };

        $stockClass = match (true) {
            $stockQty === null => 'text-muted',
            $stockQty > 0 => 'text-success',
            $isBackorderAvailable => $isB2bStore ? 'text-warning' : 'text-success',
            default => 'text-danger',
        };

        $stockHint = $isBackorderAvailable && $isB2bStore
            ? __('themes_b2c.product.backorder_soon_hint')
            : null;

        $stockDisplay = $stockQty !== null
            ? number_format($stockQty, 0, ',', '.')
            : null;

        $maxCartQuantity = $this->resolveMaxCartQuantity($selectedProduct, $quantityMin);
        $canAddToCart = $this->canAddToCart($selectedProduct, $quantityMin);
        $purchaseBlocked = !$canAddToCart;
        $quantityMax = $maxCartQuantity !== null && $maxCartQuantity > 0 ? $maxCartQuantity : null;

        $colorOptions = $variantPresentation
            ->filter(fn (array $item) => !empty($item['color']['value']))
            ->groupBy(fn (array $item) => $item['color']['value'])
            ->map(function (Collection $items, string $value) use ($selectedFormatValue) {
                $preferred = $selectedFormatValue
                    ? $items->first(fn (array $item) => ($item['format']['value'] ?? null) === $selectedFormatValue)
                    : null;

                $target = $preferred ?: $items->first();

                return [
                    'value' => $value,
                    'swatch_url' => $target['color']['swatch_url'] ?? null,
                    'sku' => $target['sku'],
                ];
            })
            ->values();

        $formatOptions = $variantPresentation
            ->filter(fn (array $item) => !empty($item['format']['value']))
            ->groupBy(fn (array $item) => $item['format']['value'])
            ->map(function (Collection $items, string $value) use ($selectedColorValue) {
                $preferred = $selectedColorValue
                    ? $items->first(fn (array $item) => ($item['color']['value'] ?? null) === $selectedColorValue)
                    : null;

                $target = $preferred ?: $items->first();

                return [
                    'value' => $value,
                    'swatch_url' => $target['format']['swatch_url'] ?? null,
                    'sku' => $target['sku'],
                ];
            })
            ->values();

        $seo = $this->seoService->product($store, $locale, $selectedProduct, [
            'images' => $galleryImages->pluck('url')->all(),
            'price' => $effectivePrice,
            'stock' => $stockQty,
            'no_backorder' => $noBackorder,
        ]);

        return view($this->themeResolver->view('product.show', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'locale' => $locale,
            'sku' => $selectedProduct->sku,
            'product' => $product,
            'baseProduct' => $baseProduct,
            'selectedProduct' => $selectedProduct,
            'variantProducts' => $variantProducts,
            'selectedTranslation' => $selectedTranslation,
            'baseTranslation' => $baseTranslation,
            'selectedAttributePresentation' => $selectedAttributePresentation,
            'selectedColorValue' => $selectedColorValue,
            'selectedFormatValue' => $selectedFormatValue,
            'hasColorVariants' => $hasColorVariants,
            'hasFormatVariants' => $hasFormatVariants,
            'technicalRows' => $technicalRows,
            'variantPresentation' => $variantPresentation,
            'selectedVariant' => $selectedVariant,
            'selectedVariantPriceBreaks' => $selectedVariantPriceBreaks,
            'selectedVariantPriceBreaksJson' => $selectedVariantPriceBreaksJson,
            'selectedVariantImage' => data_get($selectedVariant, 'image'),
            'image' => $image,
            'galleryImages' => $galleryImages,
            'mainImage' => $mainImage,
            'effectivePrice' => $effectivePrice,
            'selectedPriceRow' => $selectedPriceRow,
            'displayQty' => $displayQty,
            'stockQty' => $stockQty,
            'stockLabel' => $stockLabel,
            'stockClass' => $stockClass,
            'stockHint' => $stockHint,
            'stockDisplay' => $stockDisplay,
            'canAddToCart' => $canAddToCart,
            'purchaseBlocked' => $purchaseBlocked,
            'maxCartQuantity' => $maxCartQuantity,
            'quantityMax' => $quantityMax,
            'noBackorder' => $noBackorder,
            'quantityMin' => $quantityMin,
            'quantityStep' => $quantityStep,
            'quantityInputValue' => $quantityInputValue,
            'showPackMultiple' => $showPackMultiple,
            'packMultiple' => $packMultiple,
            'colorOptions' => $colorOptions,
            'formatOptions' => $formatOptions,
            'seo' => $seo,
            'comparisonRows' => $comparisonRows,
        ]);
    }

    private function loadComparisonsForProducts(Collection $products): void
    {
        $products = $products
            ->filter(fn ($product) => $product instanceof Product)
            ->unique(fn (Product $product) => implode('|', [
                (int) $product->ditta_cg18,
                (int) $product->site_type,
                (string) $product->sku,
            ]))
            ->values();

        if ($products->isEmpty()) {
            return;
        }

        $comparisons = collect();

        $products
            ->groupBy(fn (Product $product) => implode('|', [
                (int) $product->ditta_cg18,
                (int) $product->site_type,
            ]))
            ->each(function (Collection $group) use (&$comparisons) {
                $first = $group->first();

                if (!$first instanceof Product) {
                    return;
                }

                $skus = $group
                    ->pluck('sku')
                    ->map(fn ($sku) => trim((string) $sku))
                    ->filter()
                    ->unique()
                    ->values();

                if ($skus->isEmpty()) {
                    return;
                }

                $rows = ProductComparison::query()
                    ->where('ditta_cg18', (int) $first->ditta_cg18)
                    ->where('site_type', (int) $first->site_type)
                    ->whereIn('sku', $skus->all())
                    ->orderBy('source')
                    ->orderBy('comparison_sku')
                    ->orderBy('id')
                    ->get();

                $comparisons = $comparisons->merge($rows);
            });

        $comparisonsByKey = $comparisons->groupBy(fn (ProductComparison $comparison) => implode('|', [
            (int) $comparison->ditta_cg18,
            (int) $comparison->site_type,
            (string) $comparison->sku,
        ]));

        foreach ($products as $product) {
            $key = implode('|', [
                (int) $product->ditta_cg18,
                (int) $product->site_type,
                (string) $product->sku,
            ]);

            $product->setRelation(
                'comparisons',
                new EloquentCollection($comparisonsByKey->get($key, collect())->all())
            );
        }
    }

    private function resolveProductComparisonRows(Product $selectedProduct, Product $baseProduct, Collection $attributePresentation): Collection
    {
        $comparisons = collect($selectedProduct->comparisons ?? []);
        if ($comparisons->isEmpty() && $selectedProduct->getKey() !== $baseProduct->getKey()) {
            $comparisons = collect($baseProduct->comparisons ?? []);
        }
        return $comparisons
            ->map(fn (ProductComparison $comparison) => [
                'label' => $this->translatedComparisonLabel($comparison, $attributePresentation),
                'value' => Product::normalizeErpCodeValue($comparison->comparison_sku),
            ])
            ->filter(fn (array $row) => $row['value'] !== null)
            ->values();
    }

    private function translatedComparisonLabel(ProductComparison $comparison, Collection $attributePresentation): string
    {
        $source = trim((string) $comparison->source);
        $normalizedSource = Str::lower($source);
        $normalizedBaseSource = Str::lower(trim((string) preg_replace('/[-_\s]*ciak$/i', '', $source)));

        $codeMap = [
            'a02' => ['formato', 'format', 'size'],
            'a07' => ['tracciato', 'layout'],
            'a09' => ['colore', 'color'],
            'a11' => ['marca-brand', 'brand', 'marca'],
        ];

        foreach ($attributePresentation as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $normalizedLabel = Str::lower(trim((string) ($row['normalized_label'] ?? $label)));
            $normalizedCode = Str::lower(trim((string) ($row['normalized_code'] ?? $row['code'] ?? '')));

            if ($label === '') {
                continue;
            }

            if ($normalizedSource === $normalizedLabel || $normalizedBaseSource === $normalizedLabel) {
                return $label;
            }

            if ($normalizedSource === $normalizedCode || $normalizedBaseSource === $normalizedCode) {
                return $label;
            }

            foreach ($codeMap as $code => $aliases) {
                if ($normalizedCode !== $code) {
                    continue;
                }

                if (in_array($normalizedSource, $aliases, true) || in_array($normalizedBaseSource, $aliases, true)) {
                    return $label;
                }
            }
        }

        return $source;
    }

    private function resolveVariantPricing(mixed $store, Product $variant, int|float $qty = 1): array
    {
        $pricing = $this->productPriceService->resolveForListing(
            store: $store,
            product: $variant,
            qty: $qty
        );

        $pricePayload = is_array($pricing['price_payload'] ?? null)
            ? $pricing['price_payload']
            : [];

        $resolvedPrice = $pricing['price']
            ?? $pricePayload['price']
            ?? $pricePayload['price_net']
            ?? $variant->public_price
            ?? $variant->effective_price;

        return [
            'price' => $resolvedPrice !== null ? (float) $resolvedPrice : null,
            'price_row' => [
                'price' => $resolvedPrice !== null ? (float) $resolvedPrice : null,
                'price_net' => isset($pricePayload['price_net']) && $pricePayload['price_net'] !== null
                    ? (float) $pricePayload['price_net']
                    : ($resolvedPrice !== null ? (float) $resolvedPrice : null),
                'price_gross' => isset($pricePayload['price_gross']) && $pricePayload['price_gross'] !== null
                    ? (float) $pricePayload['price_gross']
                    : null,
                'listino_id' => isset($pricePayload['listino_id']) && $pricePayload['listino_id'] !== null
                    ? (int) $pricePayload['listino_id']
                    : null,
                'qty_from' => isset($pricePayload['qty_from']) && $pricePayload['qty_from'] !== null
                    ? (float) $pricePayload['qty_from']
                    : null,
                'qty_to' => isset($pricePayload['qty_to']) && $pricePayload['qty_to'] !== null
                    ? (float) $pricePayload['qty_to']
                    : null,
                'sc1' => isset($pricePayload['sc1']) && $pricePayload['sc1'] !== null ? (float) $pricePayload['sc1'] : null,
                'sc2' => isset($pricePayload['sc2']) && $pricePayload['sc2'] !== null ? (float) $pricePayload['sc2'] : null,
                'sc3' => isset($pricePayload['sc3']) && $pricePayload['sc3'] !== null ? (float) $pricePayload['sc3'] : null,
                'sc4' => isset($pricePayload['sc4']) && $pricePayload['sc4'] !== null ? (float) $pricePayload['sc4'] : null,
                'sc5' => isset($pricePayload['sc5']) && $pricePayload['sc5'] !== null ? (float) $pricePayload['sc5'] : null,
                'sc6' => isset($pricePayload['sc6']) && $pricePayload['sc6'] !== null ? (float) $pricePayload['sc6'] : null,
            ],
            'price_breaks' => is_array($pricing['price_breaks'] ?? null)
                ? $pricing['price_breaks']
                : [],
        ];
    }

    private function loadResolvedProductGraph(Product $product): void

    {

        $locale = app()->getLocale();

        $locales = collect([$locale, config('app.fallback_locale', 'en')])->filter()->unique()->values()->all();

        $relations = [

            'translations' => fn ($q) => $q->whereIn('locale', $locales),

            'mediaAssets',

            'productAttributeValues.attribute.translations' => fn ($q) => $q->whereIn('locale', $locales),

            'productAttributeValues.value.translations' => fn ($q) => $q->whereIn('locale', $locales),

            'productAttributeValues.value.mediaAssets',

        ];

        $product->loadMissing($relations);

        $resolvedBaseProduct = $product->getAttribute('resolved_base_product');

        $resolvedSelectedProduct = $product->getAttribute('resolved_selected_product');

        $resolvedVariantProducts = $product->getAttribute('resolved_variant_products');

        if ($resolvedBaseProduct instanceof Product) {

            $resolvedBaseProduct->loadMissing($relations);

        }

        if ($resolvedSelectedProduct instanceof Product) {

            $resolvedSelectedProduct->loadMissing($relations);

        }

        if ($resolvedVariantProducts instanceof EloquentCollection && $resolvedVariantProducts->isNotEmpty()) {

            $resolvedVariantProducts->load($relations);

        }

    }

    private function findPresentationRow(Collection $rows, array $labels = [], array $codes = []): ?array
    {
        $normalizedLabels = collect($labels)
            ->map(fn ($label) => Str::lower(trim((string) $label)))
            ->filter()
            ->values()
            ->all();

        $normalizedCodes = collect($codes)
            ->map(fn ($code) => Str::lower(trim((string) $code)))
            ->filter()
            ->values()
            ->all();

        $row = $rows->first(function (array $item) use ($normalizedLabels, $normalizedCodes) {
            $matchesLabel = in_array($item['normalized_label'] ?? null, $normalizedLabels, true);
            $matchesCode = in_array($item['normalized_code'] ?? null, $normalizedCodes, true);

            return $matchesLabel || $matchesCode;
        });

        return is_array($row) ? $row : null;
    }

    private function mapProductAttributePresentation(Product $product, string $locale): Collection
    {
        $fallback = (string) config('app.fallback_locale', 'en');

        return collect($product->productAttributeValues ?? [])
            ->sortBy(function ($item) {
                return [
                    (int) ($item->attribute->sort_order ?? 0),
                    (string) ($item->attribute->code ?? ''),
                ];
            })
            ->map(function ($row) use ($locale, $fallback) {
                $attributeTranslations = $row->attribute?->relationLoaded('translations')
                    ? collect($row->attribute->getRelation('translations'))
                    : collect();

                $valueTranslations = $row->value?->relationLoaded('translations')
                    ? collect($row->value->getRelation('translations'))
                    : collect();

                $attributeTranslation = $attributeTranslations->firstWhere('locale', $locale)
                    ?: $attributeTranslations->firstWhere('locale', $fallback)
                    ?: $attributeTranslations->first();

                $valueTranslation = $valueTranslations->firstWhere('locale', $locale)
                    ?: $valueTranslations->firstWhere('locale', $fallback)
                    ?: $valueTranslations->first();

                $attributeLabel = $attributeTranslation?->label
                    ?? $row->attribute?->code
                    ?? __('themes_b2c.product.attribute');

                $attributeValue = $valueTranslation?->label
                    ?? $row->value?->value_code
                    ?? $row->raw_value
                    ?? '—';

                $attributeCode = trim((string) ($row->attribute?->code ?? ''));

                $swatchUrl = $row->value instanceof \App\Models\AttributeValue
                    ? $this->attributeValueSwatchUrl($row->value)
                    : null;

                return [
                    'code' => $attributeCode !== '' ? $attributeCode : null,
                    'label' => $attributeLabel,
                    'value' => $attributeValue,
                    'normalized_label' => Str::lower(trim((string) $attributeLabel)),
                    'normalized_code' => Str::lower($attributeCode),
                    'swatch_url' => $swatchUrl,
                ];
            })
            ->values();
    }

    private function buildGalleryImages(
        Product $selectedProduct,
        Product $baseProduct,
        ?string $selectedTranslationName = null,
        ?string $baseTranslationName = null,
        ?string $fallbackImage = null
    ): Collection {
        $selectedMediaAssets = collect($selectedProduct->mediaAssets ?? [])
            ->filter(fn ($asset) => in_array($asset->role, [
                MediaAsset::ROLE_MAIN,
                MediaAsset::ROLE_GALLERY,
            ], true))
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $baseMediaAssets = collect($baseProduct->mediaAssets ?? [])
            ->filter(fn ($asset) => in_array($asset->role, [
                MediaAsset::ROLE_MAIN,
                MediaAsset::ROLE_GALLERY,
            ], true))
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $galleryImages = $selectedMediaAssets
            ->map(fn ($asset) => [
                'url' => $this->mediaAssetUrl($asset),
                'alt' => $selectedTranslationName ?? $selectedProduct->sku,
                'source' => 'selected',
                'sort_order' => $asset->sort_order ?? 0,
                'role' => $asset->role,
            ])
            ->filter(fn ($item) => !empty($item['url']))
            ->values();

        if ($galleryImages->isEmpty()) {
            $galleryImages = $baseMediaAssets
                ->map(fn ($asset) => [
                    'url' => $this->mediaAssetUrl($asset),
                    'alt' => $baseTranslationName ?? $baseProduct->sku,
                    'source' => 'base',
                    'sort_order' => $asset->sort_order ?? 0,
                    'role' => $asset->role,
                ])
                ->filter(fn ($item) => !empty($item['url']))
                ->values();
        }

        if ($galleryImages->isEmpty()) {
            $resolvedFallbackImage = $fallbackImage
                ?? $this->mainImageUrlFromLoadedMedia($selectedProduct)
                ?? $this->mainImageUrlFromLoadedMedia($baseProduct)
                ?? $selectedProduct->main_image_url
                ?? $baseProduct->main_image_url;

            if ($resolvedFallbackImage) {
                $galleryImages = collect([[
                    'url' => $resolvedFallbackImage,
                    'alt' => $selectedTranslationName ?? $selectedProduct->sku,
                    'source' => 'fallback',
                    'sort_order' => 0,
                    'role' => 'main',
                ]]);
            }
        }

        return $galleryImages->values();
    }

    private function attributeValueSwatchUrl(\App\Models\AttributeValue $value): ?string
    {
        $loadedAssets = $value->relationLoaded('mediaAssets')
            ? collect($value->getRelation('mediaAssets'))
            : collect();

        $swatch = $loadedAssets->firstWhere('role', MediaAsset::ROLE_SWATCH);

        if (!$swatch instanceof MediaAsset) {
            return null;
        }

        return $this->mediaAssetUrl($swatch);
    }

    private function mainImageUrlFromLoadedMedia(Product $product): ?string
    {
        $mediaAssets = collect($product->mediaAssets ?? [])
            ->filter(fn ($asset) => $asset instanceof MediaAsset && in_array($asset->role, [MediaAsset::ROLE_MAIN, MediaAsset::ROLE_GALLERY], true))
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $mainAsset = $mediaAssets->firstWhere('role', MediaAsset::ROLE_MAIN)
            ?? $mediaAssets->first();

        return $mainAsset instanceof MediaAsset ? $this->mediaAssetUrl($mainAsset) : null;
    }

    private function mediaAssetUrl(MediaAsset $asset): ?string
    {
        $source = $asset->local_path
            ?? $asset->erp_full_path
            ?? $asset->url
            ?? null;

        return media_url($source);
    }

    private function resolveMaxCartQuantity(Product $product, int $quantityMin): ?int
    {
        $stockQty = $product->stock_qty !== null ? (float) $product->stock_qty : null;
        $noBackorder = (bool) ($product->no_backorder ?? false);

        if (!$noBackorder || $stockQty === null) {
            return null;
        }

        $maxQuantity = (int) floor($stockQty);

        if ($maxQuantity < $quantityMin) {
            return 0;
        }

        return $maxQuantity;
    }

    private function canAddToCart(Product $product, int $quantityMin): bool
    {
        $maxCartQuantity = $this->resolveMaxCartQuantity($product, $quantityMin);

        return $maxCartQuantity === null || $maxCartQuantity >= $quantityMin;
    }

    private function hasVariantAxis(Collection $variantPresentation, string $key): bool
    {
        return $variantPresentation
            ->pluck($key . '.value')
            ->filter(fn ($value) => filled($value))
            ->unique()
            ->count() > 1;
    }

    private function normalizePriceBreaks(array|Collection $priceBreaks): Collection
    {
        return collect($priceBreaks)
            ->map(function ($row) {
                $resolvedPrice = $row['price'] ?? $row['price_net'] ?? null;

                return [
                    'qty_from' => isset($row['qty_from']) ? (float) $row['qty_from'] : 0,
                    'qty_to' => isset($row['qty_to']) && $row['qty_to'] !== null ? (float) $row['qty_to'] : null,
                    'price' => $resolvedPrice !== null ? (float) $resolvedPrice : null,
                    'price_net' => isset($row['price_net']) && $row['price_net'] !== null ? (float) $row['price_net'] : null,
                    'listino_id' => isset($row['listino_id']) ? (int) $row['listino_id'] : null,
                ];
            })
            ->filter(fn (array $row) => $row['price'] !== null)
            ->values();
    }

    private function shouldHideFromTechnicalRows(array $item, bool $hasColorVariants, bool $hasFormatVariants): bool
    {
        return false;
    }
}
