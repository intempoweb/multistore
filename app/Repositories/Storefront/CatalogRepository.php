<?php

namespace App\Repositories\Storefront;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\GroupDescription;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\Store;
use App\Models\StoreVisibleGroup;
use App\Services\Storefront\Cart\CartItemService;
use App\Services\Storefront\Pricing\ProductPriceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CatalogRepository
{
    private array $categoryLabelCache = [];
    private array $visibleGroupCodesCache = [];
    private array $categoryListingSkusCache = [];
    private array $categoryFacetCache = [];
    private array $navigationTreeCache = [];
    private array $effectivePriceCache = [];

    public function getRootCategories(Store $store, string $locale): Collection
    {
        $famCodes = $this->pluckNormalizedDistinct(
            $this->baseVisibleProductsQuery($store),
            'fam_99'
        );

        if ($famCodes->isEmpty()) {
            return collect();
        }

        return $famCodes->map(function (string $famCode) use ($store, $locale) {
            $label = $this->categoryLabel($store, $locale, $famCode);

            return [
                'level' => 'famiglia',
                'fam_code' => $famCode,
                'sfam_code' => null,
                'gruppo_code' => null,
                'sgruppo_code' => null,
                'code' => $famCode,
                'label' => $label,
                'description' => $label,
                'slug' => $this->buildCategorySlug($store, $locale, $famCode),
                'path' => [
                    'fam' => $famCode,
                    'sfam' => null,
                    'gruppo' => null,
                    'sgruppo' => null,
                ],
            ];
        })->values();
    }

    public function getChildrenCategories(
        Store $store,
        string $locale,
        ?string $fam = null,
        ?string $sfam = null,
        ?string $gruppo = null
    ): Collection {
        $fam = Product::normalizeErpCodeValue($fam);
        $sfam = Product::normalizeErpCodeValue($sfam);
        $gruppo = Product::normalizeErpCodeValue($gruppo);

        if ($fam === null) {
            return $this->getRootCategories($store, $locale);
        }

        $query = $this->baseVisibleProductsQuery($store)
            ->forCategoryTree($fam, $sfam, $gruppo, null);

        if ($sfam === null) {
            $sfamCodes = $this->pluckNormalizedDistinct($query, 'sfam_99');

            return $sfamCodes->map(function (string $sfamCode) use ($store, $locale, $fam) {
                $label = $this->categoryLabel($store, $locale, $fam, $sfamCode);

                return [
                    'level' => 'sottofamiglia',
                    'fam_code' => $fam,
                    'sfam_code' => $sfamCode,
                    'gruppo_code' => null,
                    'sgruppo_code' => null,
                    'code' => $sfamCode,
                    'label' => $label,
                    'description' => $label,
                    'slug' => $this->buildCategorySlug($store, $locale, $fam, $sfamCode),
                    'path' => [
                        'fam' => $fam,
                        'sfam' => $sfamCode,
                        'gruppo' => null,
                        'sgruppo' => null,
                    ],
                ];
            })->values();
        }

        if ($gruppo === null) {
            $gruppoCodes = $this->pluckNormalizedDistinct($query, 'gruppo_99');

            return $gruppoCodes->map(function (string $gruppoCode) use ($store, $locale, $fam, $sfam) {
                $label = $this->categoryLabel($store, $locale, $fam, $sfam, $gruppoCode);

                return [
                    'level' => 'gruppo',
                    'fam_code' => $fam,
                    'sfam_code' => $sfam,
                    'gruppo_code' => $gruppoCode,
                    'sgruppo_code' => null,
                    'code' => $gruppoCode,
                    'label' => $label,
                    'description' => $label,
                    'slug' => $this->buildCategorySlug($store, $locale, $fam, $sfam, $gruppoCode),
                    'path' => [
                        'fam' => $fam,
                        'sfam' => $sfam,
                        'gruppo' => $gruppoCode,
                        'sgruppo' => null,
                    ],
                ];
            })->values();
        }

        return collect();
    }

    public function getCategoryMeta(
        Store $store,
        string $locale,
        ?string $fam = null,
        ?string $sfam = null,
        ?string $gruppo = null,
        ?string $sgruppo = null
    ): array {
        $fam = Product::normalizeErpCodeValue($fam);
        $sfam = Product::normalizeErpCodeValue($sfam);
        $gruppo = Product::normalizeErpCodeValue($gruppo);
        $sgruppo = Product::normalizeErpCodeValue($sgruppo);

        $code = $sgruppo ?? $gruppo ?? $sfam ?? $fam;
        $label = $this->categoryLabel($store, $locale, $fam, $sfam, $gruppo, $sgruppo);

        $level = match (true) {
            $fam !== null && $sfam === null => 'famiglia',
            $fam !== null && $sfam !== null && $gruppo === null => 'sottofamiglia',
            $fam !== null && $sfam !== null && $gruppo !== null => 'gruppo',
            default => 'categoria',
        };

        return [
            'level' => $level,
            'code' => $code,
            'label' => $label ?: 'Categoria',
            'description' => $label,
            'slug' => $this->buildCategorySlug($store, $locale, $fam, $sfam, $gruppo, $sgruppo),
            'path' => [
                'fam' => $fam,
                'sfam' => $sfam,
                'gruppo' => $gruppo,
                'sgruppo' => $sgruppo,
            ],
        ];
    }

    public function parseLegacyCategorySlug(string $slug): array
    {
        $parts = collect(explode('/', trim($slug, '/')))
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->values();

        return [
            'fam' => Product::normalizeErpCodeValue($parts->get(0)),
            'sfam' => Product::normalizeErpCodeValue($parts->get(1)),
            'gruppo' => Product::normalizeErpCodeValue($parts->get(2)),
            'sgruppo' => Product::normalizeErpCodeValue($parts->get(3)),
        ];
    }

    public function parseCategorySlug(Store $store, string $locale, string $slug): array
    {
        $parts = collect(explode('/', trim($slug, '/')))
            ->map(fn ($part) => Str::slug((string) $part))
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return [
                'fam' => null,
                'sfam' => null,
                'gruppo' => null,
                'sgruppo' => null,
            ];
        }

        $root = $this->getRootCategories($store, $locale)
            ->first(fn (array $item) => basename((string) $item['slug']) === $parts->get(0));

        if (!$root) {
            return $this->parseLegacyCategorySlug($slug);
        }

        $fam = $root['fam_code'] ?? null;
        $sfam = null;
        $gruppo = null;

        if ($parts->count() >= 2) {
            $sfamRow = $this->getChildrenCategories($store, $locale, $fam)
                ->first(fn (array $item) => basename((string) $item['slug']) === $parts->get(1));

            $sfam = $sfamRow['sfam_code'] ?? null;
        }

        if ($parts->count() >= 3 && $sfam !== null) {
            $gruppoRow = $this->getChildrenCategories($store, $locale, $fam, $sfam)
                ->first(fn (array $item) => basename((string) $item['slug']) === $parts->get(2));

            $gruppo = $gruppoRow['gruppo_code'] ?? null;
        }

        return [
            'fam' => $fam,
            'sfam' => $sfam,
            'gruppo' => $gruppo,
            'sgruppo' => null,
        ];
    }

    public function resolveSeoFiltersToInternal(
        Store $store,
        string $locale,
        ?string $fam = null,
        ?string $sfam = null,
        ?string $gruppo = null,
        ?string $sgruppo = null,
        array $seoFilters = []
    ): array {
        if (empty($seoFilters)) {
            return [];
        }

        $facets = $this->getCategoryFilterFacets(
            $store,
            $locale,
            $fam,
            $sfam,
            $gruppo,
            $sgruppo
        );

        $facetsBySlug = $facets->keyBy(fn (array $facet) => (string) ($facet['slug'] ?? ''));

        $resolved = [];

        foreach ($seoFilters as $attributeSlug => $values) {
            $attributeSlug = Str::slug((string) $attributeSlug);
            $facet = $facetsBySlug->get($attributeSlug);

            if (!$facet) {
                continue;
            }

            $attributeCode = (string) ($facet['code'] ?? '');

            if ($attributeCode === '') {
                continue;
            }

            $valuesBySlug = collect($facet['values'] ?? [])
                ->keyBy(fn (array $value) => (string) ($value['slug'] ?? ''));

            foreach (collect(is_array($values) ? $values : [$values]) as $valueSlug) {
                $valueSlug = Str::slug((string) $valueSlug);
                $value = $valuesBySlug->get($valueSlug);

                if (!$value || empty($value['key'])) {
                    continue;
                }

                $resolved[$attributeCode][] = (string) $value['key'];
            }
        }

        return collect($resolved)
            ->map(fn (array $values) => collect($values)->filter()->unique()->values()->all())
            ->filter(fn (array $values) => !empty($values))
            ->all();
    }

    public function buildCategorySlug(
        Store $store,
        string $locale,
        ?string $fam = null,
        ?string $sfam = null,
        ?string $gruppo = null,
        ?string $sgruppo = null
    ): string {
        $parts = [];

        if ($fam !== null) {
            $parts[] = Str::slug($this->categoryLabel($store, $locale, $fam));
        }

        if ($fam !== null && $sfam !== null) {
            $parts[] = Str::slug($this->categoryLabel($store, $locale, $fam, $sfam));
        }

        if ($fam !== null && $sfam !== null && $gruppo !== null) {
            $parts[] = Str::slug($this->categoryLabel($store, $locale, $fam, $sfam, $gruppo));
        }

        if ($sgruppo !== null) {
            $parts[] = Str::slug($sgruppo);
        }

        return collect($parts)->filter()->implode('/');
    }

    public function getCategoryProducts(
        Store $store,
        string $locale,
        ?string $fam = null,
        ?string $sfam = null,
        ?string $gruppo = null,
        ?string $sgruppo = null,
        ?int $tipocf = null,
        ?int $clifor = null,
        int $perPage = 24,
        array $filters = [],
        string $sort = 'default'
    ): LengthAwarePaginatorContract {
        $listingSkus = $this->resolveCategoryListingSkus($store, $fam, $sfam, $gruppo, $sgruppo, $tipocf, $clifor);

        if ($listingSkus->isEmpty()) {
            return $this->emptyPaginator($perPage);
        }

        $query = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->whereIn('sku', $listingSkus->all())
            ->with($this->baseProductWithRelations());

        $this->applyAttributeFilters($query, $filters);

        $this->applyListingSort($query, $sort);

        $paginator = $query->paginate($perPage)->withQueryString();
        $items = collect($paginator->items());

        $this->attachVisibleChildrenToProducts($store, $items, $tipocf, $clifor);
        $this->enrichCategoryDescriptions($store, $items, $locale);
        $this->enrichProductPresentation($store, $items, $locale, $filters);

        return $paginator;
    }

    public function getCategoryFilterFacets(
        Store $store,
        string $locale,
        ?string $fam = null,
        ?string $sfam = null,
        ?string $gruppo = null,
        ?string $sgruppo = null,
        ?int $tipocf = null,
        ?int $clifor = null,
        array $activeFilters = []
    ): Collection {
        $cacheKey = $this->categoryScopedCacheKey(
            $store,
            $locale,
            $fam,
            $sfam,
            $gruppo,
            $sgruppo,
            $tipocf,
            $clifor
        );

        if (!array_key_exists($cacheKey, $this->categoryFacetCache)) {
            $this->categoryFacetCache[$cacheKey] = $this->buildCategoryFilterFacets(
                $store,
                $locale,
                $fam,
                $sfam,
                $gruppo,
                $sgruppo,
                $tipocf,
                $clifor
            );
        }

        return $this->categoryFacetCache[$cacheKey]
            ->map(function (array $facet) use ($activeFilters) {
                $attributeCode = (string) ($facet['code'] ?? '');
                $facet['active_values'] = collect($activeFilters[$attributeCode] ?? [])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->values();

                return $facet;
            })
            ->values();
    }

    private function buildCategoryFilterFacets(
        Store $store,
        string $locale,
        ?string $fam = null,
        ?string $sfam = null,
        ?string $gruppo = null,
        ?string $sgruppo = null,
        ?int $tipocf = null,
        ?int $clifor = null
    ): Collection {
        $listingSkus = $this->resolveCategoryListingSkus($store, $fam, $sfam, $gruppo, $sgruppo, $tipocf, $clifor);

        if ($listingSkus->isEmpty()) {
            return collect();
        }

        $facetProducts = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->where(function (Builder $query) use ($listingSkus) {
                $query->whereIn('sku', $listingSkus->all())
                    ->orWhere(function (Builder $children) use ($listingSkus) {
                        $children
                            ->where('type', 'simple')
                            ->whereIn('parent_code', $listingSkus->all());
                    });
            })
            ->whereHas('productAttributeValues.attribute', fn (Builder $attribute) => $attribute->where('is_filterable', true))
            ->with([
                'productAttributeValues' => function ($query) {
                    $query->whereHas('attribute', fn (Builder $attribute) => $attribute->where('is_filterable', true));
                },
                'productAttributeValues.attribute.translations',
                'productAttributeValues.value.translations',
                'productAttributeValues.value.mediaAssets',
            ])
            ->get(['id', 'sku', 'parent_code', 'type', 'ditta_cg18', 'site_type']);

        return $facetProducts
            ->flatMap(fn (Product $product) => collect($product->productAttributeValues ?? []))
            ->filter(fn ($row) => $row->attribute instanceof Attribute && (bool) $row->attribute->is_filterable)
            ->groupBy(fn ($row) => (int) $row->attribute_id)
            ->map(function (Collection $rows) use ($locale) {
                $first = $rows->first();
                $attribute = $first?->attribute;

                if (!$attribute instanceof Attribute) {
                    return null;
                }

                $attributeCode = (string) $attribute->code;
                $attributeLabel = $attribute->translationOrFallback($locale)?->label ?? $attributeCode;
                $attributeSlug = Str::slug($attributeLabel);

                $values = $rows
                    ->map(function ($row) use ($locale) {
                        $valueModel = $row->value;
                        $valueTranslation = $valueModel?->translationOrFallback($locale);
                        $rawValue = trim((string) ($row->raw_value ?? ''));

                        $valueKey = (string) ($row->value_key ?: ProductAttributeValue::makeValueKey(
                            $row->attribute_value_id ? (int) $row->attribute_value_id : null,
                            $rawValue !== '' ? $rawValue : null
                        ));

                        $valueLabel = $valueTranslation?->label
                            ?? $valueModel?->value_code
                            ?? ($rawValue !== '' ? $rawValue : '—');

                        return [
                            'key' => $valueKey,
                            'label' => $valueLabel,
                            'slug' => Str::slug((string) $valueLabel),
                            'value_code' => $valueModel?->value_code,
                            'raw_value' => $rawValue !== '' ? $rawValue : null,
                            'swatch_url' => $valueModel instanceof AttributeValue ? $this->attributeValueSwatchUrl($valueModel) : null,
                        ];
                    })
                    ->filter(fn (array $value) => trim((string) ($value['key'] ?? '')) !== '')
                    ->groupBy('key')
                    ->map(function (Collection $group) {
                        $first = $group->first();

                        return [
                            'key' => $first['key'],
                            'label' => $first['label'],
                            'slug' => $first['slug'],
                            'value_code' => $first['value_code'] ?? null,
                            'raw_value' => $first['raw_value'] ?? null,
                            'swatch_url' => $first['swatch_url'] ?? null,
                            'count' => $group->count(),
                        ];
                    })
                    ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values();

                if ($values->isEmpty()) {
                    return null;
                }

                return [
                    'id' => (int) $attribute->id,
                    'code' => $attributeCode,
                    'label' => $attributeLabel,
                    'slug' => $attributeSlug,
                    'type' => $attribute->type,
                    'sort_order' => (int) ($attribute->sort_order ?? 0),
                    'is_variant' => (bool) ($attribute->is_variant ?? false),
                    'active_values' => collect(),
                    'values' => $values,
                ];
            })
            ->filter()
            ->sortBy([
                ['sort_order', 'asc'],
                ['label', 'asc'],
            ])
            ->values();
    }

    public function getNavigationTree(Store $store, string $locale): Collection
    {
        [$tipocf, $clifor] = $this->resolveStorefrontCustomerContext($store, null, null);

        $cacheKey = $this->categoryScopedCacheKey(
            $store,
            $locale,
            null,
            null,
            null,
            null,
            $tipocf,
            $clifor
        );

        if (array_key_exists($cacheKey, $this->navigationTreeCache)) {
            return $this->navigationTreeCache[$cacheKey];
        }

        return $this->navigationTreeCache[$cacheKey] = $this->getRootCategories($store, $locale)
            ->map(function (array $famiglia) use ($store, $locale) {
                $famiglia['children'] = $this->getChildrenCategories($store, $locale, $famiglia['fam_code'] ?? null)
                    ->map(function (array $sottofamiglia) use ($store, $locale) {
                        $sottofamiglia['children'] = $this->getChildrenCategories(
                            $store,
                            $locale,
                            $sottofamiglia['fam_code'] ?? null,
                            $sottofamiglia['sfam_code'] ?? null
                        )->values();

                        return $sottofamiglia;
                    })
                    ->values();

                return $famiglia;
            })
            ->values();
    }

    public function getProductBySku(
        Store $store,
        string $locale,
        string $sku,
        ?int $tipocf = null,
        ?int $clifor = null
    ): ?Product {
        $product = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->with($this->baseProductWithRelations())
            ->where('sku', trim($sku))
            ->first();

        if (!$product) {
            return null;
        }

        $this->attachResolvedConfigurableContext($store, $product, $tipocf, $clifor);

        $productsToEnrich = collect([$product]);
        $baseProduct = $product->getAttribute('resolved_base_product');

        if ($baseProduct instanceof Product && $baseProduct->getKey() !== $product->getKey()) {
            $productsToEnrich->push($baseProduct);
        }

        $this->enrichCategoryDescriptions($store, $productsToEnrich, $locale);
        $this->enrichProductPresentation($store, $productsToEnrich, $locale);

        $variantProducts = $product->getAttribute('resolved_variant_products') ?? new EloquentCollection();

        if ($variantProducts instanceof EloquentCollection && $variantProducts->isNotEmpty()) {
            $this->enrichCategoryDescriptions($store, $variantProducts, $locale);
            $this->enrichProductPresentation($store, $variantProducts, $locale);
        }

        return $product;
    }

    public function searchProducts(
        Store $store,
        string $locale,
        string $query,
        ?int $tipocf = null,
        ?int $clifor = null,
        int $perPage = 24,
        string $sort = 'default'
    ): LengthAwarePaginatorContract {
        $query = trim($query);

        Log::debug('Storefront search started', [
            'store_id' => $store->id ?? null,
            'store_name' => $store->name ?? null,
            'ditta_cg18' => $store->ditta_cg18 ?? null,
            'erp_site_code' => $store->erp_site_code ?? null,
            'site_code' => $store->site_code ?? null,
            'is_b2b' => (bool) ($store->is_b2b ?? false),
            'locale' => $locale,
            'query' => $query,
            'query_length' => mb_strlen($query),
            'tipocf' => $tipocf,
            'clifor' => $clifor,
            'per_page' => $perPage,
            'sort' => $sort,
        ]);

        if (mb_strlen($query) < 2) {
            Log::debug('Storefront search stopped: query too short', [
                'query' => $query,
                'query_length' => mb_strlen($query),
            ]);

            return $this->emptyPaginator($perPage);
        }

        $matchedProducts = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->where(function (Builder $builder) use ($query, $locale) {
                $this->applySearchConstraint($builder, $query, $locale);
            })
            ->get(['sku', 'parent_code', 'type']);

        Log::debug('Storefront search matched raw products', [
            'query' => $query,
            'matched_count' => $matchedProducts->count(),
            'sample' => $matchedProducts
                ->take(10)
                ->map(fn ($product) => [
                    'sku' => $product->sku,
                    'parent_code' => $product->parent_code,
                    'type' => $product->type,
                ])
                ->values()
                ->all(),
        ]);

        if ($matchedProducts->isEmpty()) {
            Log::debug('Storefront search stopped: no raw product matches', [
                'query' => $query,
            ]);

            return $this->emptyPaginator($perPage);
        }

        $parentCodes = $matchedProducts
            ->pluck('parent_code')
            ->map(fn ($code) => Product::normalizeErpCodeValue($code))
            ->filter()
            ->unique()
            ->values();

        Log::debug('Storefront search parent codes resolved', [
            'query' => $query,
            'parent_codes_count' => $parentCodes->count(),
            'parent_codes_sample' => $parentCodes->take(20)->values()->all(),
        ]);

        $activeParentSkuSet = [];

        if ($parentCodes->isNotEmpty()) {
            $activeParentSkuSet = Product::query()
                ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
                ->active()
                ->where('type', 'configurable')
                ->whereIn('sku', $parentCodes->all())
                ->pluck('sku')
                ->map(fn ($sku) => trim((string) $sku))
                ->filter()
                ->unique()
                ->flip()
                ->all();
        }

        Log::debug('Storefront search active configurable parents resolved', [
            'query' => $query,
            'active_parent_count' => count($activeParentSkuSet),
            'active_parent_sample' => array_slice(array_keys($activeParentSkuSet), 0, 20),
        ]);

        $listingSkus = $matchedProducts
            ->map(function ($product) use ($activeParentSkuSet) {
                if ($product->type === 'configurable') {
                    return trim((string) $product->sku);
                }

                $parentCode = Product::normalizeErpCodeValue($product->parent_code);

                if ($parentCode !== null && isset($activeParentSkuSet[$parentCode])) {
                    return $parentCode;
                }

                return trim((string) $product->sku);
            })
            ->filter()
            ->unique()
            ->values();

        Log::debug('Storefront search listing SKUs resolved', [
            'query' => $query,
            'listing_skus_count' => $listingSkus->count(),
            'listing_skus_sample' => $listingSkus->take(30)->values()->all(),
        ]);

        if ($listingSkus->isEmpty()) {
            Log::debug('Storefront search stopped: no listing SKUs', [
                'query' => $query,
            ]);

            return $this->emptyPaginator($perPage);
        }

        $productsQuery = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->whereIn('sku', $listingSkus->all())
            ->with($this->baseProductWithRelations());

        $this->applyListingSort($productsQuery, $sort);

        $paginator = $productsQuery->paginate($perPage)->withQueryString();
        $items = collect($paginator->items());

        Log::debug('Storefront search paginated products', [
            'query' => $query,
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'items_count' => $items->count(),
            'items_sample' => $items
                ->take(10)
                ->map(fn ($product) => [
                    'sku' => $product instanceof Product ? $product->sku : null,
                    'parent_code' => $product instanceof Product ? $product->parent_code : null,
                    'type' => $product instanceof Product ? $product->type : null,
                ])
                ->values()
                ->all(),
        ]);

        $this->attachVisibleChildrenToProducts($store, $items, $tipocf, $clifor);
        $this->enrichCategoryDescriptions($store, $items, $locale);
        $this->enrichProductPresentation($store, $items, $locale);

        Log::debug('Storefront search completed', [
            'query' => $query,
            'total' => $paginator->total(),
            'items_count' => $items->count(),
        ]);

        return $paginator;
    }

    public function suggestProducts(
        Store $store,
        string $locale,
        string $query,
        ?int $tipocf = null,
        ?int $clifor = null,
        int $limit = 6
    ): Collection {
        $products = $this->searchProducts(
            store: $store,
            locale: $locale,
            query: $query,
            tipocf: $tipocf,
            clifor: $clifor,
            perPage: $limit,
            sort: 'default'
        );

        return collect($products->items())
            ->map(function (Product $product) use ($store, $locale) {
                $variantOptions = collect($product->listing_variant_options ?? []);
                $selectedSku = (string) ($product->listing_target_sku ?? $product->sku);

                $selectedVariant = $variantOptions->first(
                    fn (array $variant) => (string) ($variant['sku'] ?? '') === $selectedSku
                ) ?? $variantOptions->first();

                $selectedProduct = $this->resolveSuggestionSelectedProduct($product, $selectedSku);

                $image = $selectedVariant['image']
                    ?? $selectedProduct->main_image_url
                    ?? $product->main_image_url
                    ?? null;

                $category = $this->resolveSuggestionCategoryLabel(
                    store: $store,
                    locale: $locale,
                    product: $product,
                    selectedProduct: $selectedProduct
                );

                $selectedVariant = is_array($selectedVariant) ? $selectedVariant : [];
                $color = $this->resolveSuggestionVariantAttributeLabel($selectedVariant, 'color');
                $format = $this->resolveSuggestionVariantAttributeLabel($selectedVariant, 'format');
                $description = $this->resolveSuggestionDescription($product, $selectedProduct, $locale);

                return [
                    'sku' => $selectedSku,
                    'product_sku' => $selectedSku,
                    'name' => (string) ($product->display_name ?? $selectedProduct->display_name ?? $product->sku),
                    'description' => $description,
                    'short_description' => $description,
                    'url' => route('storefront.product.show', $selectedSku),
                    'image' => $image,
                    'thumbnail' => $image,
                    'price' => $product->effective_price !== null
                        ? '€ ' . number_format((float) $product->effective_price, 3, ',', '.')
                        : null,
                    'category' => $category,
                    'category_path' => $category,
                    'collection' => null,
                    'collection_label' => null,
                    'color' => $color,
                    'color_label' => $color,
                    'format' => $format,
                    'format_label' => $format,
                    'quantity_min' => (int) ($selectedVariant['quantity_min'] ?? 1),
                    'quantity_step' => (int) ($selectedVariant['quantity_step'] ?? 1),
                    'pack_multiple' => (int) ($selectedVariant['pack_multiple'] ?? 1),
                    'min_order_qty' => (int) ($selectedVariant['min_order_qty'] ?? 1),
                ];
            })
            ->values();
    }

    private function resolveSuggestionSelectedProduct(Product $product, string $selectedSku): Product
    {
        if (trim((string) $product->sku) === trim($selectedSku)) {
            return $product;
        }

        $children = $product->relationLoaded('children')
            ? collect($product->getRelation('children'))
            : collect();

        $selectedChild = $children->first(
            fn ($child) => $child instanceof Product && trim((string) $child->sku) === trim($selectedSku)
        );

        return $selectedChild instanceof Product ? $selectedChild : $product;
    }

    private function resolveSuggestionCategoryLabel(
        Store $store,
        string $locale,
        Product $product,
        ?Product $selectedProduct = null
    ): ?string {
        $source = $selectedProduct instanceof Product ? $selectedProduct : $product;

        $existingCategory = trim((string) ($source->category_path_description ?? ''));

        if ($existingCategory !== '') {
            return $existingCategory;
        }

        $fam = Product::normalizeErpCodeValue($source->fam_99) ?: Product::normalizeErpCodeValue($product->fam_99);
        $sfam = Product::normalizeErpCodeValue($source->sfam_99) ?: Product::normalizeErpCodeValue($product->sfam_99);
        $gruppo = Product::normalizeErpCodeValue($source->gruppo_99) ?: Product::normalizeErpCodeValue($product->gruppo_99);
        $sgruppo = Product::normalizeErpCodeValue($source->sgruppo_99) ?: Product::normalizeErpCodeValue($product->sgruppo_99);

        $famDescription = $fam ? $this->categoryLabel($store, $locale, $fam) : null;
        $sfamDescription = ($fam && $sfam) ? $this->categoryLabel($store, $locale, $fam, $sfam) : null;
        $gruppoDescription = ($fam && $sfam && $gruppo) ? $this->categoryLabel($store, $locale, $fam, $sfam, $gruppo) : null;

        $category = collect([
            $famDescription,
            $sfamDescription,
            $gruppoDescription,
            $sgruppo,
        ])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->implode(' / ');

        return $category !== '' ? $category : null;
    }


    private function resolveSuggestionDescription(Product $product, Product $selectedProduct, string $locale): ?string
    {
        $selectedTranslation = $selectedProduct->translationOrFallback($locale);
        $productTranslation = $product->translationOrFallback($locale);

        $description = trim((string) (
            $selectedProduct->display_short_description
            ?? $selectedTranslation?->short_description
            ?? $selectedProduct->display_description
            ?? $selectedTranslation?->description
            ?? $product->display_short_description
            ?? $productTranslation?->short_description
            ?? $product->display_description
            ?? $productTranslation?->description
            ?? ''
        ));

        return $description !== '' ? $description : null;
    }

    private function resolveSuggestionVariantAttributeLabel(array $selectedVariant, string $key): ?string
    {
        $value = trim((string) ($selectedVariant[$key]['value'] ?? ''));

        return $value !== '' ? $value : null;
    }

    private function categoryLabel(
        Store $store,
        string $locale,
        ?string $fam = null,
        ?string $sfam = null,
        ?string $gruppo = null,
        ?string $sgruppo = null
    ): string {
        $fam = Product::normalizeErpCodeValue($fam);
        $sfam = Product::normalizeErpCodeValue($sfam);
        $gruppo = Product::normalizeErpCodeValue($gruppo);
        $sgruppo = Product::normalizeErpCodeValue($sgruppo);

        $fallback = $sgruppo ?? $gruppo ?? $sfam ?? $fam ?? 'Categoria';
        $cacheKey = implode('|', [
            (int) $store->ditta_cg18,
            (int) $store->erp_site_code,
            $locale,
            $fam,
            $sfam,
            $gruppo,
            $sgruppo,
        ]);

        if (array_key_exists($cacheKey, $this->categoryLabelCache)) {
            return $this->categoryLabelCache[$cacheKey];
        }

        $query = GroupDescription::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
            ->forLocale($locale)
            ->active();

        if ($fam !== null && $sfam === null) {
            $query->famiglie()->where('fam_code', $fam);
        } elseif ($fam !== null && $sfam !== null && $gruppo === null) {
            $query->sottofamiglie()
                ->where('fam_code', $fam)
                ->where('sfam_code', $sfam);
        } elseif ($fam !== null && $sfam !== null && $gruppo !== null) {
            $query->gruppi()
                ->where('fam_code', $fam)
                ->where('sfam_code', $sfam)
                ->where('gruppo_code', $gruppo);
        } else {
            $this->categoryLabelCache[$cacheKey] = $fallback;

            return $fallback;
        }

        $label = $query->value('description') ?: $fallback;
        $this->categoryLabelCache[$cacheKey] = $label;

        return $label;
    }

    private function baseVisibleProductsQuery(Store $store, ?int $tipocf = null, ?int $clifor = null): Builder
    {
        [$tipocf, $clifor] = $this->resolveStorefrontCustomerContext($store, $tipocf, $clifor);

        if ($store->is_b2b && $tipocf !== null && $tipocf > 0 && $clifor !== null && $clifor > 0) {
            return Product::query()->visibleForCustomer(
                (int) $store->erp_site_code,
                $tipocf,
                $clifor
            );
        }

        $query = Product::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
            ->active();

        if (!$store->is_b2b) {
            return $query;
        }

        $visibleGroupCodes = $this->visibleGroupCodes($store);

        return $query->where(function (Builder $outer) use ($visibleGroupCodes) {
            $outer->where(function (Builder $simple) use ($visibleGroupCodes) {
                $simple
                    ->where('type', 'simple')
                    ->where(function (Builder $visibility) use ($visibleGroupCodes) {
                        if ($visibleGroupCodes->isNotEmpty()) {
                            $visibility->whereIn('codgrupfis_mg61', $visibleGroupCodes->all());
                        } else {
                            $visibility->whereRaw('1 = 0');
                        }

                        $visibility->orWhere(function (Builder $nullGroupException) {
                            $nullGroupException
                                ->whereNull('codgrupfis_mg61')
                                ->where('fam_99', 'H');
                        });
                    });
            });

            $outer->orWhere(function (Builder $configurable) use ($visibleGroupCodes) {
                $configurable
                    ->where('type', 'configurable')
                    ->whereExists(function ($sub) use ($visibleGroupCodes) {
                        $sub->selectRaw('1')
                            ->from('products as c')
                            ->whereColumn('c.parent_code', 'products.sku')
                            ->whereColumn('c.ditta_cg18', 'products.ditta_cg18')
                            ->whereColumn('c.site_type', 'products.site_type')
                            ->where('c.type', 'simple')
                            ->where('c.is_active', 1)
                            ->where(function ($visibility) use ($visibleGroupCodes) {
                                if ($visibleGroupCodes->isNotEmpty()) {
                                    $visibility->whereIn('c.codgrupfis_mg61', $visibleGroupCodes->all());
                                } else {
                                    $visibility->whereRaw('1 = 0');
                                }

                                $visibility->orWhere(function ($nullGroupException) {
                                    $nullGroupException
                                        ->whereNull('c.codgrupfis_mg61')
                                        ->where('c.fam_99', 'H');
                                });
                            });
                    });
            });
        });
    }

    private function resolveCategoryListingSkus(
        Store $store,
        ?string $fam = null,
        ?string $sfam = null,
        ?string $gruppo = null,
        ?string $sgruppo = null,
        ?int $tipocf = null,
        ?int $clifor = null
    ): Collection {
        $cacheKey = $this->categoryScopedCacheKey(
            $store,
            null,
            $fam,
            $sfam,
            $gruppo,
            $sgruppo,
            $tipocf,
            $clifor
        );

        if (array_key_exists($cacheKey, $this->categoryListingSkusCache)) {
            return $this->categoryListingSkusCache[$cacheKey];
        }

        $categoryProducts = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->forCategoryTree($fam, $sfam, $gruppo, $sgruppo)
            ->get(['sku', 'parent_code', 'type']);

        if ($categoryProducts->isEmpty()) {
            return $this->categoryListingSkusCache[$cacheKey] = collect();
        }

        $parentCodes = $categoryProducts
            ->pluck('parent_code')
            ->map(fn ($code) => Product::normalizeErpCodeValue($code))
            ->filter()
            ->unique()
            ->values();

        $activeConfigurableParents = collect();

        if ($parentCodes->isNotEmpty()) {
            $activeConfigurableParents = Product::query()
                ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
                ->active()
                ->where('type', 'configurable')
                ->whereIn('sku', $parentCodes->all())
                ->pluck('sku')
                ->map(fn ($sku) => trim((string) $sku))
                ->filter()
                ->unique()
                ->values();
        }

        $activeParentSkuSet = array_flip($activeConfigurableParents->all());

        return $this->categoryListingSkusCache[$cacheKey] = $categoryProducts
            ->map(function ($product) use ($activeParentSkuSet) {
                if ($product->type === 'configurable') {
                    return trim((string) $product->sku);
                }

                $parentCode = Product::normalizeErpCodeValue($product->parent_code);

                if ($parentCode !== null && isset($activeParentSkuSet[$parentCode])) {
                    return $parentCode;
                }

                return trim((string) $product->sku);
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function attachVisibleChildrenToProducts(Store $store, Collection $products, ?int $tipocf = null, ?int $clifor = null): void
    {
        $configurableProducts = $products
            ->filter(fn ($product) => $product instanceof Product && $product->type === 'configurable')
            ->values();

        if ($configurableProducts->isEmpty()) {
            return;
        }

        $parentSkus = $configurableProducts
            ->map(fn (Product $product) => trim((string) $product->sku))
            ->filter()
            ->unique()
            ->values();

        if ($parentSkus->isEmpty()) {
            return;
        }

        $children = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->where('type', 'simple')
            ->whereIn('parent_code', $parentSkus->all())
            ->with($this->baseProductWithRelations())
            ->orderBy('sku')
            ->get();

        $childrenByParent = $children->groupBy(fn (Product $product) => trim((string) $product->parent_code));

        foreach ($configurableProducts as $product) {
            $group = $childrenByParent->get(trim((string) $product->sku), collect());
            $product->setRelation('children', new EloquentCollection($group->all()));
        }
    }

    private function attachResolvedConfigurableContext(Store $store, Product $product, ?int $tipocf = null, ?int $clifor = null): void
    {
        $product->loadMissing('comparisons');

        if ($product->type === 'configurable') {
            $this->attachVisibleChildrenToProducts($store, collect([$product]), $tipocf, $clifor);

            $variantProducts = $product->getRelation('children');

            if (!$variantProducts instanceof EloquentCollection || $variantProducts->isEmpty()) {
                $variantProducts = new EloquentCollection([$product]);
            }

            $variantProducts->loadMissing('comparisons');

            $selectedProduct = $variantProducts->first() instanceof Product
                ? $variantProducts->first()
                : $product;

            $product->setAttribute('resolved_base_product', $product);
            $product->setAttribute('resolved_selected_product', $selectedProduct);
            $product->setAttribute('resolved_variant_products', $variantProducts);

            return;
        }

        $parentCode = Product::normalizeErpCodeValue($product->parent_code);
        $baseProduct = null;
        $variantProducts = new EloquentCollection([$product]);

        if ($parentCode !== null) {
            $baseProduct = Product::query()
                ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
                ->active()
                ->where('type', 'configurable')
                ->with($this->baseProductWithRelations())
                ->where('sku', $parentCode)
                ->first();

            if ($baseProduct instanceof Product) {
                $this->attachVisibleChildrenToProducts($store, collect([$baseProduct]), $tipocf, $clifor);

                $children = $baseProduct->getRelation('children');

                if ($children instanceof EloquentCollection && $children->isNotEmpty()) {
                    $variantProducts = $children;
                }
            }
        }

        $variantProducts->loadMissing('comparisons');

        $product->setAttribute('resolved_base_product', $baseProduct ?: $product);
        $product->setAttribute('resolved_selected_product', $product);
        $product->setAttribute('resolved_variant_products', $variantProducts);
    }

    private function enrichCategoryDescriptions(Store $store, Collection $products, string $locale): void
    {
        if ($products->isEmpty()) {
            return;
        }

        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $fam = Product::normalizeErpCodeValue($product->fam_99);
            $sfam = Product::normalizeErpCodeValue($product->sfam_99);
            $gruppo = Product::normalizeErpCodeValue($product->gruppo_99);
            $sgruppo = Product::normalizeErpCodeValue($product->sgruppo_99);

            $famDescription = $fam ? $this->categoryLabel($store, $locale, $fam) : null;
            $sfamDescription = ($fam && $sfam) ? $this->categoryLabel($store, $locale, $fam, $sfam) : null;
            $gruppoDescription = ($fam && $sfam && $gruppo) ? $this->categoryLabel($store, $locale, $fam, $sfam, $gruppo) : null;

            $product->setAttribute('fam_description', $famDescription);
            $product->setAttribute('sfam_description', $sfamDescription);
            $product->setAttribute('gruppo_description', $gruppoDescription);
            $product->setAttribute('sgruppo_description', $sgruppo ?: null);
            $product->setAttribute(
                'category_path_description',
                collect([$famDescription, $sfamDescription, $gruppoDescription, $sgruppo])->filter()->implode(' / ')
            );
        }
    }

    private function enrichProductPresentation(Store $store, Collection $products, string $locale, array $activeFilters = []): void
    {
        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $translation = $product->translationOrFallback($locale);
            $productMainImageUrl = $this->mainImageUrlFromLoadedMedia($product);

            $product->setAttribute('display_name', $translation?->name ?? $product->sku);
            $product->setAttribute('display_description', $translation?->description);
            $product->setAttribute('display_short_description', $translation?->short_description);
            $product->setAttribute('display_seo_title', $translation?->seo_title ?? $translation?->name ?? $product->sku);
            $product->setAttribute('display_seo_description', $translation?->seo_description);
            $product->setAttribute('main_image_url', $productMainImageUrl);
            $product->setAttribute('effective_price', $this->resolveEffectivePrice($store, $product));
            $product->setAttribute('is_sellable', $this->isSellable($store, $product));

            $variantProducts = collect();

            if ($product->type === 'configurable') {
                $children = $product->getRelation('children');
                $variantProducts = $children instanceof EloquentCollection
                    ? $children->where('is_active', true)->values()
                    : collect();
            } elseif ($product->type === 'simple') {
                $variantProducts = collect([$product]);
            }

            $variantOptions = $variantProducts->map(function (Product $variant) use ($store, $locale) {
                $attributeRows = $variant->productAttributeValues
                    ->sortBy(function ($item) {
                        return [
                            (int) ($item->attribute->sort_order ?? 0),
                            (string) ($item->attribute->code ?? ''),
                        ];
                    })
                    ->values();

                $presentation = $attributeRows->map(function ($row) use ($locale) {
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
                    $normalizedLabel = mb_strtolower(trim((string) $attributeLabel));
                    $normalizedCode = mb_strtolower($attributeCode);
                    $swatchUrl = $row->value instanceof AttributeValue
                        ? $this->attributeValueSwatchUrl($row->value)
                        : null;

                    return [
                        'code' => $attributeCode !== '' ? $attributeCode : null,
                        'label' => $attributeLabel,
                        'value' => $attributeValue,
                        'normalized_label' => $normalizedLabel,
                        'normalized_code' => $normalizedCode,
                        'swatch_url' => $swatchUrl,
                    ];
                })->values();

                $mainImageUrl = $this->mainImageUrlFromLoadedMedia($variant);
                $hoverImageUrl = $this->hoverImageUrlFromLoadedMedia($variant, $mainImageUrl);
                $quantityConstraints = $this->resolveListingQuantityConstraints($variant);
                $variantEffectivePrice = $this->resolveEffectivePrice(
                    $store,
                    $variant,
                    (float) ($quantityConstraints['quantity_min'] ?? 1)
                );

                return [
                    'sku' => $variant->sku,
                    'label' => $variant->translationOrFallback($locale)?->name ?? $variant->sku,
                    'image' => $mainImageUrl,
                    'hover_image' => $hoverImageUrl,
                    'price' => $variantEffectivePrice,
                    'effective_price' => $variantEffectivePrice,
                    'public_price' => $variant->public_price !== null ? (float) $variant->public_price : null,
                    'quantity_min' => (int) ($quantityConstraints['quantity_min'] ?? 1),
                    'quantity_step' => (int) ($quantityConstraints['quantity_step'] ?? 1),
                    'pack_multiple' => (int) ($quantityConstraints['pack_multiple'] ?? 1),
                    'show_pack_multiple' => (bool) ($quantityConstraints['show_pack_multiple'] ?? false),
                    'min_order_qty' => (int) ($quantityConstraints['min_order_qty'] ?? 1),
                    'color' => $presentation->first(fn (array $row) => in_array($row['normalized_label'] ?? null, ['colore', 'color'], true)
                        || in_array($row['normalized_code'] ?? null, ['colore', 'color'], true)),
                    'format' => $presentation->first(fn (array $row) => in_array($row['normalized_label'] ?? null, ['formato', 'format'], true)
                        || in_array($row['normalized_code'] ?? null, ['formato', 'format'], true)),
                    'value_keys' => $variant->productAttributeValues
                        ->mapWithKeys(fn ($row) => [
                            (string) ($row->attribute?->code ?? '') => (string) $row->value_key,
                        ])
                        ->filter(fn ($value, $key) => $key !== '' && $value !== '')
                        ->all(),
                    'is_active' => (bool) $variant->is_active,
                    'stock_qty' => $variant->stock_qty !== null ? (float) $variant->stock_qty : null,
                    'has_image' => $mainImageUrl !== null,
                ];
            })->values();

            $listingTargetSku = $this->resolveListingTargetSkuFromVariants(
                $variantOptions,
                $activeFilters,
                $product->sku
            );

            $selectedListingOption = $variantOptions->first(
                fn (array $variant) => (string) ($variant['sku'] ?? '') === $listingTargetSku
            ) ?? $variantOptions->first();

            $listingImageUrl = $selectedListingOption['image']
                ?? $productMainImageUrl
                ?? $product->getAttribute('main_image_url');

            $listingHoverImageUrl = $selectedListingOption['hover_image'] ?? null;

            $listingPrice = $selectedListingOption['price']
                ?? $selectedListingOption['effective_price']
                ?? $product->getAttribute('effective_price');

            $product->setAttribute('effective_price', $listingPrice);
            $product->setAttribute('main_image_url', $listingImageUrl);
            $product->setAttribute('listing_hover_image_url', $listingHoverImageUrl);
            $product->setAttribute('listing_selected_color_value', $selectedListingOption['color']['value'] ?? null);
            $product->setAttribute('listing_selected_format_value', $selectedListingOption['format']['value'] ?? null);
            $product->setAttribute('listing_variant_count', $variantProducts->isNotEmpty() ? $variantProducts->count() : 1);
            $product->setAttribute('listing_has_variants', $variantProducts->count() > 1);
            $product->setAttribute('listing_target_sku', $listingTargetSku);
            $product->setAttribute('listing_variant_options', $variantOptions);
        }
    }

    private function attributeValueSwatchUrl(AttributeValue $value): ?string
    {
        $loadedAssets = $value->relationLoaded('mediaAssets')
            ? collect($value->getRelation('mediaAssets'))
            : collect();

        $swatch = $loadedAssets->firstWhere('role', MediaAsset::ROLE_SWATCH);

        if (!$swatch instanceof MediaAsset) {
            return null;
        }

        $source = $swatch->local_path
            ?? $swatch->erp_full_path
            ?? $swatch->url
            ?? null;

        return media_url($source);
    }

    private function mainImageUrlFromLoadedMedia(Product $product): ?string
    {
        $mediaAssets = collect($product->mediaAssets ?? [])
            ->filter(fn ($asset) => in_array($asset->role, [MediaAsset::ROLE_MAIN, MediaAsset::ROLE_GALLERY], true))
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $mainAsset = $mediaAssets->firstWhere('role', MediaAsset::ROLE_MAIN)
            ?? $mediaAssets->first();

        return $mainAsset?->url;
    }

    private function hoverImageUrlFromLoadedMedia(Product $product, ?string $mainImageUrl = null): ?string
    {
        return collect($product->mediaAssets ?? [])
            ->filter(fn ($asset) => in_array($asset->role, [MediaAsset::ROLE_MAIN, MediaAsset::ROLE_GALLERY], true))
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn ($asset) => $asset->url ?? null)
            ->filter(fn ($url) => $url !== null && $url !== $mainImageUrl)
            ->first();
    }

    private function resolveEffectivePrice(Store $store, Product $product, int|float $qty = 1): ?float
    {
        $publicPrice = $product->public_price !== null ? (float) $product->public_price : null;
        $customer = auth('customer')->user();

        $cacheKey = implode('|', [
            (int) ($store->id ?? 0),
            (int) ($store->ditta_cg18 ?? 0),
            $customer?->getAuthIdentifier() ?? 'guest',
            trim((string) ($product->sku ?? '')),
            number_format(max(1, (float) $qty), 3, '.', ''),
        ]);

        if (array_key_exists($cacheKey, $this->effectivePriceCache)) {
            return $this->effectivePriceCache[$cacheKey];
        }

        try {
            $resolved = app(ProductPriceService::class)->resolveForListing(
                store: $store,
                product: $product,
                qty: max(1, (float) $qty),
                customer: $customer
            );

            return $this->effectivePriceCache[$cacheKey] = array_key_exists('price', $resolved) && $resolved['price'] !== null
                ? (float) $resolved['price']
                : $publicPrice;
        } catch (\Throwable $exception) {
            return $this->effectivePriceCache[$cacheKey] = $publicPrice;
        }
    }
    private function resolveStorefrontCustomerContext(Store $store, ?int $tipocf = null, ?int $clifor = null): array
    {
        if (!$store->is_b2b) {
            return [$tipocf, $clifor];
        }

        if ($tipocf !== null && $tipocf > 0 && $clifor !== null && $clifor > 0) {
            return [$tipocf, $clifor];
        }

        $customer = auth('customer')->user();

        if (!$customer) {
            return [$tipocf, $clifor];
        }

        $resolvedTipocf = (int) ($customer->tipocf_cg44 ?? $customer->tipocf ?? 0);
        $resolvedClifor = (int) ($customer->clifor_cg44 ?? $customer->clifor ?? 0);

        return [
            $resolvedTipocf > 0 ? $resolvedTipocf : $tipocf,
            $resolvedClifor > 0 ? $resolvedClifor : $clifor,
        ];
    }

    private function isSellable(Store $store, Product $product): bool
    {
        if (!$product->is_active) {
            return false;
        }

        $qty = (float) ($product->stock_qty ?? 0);

        if (!$store->is_b2b) {
            return $qty > 0;
        }

        return $qty > 0 || !$product->no_backorder;
    }

    private function resolveListingQuantityConstraints(Product $product): array
    {
        try {
            return app(CartItemService::class)->resolveQuantityConstraintsForProduct($product);
        } catch (\Throwable $exception) {
            $rawMinOrderQty = (int) ceil((float) ($product->min_order_qty ?? 1));
            $rawPackMultiple = (int) ceil((float) ($product->pzconf_mg68 ?? 0));

            $minOrderQty = max(1, $rawMinOrderQty);
            $packMultiple = max(1, $rawPackMultiple);
            $quantityMin = max($minOrderQty, $packMultiple);

            if ($packMultiple > 1 && $quantityMin % $packMultiple !== 0) {
                $quantityMin = (int) (ceil($quantityMin / $packMultiple) * $packMultiple);
            }

            $quantityStep = $packMultiple > 1
                ? $packMultiple
                : $quantityMin;

            return [
                'min_order_qty' => $minOrderQty,
                'pack_multiple' => $packMultiple,
                'quantity_min' => $quantityMin,
                'quantity_step' => max(1, $quantityStep),
                'show_pack_multiple' => $packMultiple > 1,
            ];
        }
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginatorContract
    {
        return new LengthAwarePaginator(
            items: collect(),
            total: 0,
            perPage: $perPage,
            currentPage: 1,
            options: [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    private function baseProductWithRelations(): array
    {
        return [
            'translations',
            'mediaAssets',
            'comparisons',
            'productAttributeValues.attribute.translations',
            'productAttributeValues.value.translations',
            'productAttributeValues.value.mediaAssets',
        ];
    }

    private function visibleGroupCodes(Store $store): Collection
    {
        $cacheKey = implode('|', [
            (int) $store->ditta_cg18,
            (int) $store->erp_site_code,
        ]);

        if (array_key_exists($cacheKey, $this->visibleGroupCodesCache)) {
            return $this->visibleGroupCodesCache[$cacheKey];
        }

        return $this->visibleGroupCodesCache[$cacheKey] = StoreVisibleGroup::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
            ->pluck('codice_xx32')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values();
    }

    private function categoryScopedCacheKey(
        Store $store,
        ?string $locale = null,
        ?string $fam = null,
        ?string $sfam = null,
        ?string $gruppo = null,
        ?string $sgruppo = null,
        ?int $tipocf = null,
        ?int $clifor = null
    ): string {
        [$tipocf, $clifor] = $this->resolveStorefrontCustomerContext($store, $tipocf, $clifor);
        return implode('|', [
            (int) $store->ditta_cg18,
            (int) $store->erp_site_code,
            (int) $store->is_b2b,
            $locale ?? '',
            Product::normalizeErpCodeValue($fam) ?? '',
            Product::normalizeErpCodeValue($sfam) ?? '',
            Product::normalizeErpCodeValue($gruppo) ?? '',
            Product::normalizeErpCodeValue($sgruppo) ?? '',
            $tipocf !== null ? (int) $tipocf : '',
            $clifor !== null ? (int) $clifor : '',
        ]);
    }

    private function pluckNormalizedDistinct(Builder $query, string $column): Collection
    {
        $cloned = clone $query;

        return $cloned
            ->select([$column])
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn ($code) => Product::normalizeErpCodeValue($code))
            ->filter()
            ->unique()
            ->values();
    }

    private function applyListingSort(Builder $query, string $sort): void
    {
        match ($sort) {
            'sku_asc' => $query
                ->orderByRaw("CASE WHEN type = 'configurable' THEN 0 ELSE 1 END")
                ->orderBy('sku'),

            'sku_desc' => $query
                ->orderByRaw("CASE WHEN type = 'configurable' THEN 0 ELSE 1 END")
                ->orderByDesc('sku'),

            'name_asc' => $query
                ->leftJoin('product_translations as sort_translations', function ($join) {
                    $join->on('sort_translations.product_id', '=', 'products.id')
                        ->where('sort_translations.locale', '=', app()->getLocale());
                })
                ->select('products.*')
                ->orderByRaw("CASE WHEN products.type = 'configurable' THEN 0 ELSE 1 END")
                ->orderByRaw('COALESCE(sort_translations.name, products.sku) asc')
                ->orderBy('products.sku'),

            'name_desc' => $query
                ->leftJoin('product_translations as sort_translations', function ($join) {
                    $join->on('sort_translations.product_id', '=', 'products.id')
                        ->where('sort_translations.locale', '=', app()->getLocale());
                })
                ->select('products.*')
                ->orderByRaw("CASE WHEN products.type = 'configurable' THEN 0 ELSE 1 END")
                ->orderByRaw('COALESCE(sort_translations.name, products.sku) desc')
                ->orderBy('products.sku'),

            'price_asc' => $query
                ->orderByRaw('COALESCE(public_price, 999999999) asc')
                ->orderBy('sku'),

            'price_desc' => $query
                ->orderByRaw('COALESCE(public_price, 0) desc')
                ->orderBy('sku'),

            'newest' => $query
                ->orderByDesc('flgnovita_webt01')
                ->orderByDesc('erp_lastchange')
                ->orderBy('sku'),

            default => $query
                ->orderByRaw("CASE WHEN type = 'configurable' THEN 0 ELSE 1 END")
                ->orderBy('sku'),
        };
    }

    private function resolveListingTargetSkuFromVariants(Collection $variantOptions, array $activeFilters, string $fallbackSku): string
    {
        if ($variantOptions->isEmpty()) {
            return $fallbackSku;
        }

        $normalizedFilters = collect($activeFilters)
            ->map(fn ($values) => collect(is_array($values) ? $values : [$values])
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values()
                ->all()
            )
            ->filter(fn ($values) => !empty($values));

        if ($normalizedFilters->isNotEmpty()) {
            $matchedVariant = $variantOptions->first(function (array $variant) use ($normalizedFilters) {
                $valueKeys = collect($variant['value_keys'] ?? []);

                foreach ($normalizedFilters as $attributeCode => $acceptedValues) {
                    $variantValue = (string) $valueKeys->get((string) $attributeCode, '');

                    if ($variantValue === '' || !in_array($variantValue, $acceptedValues, true)) {
                        return false;
                    }
                }

                return true;
            });

            if (!empty($matchedVariant['sku'])) {
                return (string) $matchedVariant['sku'];
            }
        }

        $stockedImageVariant = $variantOptions->first(
            fn (array $variant) => ((float) ($variant['stock_qty'] ?? 0)) > 0
                && (bool) ($variant['has_image'] ?? false)
                && !empty($variant['sku'])
        );

        if (!empty($stockedImageVariant['sku'])) {
            return (string) $stockedImageVariant['sku'];
        }

        $imageVariant = $variantOptions->first(
            fn (array $variant) => (bool) ($variant['has_image'] ?? false) && !empty($variant['sku'])
        );

        if (!empty($imageVariant['sku'])) {
            return (string) $imageVariant['sku'];
        }

        $stockedVariant = $variantOptions->first(
            fn (array $variant) => ((float) ($variant['stock_qty'] ?? 0)) > 0 && !empty($variant['sku'])
        );

        if (!empty($stockedVariant['sku'])) {
            return (string) $stockedVariant['sku'];
        }

        return (string) ($variantOptions->first()['sku'] ?? $fallbackSku);
    }

    private function applyAttributeFilters(Builder $query, array $filters): void
    {
        $normalizedFilters = collect($filters)
            ->mapWithKeys(function ($values, string|int $code) {
                $values = collect(is_array($values) ? $values : [$values])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->unique()
                    ->values();

                return $values->isNotEmpty()
                    ? [(string) $code => $values]
                    : [];
            });

        if ($normalizedFilters->isEmpty()) {
            return;
        }

        foreach ($normalizedFilters as $attributeCode => $values) {
            $query->where(function (Builder $productQuery) use ($attributeCode, $values) {
                $productQuery->whereHas('productAttributeValues', function (Builder $attributeQuery) use ($attributeCode, $values) {
                    $attributeQuery
                        ->whereHas('attribute', fn (Builder $attribute) => $attribute->where('code', $attributeCode))
                        ->whereIn('value_key', $values->all());
                });

                $productQuery->orWhereExists(function ($subQuery) use ($attributeCode, $values) {
                    $subQuery
                        ->selectRaw('1')
                        ->from('products as child_products')
                        ->join('product_attribute_values as child_pav', 'child_pav.product_id', '=', 'child_products.id')
                        ->join('attributes as child_attributes', 'child_attributes.id', '=', 'child_pav.attribute_id')
                        ->whereColumn('child_products.parent_code', 'products.sku')
                        ->whereColumn('child_products.ditta_cg18', 'products.ditta_cg18')
                        ->whereColumn('child_products.site_type', 'products.site_type')
                        ->where('child_products.type', 'simple')
                        ->where('child_products.is_active', 1)
                        ->where('child_attributes.code', $attributeCode)
                        ->whereIn('child_pav.value_key', $values->all());
                });
            });
        }
    }

    private function applySearchConstraint(Builder $query, string $term, string $locale): void
    {
        $term = trim($term);
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';

        Log::debug('Storefront search constraint applied', [
            'term' => $term,
            'like' => $like,
            'locale' => $locale,
        ]);

        $query->where(function (Builder $search) use ($like, $locale) {
            $search
                ->where('products.sku', 'like', $like)
                ->orWhere('products.parent_code', 'like', $like)
                ->orWhere('products.barcode', 'like', $like)
                ->orWhere('products.marca_mg64', 'like', $like)
                ->orWhere('products.codlinea_w55', 'like', $like)
                ->orWhere('products.codedizione_w56', 'like', $like)
                ->orWhere('products.codcollezione_w57', 'like', $like)
                ->orWhere('products.codbrand_w58', 'like', $like)
                ->orWhere('products.codfantasie_w59', 'like', $like)
                ->orWhere('products.raggrupcat1_w51', 'like', $like)
                ->orWhere('products.raggrupcat2_w52', 'like', $like)
                ->orWhere('products.raggrupcat3_w53', 'like', $like)
                ->orWhere('products.raggrupcat4_w54', 'like', $like)
                ->orWhere('products.fam_99', 'like', $like)
                ->orWhere('products.sfam_99', 'like', $like)
                ->orWhere('products.gruppo_99', 'like', $like)
                ->orWhere('products.sgruppo_99', 'like', $like)
                ->orWhereExists(function ($subQuery) use ($like) {
                    $subQuery
                        ->selectRaw('1')
                        ->from('product_comparisons as product_search_comparisons')
                        ->whereColumn('product_search_comparisons.ditta_cg18', 'products.ditta_cg18')
                        ->whereColumn('product_search_comparisons.site_type', 'products.site_type')
                        ->whereColumn('product_search_comparisons.sku', 'products.sku')
                        ->where('product_search_comparisons.comparison_sku', 'like', $like);
                })
                ->orWhereExists(function ($subQuery) use ($like, $locale) {
                    $subQuery
                        ->selectRaw('1')
                        ->from('group_descriptions as gd')
                        ->whereColumn('gd.ditta_cg18', 'products.ditta_cg18')
                        ->whereColumn('gd.site_type', 'products.site_type')
                        ->where('gd.locale', $locale)
                        ->where('gd.is_active', 1)
                        ->where('gd.description', 'like', $like)
                        ->where(function ($group) {
                            $group
                                ->where(function ($family) {
                                    $family
                                        ->whereColumn('gd.fam_code', 'products.fam_99')
                                        ->whereNull('gd.sfam_code')
                                        ->whereNull('gd.gruppo_code');
                                })
                                ->orWhere(function ($subFamily) {
                                    $subFamily
                                        ->whereColumn('gd.fam_code', 'products.fam_99')
                                        ->whereColumn('gd.sfam_code', 'products.sfam_99')
                                        ->whereNull('gd.gruppo_code');
                                })
                                ->orWhere(function ($groupLevel) {
                                    $groupLevel
                                        ->whereColumn('gd.fam_code', 'products.fam_99')
                                        ->whereColumn('gd.sfam_code', 'products.sfam_99')
                                        ->whereColumn('gd.gruppo_code', 'products.gruppo_99');
                                });
                        });
                })
                ->orWhereHas('translations', function (Builder $translation) use ($like, $locale) {
                    $translation
                        ->where('locale', $locale)
                        ->where(function (Builder $text) use ($like) {
                            $text
                                ->where('name', 'like', $like)
                                ->orWhere('description', 'like', $like)
                                ->orWhere('short_description', 'like', $like);
                        });
                })
                ->orWhereExists(function ($subQuery) use ($like, $locale) {
                    $subQuery
                        ->selectRaw('1')
                        ->from('products as child_search_products')
                        ->leftJoin('product_translations as child_search_translations', function ($join) use ($locale) {
                            $join
                                ->on('child_search_translations.product_id', '=', 'child_search_products.id')
                                ->where('child_search_translations.locale', '=', $locale);
                        })
                        ->whereColumn('child_search_products.parent_code', 'products.sku')
                        ->whereColumn('child_search_products.ditta_cg18', 'products.ditta_cg18')
                        ->whereColumn('child_search_products.site_type', 'products.site_type')
                        ->where('child_search_products.type', 'simple')
                        ->where('child_search_products.is_active', 1)
                        ->where(function ($child) use ($like, $locale) {
                            $child
                                ->where('child_search_products.sku', 'like', $like)
                                ->orWhere('child_search_products.barcode', 'like', $like)
                                ->orWhere('child_search_products.marca_mg64', 'like', $like)
                                ->orWhere('child_search_products.codlinea_w55', 'like', $like)
                                ->orWhere('child_search_products.codedizione_w56', 'like', $like)
                                ->orWhere('child_search_products.codcollezione_w57', 'like', $like)
                                ->orWhere('child_search_products.codbrand_w58', 'like', $like)
                                ->orWhere('child_search_products.codfantasie_w59', 'like', $like)
                                ->orWhere('child_search_products.raggrupcat1_w51', 'like', $like)
                                ->orWhere('child_search_products.raggrupcat2_w52', 'like', $like)
                                ->orWhere('child_search_products.raggrupcat3_w53', 'like', $like)
                                ->orWhere('child_search_products.raggrupcat4_w54', 'like', $like)
                                ->orWhereExists(function ($comparisonQuery) use ($like) {
                                    $comparisonQuery
                                        ->selectRaw('1')
                                        ->from('product_comparisons as child_search_comparisons')
                                        ->whereColumn('child_search_comparisons.ditta_cg18', 'child_search_products.ditta_cg18')
                                        ->whereColumn('child_search_comparisons.site_type', 'child_search_products.site_type')
                                        ->whereColumn('child_search_comparisons.sku', 'child_search_products.sku')
                                        ->where('child_search_comparisons.comparison_sku', 'like', $like);
                                })
                                ->orWhere('child_search_translations.name', 'like', $like)
                                ->orWhere('child_search_translations.description', 'like', $like)
                                ->orWhere('child_search_translations.short_description', 'like', $like)
                                ->orWhereExists(function ($groupQuery) use ($like, $locale) {
                                    $groupQuery
                                        ->selectRaw('1')
                                        ->from('group_descriptions as child_gd')
                                        ->whereColumn('child_gd.ditta_cg18', 'child_search_products.ditta_cg18')
                                        ->whereColumn('child_gd.site_type', 'child_search_products.site_type')
                                        ->where('child_gd.locale', $locale)
                                        ->where('child_gd.is_active', 1)
                                        ->where('child_gd.description', 'like', $like)
                                        ->where(function ($group) {
                                            $group
                                                ->where(function ($family) {
                                                    $family
                                                        ->whereColumn('child_gd.fam_code', 'child_search_products.fam_99')
                                                        ->whereNull('child_gd.sfam_code')
                                                        ->whereNull('child_gd.gruppo_code');
                                                })
                                                ->orWhere(function ($subFamily) {
                                                    $subFamily
                                                        ->whereColumn('child_gd.fam_code', 'child_search_products.fam_99')
                                                        ->whereColumn('child_gd.sfam_code', 'child_search_products.sfam_99')
                                                        ->whereNull('child_gd.gruppo_code');
                                                })
                                                ->orWhere(function ($groupLevel) {
                                                    $groupLevel
                                                        ->whereColumn('child_gd.fam_code', 'child_search_products.fam_99')
                                                        ->whereColumn('child_gd.sfam_code', 'child_search_products.sfam_99')
                                                        ->whereColumn('child_gd.gruppo_code', 'child_search_products.gruppo_99');
                                                });
                                        });
                                });
                        });
                });
        });
    }
}