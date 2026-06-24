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
use App\Services\Storefront\Pricing\ProductPriceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
        $famCodes = $this->pluckNormalizedDistinct($this->baseVisibleProductsQuery($store), 'fam_99');

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
                'path' => ['fam' => $famCode, 'sfam' => null, 'gruppo' => null, 'sgruppo' => null],
            ];
        })->values();
    }

    public function getChildrenCategories(Store $store, string $locale, ?string $fam = null, ?string $sfam = null, ?string $gruppo = null): Collection
    {
        $fam = Product::normalizeErpCodeValue($fam);
        $sfam = Product::normalizeErpCodeValue($sfam);
        $gruppo = Product::normalizeErpCodeValue($gruppo);

        if ($fam === null) {
            return $this->getRootCategories($store, $locale);
        }

        $query = $this->baseVisibleProductsQuery($store)->forCategoryTree($fam, $sfam, $gruppo, null);

        if ($sfam === null) {
            return $this->pluckNormalizedDistinct($query, 'sfam_99')->map(function (string $sfamCode) use ($store, $locale, $fam) {
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
                    'path' => ['fam' => $fam, 'sfam' => $sfamCode, 'gruppo' => null, 'sgruppo' => null],
                ];
            })->values();
        }

        if ($gruppo === null) {
            return $this->pluckNormalizedDistinct($query, 'gruppo_99')->map(function (string $gruppoCode) use ($store, $locale, $fam, $sfam) {
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
                    'path' => ['fam' => $fam, 'sfam' => $sfam, 'gruppo' => $gruppoCode, 'sgruppo' => null],
                ];
            })->values();
        }

        return collect();
    }

    public function getCategoryMeta(Store $store, string $locale, ?string $fam = null, ?string $sfam = null, ?string $gruppo = null, ?string $sgruppo = null): array
    {
        $fam = Product::normalizeErpCodeValue($fam);
        $sfam = Product::normalizeErpCodeValue($sfam);
        $gruppo = Product::normalizeErpCodeValue($gruppo);
        $sgruppo = Product::normalizeErpCodeValue($sgruppo);

        $code = $sgruppo ?? $gruppo ?? $sfam ?? $fam;
        $label = $this->categoryLabel($store, $locale, $fam, $sfam, $gruppo, $sgruppo);

        return [
            'level' => match (true) {
                $fam !== null && $sfam === null => 'famiglia',
                $fam !== null && $sfam !== null && $gruppo === null => 'sottofamiglia',
                $fam !== null && $sfam !== null && $gruppo !== null => 'gruppo',
                default => 'categoria',
            },
            'code' => $code,
            'label' => $label ?: 'Categoria',
            'description' => $label,
            'slug' => $this->buildCategorySlug($store, $locale, $fam, $sfam, $gruppo, $sgruppo),
            'path' => ['fam' => $fam, 'sfam' => $sfam, 'gruppo' => $gruppo, 'sgruppo' => $sgruppo],
        ];
    }

    public function parseLegacyCategorySlug(string $slug): array
    {
        $parts = collect(explode('/', trim($slug, '/')))->map(fn ($part) => trim((string) $part))->filter()->values();

        return [
            'fam' => Product::normalizeErpCodeValue($parts->get(0)),
            'sfam' => Product::normalizeErpCodeValue($parts->get(1)),
            'gruppo' => Product::normalizeErpCodeValue($parts->get(2)),
            'sgruppo' => Product::normalizeErpCodeValue($parts->get(3)),
        ];
    }

    public function parseCategorySlug(Store $store, string $locale, string $slug): array
    {
        $parts = collect(explode('/', trim($slug, '/')))->map(fn ($part) => Str::slug((string) $part))->filter()->values();

        if ($parts->isEmpty()) {
            return ['fam' => null, 'sfam' => null, 'gruppo' => null, 'sgruppo' => null];
        }

        $root = $this->getRootCategories($store, $locale)->first(fn (array $item) => basename((string) $item['slug']) === $parts->get(0));

        if (!$root) {
            return $this->parseLegacyCategorySlug($slug);
        }

        $fam = $root['fam_code'] ?? null;
        $sfam = null;
        $gruppo = null;

        if ($parts->count() >= 2) {
            $sfamRow = $this->getChildrenCategories($store, $locale, $fam)->first(fn (array $item) => basename((string) $item['slug']) === $parts->get(1));
            $sfam = $sfamRow['sfam_code'] ?? null;
        }

        if ($parts->count() >= 3 && $sfam !== null) {
            $gruppoRow = $this->getChildrenCategories($store, $locale, $fam, $sfam)->first(fn (array $item) => basename((string) $item['slug']) === $parts->get(2));
            $gruppo = $gruppoRow['gruppo_code'] ?? null;
        }

        return ['fam' => $fam, 'sfam' => $sfam, 'gruppo' => $gruppo, 'sgruppo' => null];
    }

    public function resolveSeoFiltersToInternal(Store $store, string $locale, ?string $fam = null, ?string $sfam = null, ?string $gruppo = null, ?string $sgruppo = null, array $seoFilters = []): array
    {
        if (empty($seoFilters)) {
            return [];
        }

        $facets = $this->getCategoryFilterFacets($store, $locale, $fam, $sfam, $gruppo, $sgruppo);
        $facetsBySlug = $facets->keyBy(fn (array $facet) => (string) ($facet['slug'] ?? ''));
        $resolved = [];

        foreach ($seoFilters as $attributeSlug => $values) {
            $facet = $facetsBySlug->get(Str::slug((string) $attributeSlug));

            if (!$facet || empty($facet['code'])) {
                continue;
            }

            $valuesBySlug = collect($facet['values'] ?? [])->keyBy(fn (array $value) => (string) ($value['slug'] ?? ''));

            foreach (collect(is_array($values) ? $values : [$values]) as $valueSlug) {
                $value = $valuesBySlug->get(Str::slug((string) $valueSlug));

                if ($value && !empty($value['key'])) {
                    $resolved[(string) $facet['code']][] = (string) $value['key'];
                }
            }
        }

        return collect($resolved)
            ->map(fn (array $values) => collect($values)->filter()->unique()->values()->all())
            ->filter(fn (array $values) => !empty($values))
            ->all();
    }

    public function buildCategorySlug(Store $store, string $locale, ?string $fam = null, ?string $sfam = null, ?string $gruppo = null, ?string $sgruppo = null): string
    {
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

    public function getCategoryProducts(Store $store, string $locale, ?string $fam = null, ?string $sfam = null, ?string $gruppo = null, ?string $sgruppo = null, ?int $tipocf = null, ?int $clifor = null, int $perPage = 24, array $filters = [], string $sort = 'default'): LengthAwarePaginatorContract
    {
        $listingSkus = $this->resolveCategoryListingSkus($store, $fam, $sfam, $gruppo, $sgruppo, $tipocf, $clifor);

        if ($listingSkus->isEmpty()) {
            return $this->emptyPaginator($perPage);
        }

        $query = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->whereIn('sku', $listingSkus->all())
            ->with($this->listingProductWithRelations($locale));

        $this->applyAttributeFilters($query, $filters);
        $this->applyListingSort($query, $sort);

        $paginator = $query->paginate($perPage)->withQueryString();
        $items = collect($paginator->items());

        $this->attachVisibleChildrenToProducts($store, $items, $tipocf, $clifor, $locale, false);
        $this->enrichCategoryDescriptions($store, $items, $locale);
        $this->enrichProductPresentation($store, $items, $locale, $filters, true);

        return $paginator;
    }

    public function getCategoryFilterFacets(Store $store, string $locale, ?string $fam = null, ?string $sfam = null, ?string $gruppo = null, ?string $sgruppo = null, ?int $tipocf = null, ?int $clifor = null, array $activeFilters = []): Collection
    {
        $cacheKey = $this->categoryScopedCacheKey($store, $locale, $fam, $sfam, $gruppo, $sgruppo, $tipocf, $clifor);
        $cacheKey .= '|facet-sort:'.$this->filterFacetSortSignature();

        if (!array_key_exists($cacheKey, $this->categoryFacetCache)) {
            $this->categoryFacetCache[$cacheKey] = Cache::remember(
                'storefront:category_facets:' . sha1($cacheKey),
                now()->addHours(6),
                fn () => $this->buildCategoryFilterFacets($store, $locale, $fam, $sfam, $gruppo, $sgruppo, $tipocf, $clifor)
            );
        }

        return $this->categoryFacetCache[$cacheKey]
            ->map(function (array $facet) use ($activeFilters) {
                $attributeCode = (string) ($facet['code'] ?? '');
                $facet['active_values'] = collect($activeFilters[$attributeCode] ?? [])->map(fn ($value) => trim((string) $value))->filter()->values();
                return $facet;
            })
            ->values();
    }

    private function buildCategoryFilterFacets(Store $store, string $locale, ?string $fam = null, ?string $sfam = null, ?string $gruppo = null, ?string $sgruppo = null, ?int $tipocf = null, ?int $clifor = null): Collection
    {
        $listingSkus = $this->resolveCategoryListingSkus($store, $fam, $sfam, $gruppo, $sgruppo, $tipocf, $clifor);

        if ($listingSkus->isEmpty()) {
            return collect();
        }

        $facetProducts = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->where(function (Builder $query) use ($listingSkus) {
                $query->whereIn('sku', $listingSkus->all())
                    ->orWhere(fn (Builder $children) => $children->where('type', 'simple')->whereIn('parent_code', $listingSkus->all()));
            })
            ->whereHas('productAttributeValues.attribute', fn (Builder $attribute) => $attribute->where('is_filterable', true))
            ->with([
                'productAttributeValues' => fn ($query) => $query->whereHas('attribute', fn (Builder $attribute) => $attribute->where('is_filterable', true)),
                'productAttributeValues.attribute.translations' => fn ($query) => $query->whereIn('locale', $this->localesForLoading($locale)),
                'productAttributeValues.value.translations' => fn ($query) => $query->whereIn('locale', $this->localesForLoading($locale)),
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
                $attributeLabel = $this->loadedTranslation($attribute, $locale)?->label ?? $attributeCode;

                $values = $rows
                    ->map(function ($row) use ($locale) {
                        $valueModel = $row->value;
                        $rawValue = trim((string) ($row->raw_value ?? ''));
                        $valueKey = (string) ($row->value_key ?: ProductAttributeValue::makeValueKey(
                            $row->attribute_value_id ? (int) $row->attribute_value_id : null,
                            $rawValue !== '' ? $rawValue : null
                        ));
                        $valueLabel = ($valueModel instanceof AttributeValue ? $this->loadedTranslation($valueModel, $locale)?->label : null)
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
                    'slug' => Str::slug($attributeLabel),
                    'type' => $attribute->type,
                    'sort_order' => (int) ($attribute->sort_order ?? 0),
                    'is_variant' => (bool) ($attribute->is_variant ?? false),
                    'active_values' => collect(),
                    'values' => $values,
                ];
            })
            ->filter()
            ->sortBy([
                fn (array $facet) => (int) ($facet['sort_order'] ?? 999),
                fn (array $facet) => (string) ($facet['label'] ?? ''),
            ])
            ->values();
    }

    public function getNavigationTree(Store $store, string $locale): Collection
    {
        [$tipocf, $clifor] = $this->resolveStorefrontCustomerContext($store, null, null);
        $cacheKey = $this->categoryScopedCacheKey($store, $locale, null, null, null, null, $tipocf, $clifor);

        if (array_key_exists($cacheKey, $this->navigationTreeCache)) {
            return $this->navigationTreeCache[$cacheKey];
        }

        return $this->navigationTreeCache[$cacheKey] = $this->getRootCategories($store, $locale)
            ->map(function (array $famiglia) use ($store, $locale) {
                $famiglia['children'] = $this->getChildrenCategories($store, $locale, $famiglia['fam_code'] ?? null)
                    ->map(function (array $sottofamiglia) use ($store, $locale) {
                        $sottofamiglia['children'] = $this->getChildrenCategories($store, $locale, $sottofamiglia['fam_code'] ?? null, $sottofamiglia['sfam_code'] ?? null)->values();
                        return $sottofamiglia;
                    })
                    ->values();

                return $famiglia;
            })
            ->values();
    }

    public function getProductBySku(Store $store, string $locale, string $sku, ?int $tipocf = null, ?int $clifor = null): ?Product
    {
        $product = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->with($this->detailProductWithRelations($locale))
            ->where('sku', trim($sku))
            ->first();

        if (!$product) {
            return null;
        }

        $this->attachResolvedConfigurableContext($store, $product, $locale, $tipocf, $clifor);

        $productsToEnrich = collect([$product]);
        $baseProduct = $product->getAttribute('resolved_base_product');

        if ($baseProduct instanceof Product && $baseProduct->getKey() !== $product->getKey()) {
            $productsToEnrich->push($baseProduct);
        }

        $this->enrichCategoryDescriptions($store, $productsToEnrich, $locale);
        $this->enrichProductPresentation($store, $productsToEnrich, $locale, [], true);

        $variantProducts = $product->getAttribute('resolved_variant_products') ?? new EloquentCollection();

        if ($variantProducts instanceof EloquentCollection && $variantProducts->isNotEmpty()) {
            $this->enrichCategoryDescriptions($store, $variantProducts, $locale);
            $this->enrichProductPresentation($store, $variantProducts, $locale, [], true);
        }

        return $product;
    }

    public function searchProducts(Store $store, string $locale, string $query, ?int $tipocf = null, ?int $clifor = null, int $perPage = 24, string $sort = 'default', array $filters = []): LengthAwarePaginatorContract
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return $this->emptyPaginator($perPage);
        }

        $matchedProductsQuery = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->where(fn (Builder $builder) => $this->applySearchConstraint($builder, $query, $locale));

        $this->applyAttributeFilters($matchedProductsQuery, $filters);

        $matchedProducts = $matchedProductsQuery->get(['sku', 'parent_code', 'type']);

        if ($matchedProducts->isEmpty()) {
            return $this->emptyPaginator($perPage);
        }

        $parentCodes = $matchedProducts->pluck('parent_code')->map(fn ($code) => Product::normalizeErpCodeValue($code))->filter()->unique()->values();
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

        $normalizedSearchTerm = mb_strtolower(trim($query));

        $listingSkus = $matchedProducts
            ->map(function ($product) use ($activeParentSkuSet, $normalizedSearchTerm) {
                $sku = trim((string) $product->sku);
                $parentCode = Product::normalizeErpCodeValue($product->parent_code);

                if ($product->type === 'configurable') {
                    return mb_stripos($sku, $normalizedSearchTerm) !== false
                        ? $sku
                        : null;
                }

                if ($normalizedSearchTerm !== '' && mb_stripos($sku, $normalizedSearchTerm) !== false) {
                    return $sku;
                }

                return $parentCode !== null && isset($activeParentSkuSet[$parentCode])
                    ? $parentCode
                    : $sku;
            })
            ->filter()
            ->unique()
            ->values();

        if ($listingSkus->isEmpty()) {
            return $this->emptyPaginator($perPage);
        }

        $productsQuery = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->whereIn('sku', $listingSkus->all())
            ->with($this->listingProductWithRelations($locale));

        $this->applyAttributeFilters($productsQuery, $filters);
        $this->applyListingSort($productsQuery, $sort);

        $paginator = $productsQuery->paginate($perPage)->withQueryString();
        $items = collect($paginator->items());

        $this->attachVisibleChildrenToProducts($store, $items, $tipocf, $clifor, $locale, false);
        $this->enrichCategoryDescriptions($store, $items, $locale);
        $this->enrichProductPresentation($store, $items, $locale, $filters, true);

        return $paginator;
    }

    public function getSearchFilterFacets(Store $store, string $locale, string $query, ?int $tipocf = null, ?int $clifor = null, array $activeFilters = []): Collection
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return collect();
        }

        return $this->buildSearchFilterFacets($store, $locale, $query, $tipocf, $clifor)
            ->map(function (array $facet) use ($activeFilters) {
                $attributeCode = (string) ($facet['code'] ?? '');
                $facet['active_values'] = collect($activeFilters[$attributeCode] ?? [])->map(fn ($value) => trim((string) $value))->filter()->values();
                return $facet;
            })
            ->values();
    }

    private function buildSearchFilterFacets(Store $store, string $locale, string $query, ?int $tipocf = null, ?int $clifor = null): Collection
    {
        $matchedProducts = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->where(fn (Builder $builder) => $this->applySearchConstraint($builder, $query, $locale))
            ->get(['sku', 'parent_code', 'type']);

        if ($matchedProducts->isEmpty()) {
            return collect();
        }

        $parentCodes = $matchedProducts
            ->pluck('parent_code')
            ->map(fn ($code) => Product::normalizeErpCodeValue($code))
            ->filter()
            ->unique()
            ->values();

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

        $normalizedSearchTerm = mb_strtolower(trim($query));

        $listingSkus = $matchedProducts
            ->map(function ($product) use ($activeParentSkuSet, $normalizedSearchTerm) {
                $sku = trim((string) $product->sku);
                $parentCode = Product::normalizeErpCodeValue($product->parent_code);

                if ($product->type === 'configurable') {
                    return mb_stripos($sku, $normalizedSearchTerm) !== false
                        ? $sku
                        : null;
                }

                if ($normalizedSearchTerm !== '' && mb_stripos($sku, $normalizedSearchTerm) !== false) {
                    return $sku;
                }

                return $parentCode !== null && isset($activeParentSkuSet[$parentCode])
                    ? $parentCode
                    : $sku;
            })
            ->filter()
            ->unique()
            ->values();

        if ($listingSkus->isEmpty()) {
            return collect();
        }

        $facetProducts = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->where(function (Builder $query) use ($listingSkus) {
                $query->whereIn('sku', $listingSkus->all())
                    ->orWhere(fn (Builder $children) => $children->where('type', 'simple')->whereIn('parent_code', $listingSkus->all()));
            })
            ->whereHas('productAttributeValues.attribute', fn (Builder $attribute) => $attribute->where('is_filterable', true))
            ->with([
                'productAttributeValues' => fn ($query) => $query->whereHas('attribute', fn (Builder $attribute) => $attribute->where('is_filterable', true)),
                'productAttributeValues.attribute.translations' => fn ($query) => $query->whereIn('locale', $this->localesForLoading($locale)),
                'productAttributeValues.value.translations' => fn ($query) => $query->whereIn('locale', $this->localesForLoading($locale)),
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
                $attributeLabel = $this->loadedTranslation($attribute, $locale)?->label ?? $attributeCode;

                $values = $rows
                    ->map(function ($row) use ($locale) {
                        $valueModel = $row->value;
                        $rawValue = trim((string) ($row->raw_value ?? ''));
                        $valueKey = (string) ($row->value_key ?: ProductAttributeValue::makeValueKey(
                            $row->attribute_value_id ? (int) $row->attribute_value_id : null,
                            $rawValue !== '' ? $rawValue : null
                        ));
                        $valueLabel = ($valueModel instanceof AttributeValue ? $this->loadedTranslation($valueModel, $locale)?->label : null)
                            ?? $valueModel?->value_code
                            ?? ($rawValue !== '' ? $rawValue : '-');

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
                    'slug' => Str::slug($attributeLabel),
                    'type' => $attribute->type,
                    'sort_order' => (int) ($attribute->sort_order ?? 0),
                    'is_variant' => (bool) ($attribute->is_variant ?? false),
                    'active_values' => collect(),
                    'values' => $values,
                ];
            })
            ->filter()
            ->sortBy([
                fn (array $facet) => (int) ($facet['sort_order'] ?? 999),
                fn (array $facet) => (string) ($facet['label'] ?? ''),
            ])
            ->values();
    }

    public function suggestProducts(Store $store, string $locale, string $query, ?int $tipocf = null, ?int $clifor = null, int $limit = 6): Collection
    {
        $products = $this->searchProducts($store, $locale, $query, $tipocf, $clifor, $limit, 'default');

        return collect($products->items())
        ->map(function (Product $product) use ($store, $locale) {
            $variantOptions = collect($product->listing_variant_options ?? []);
            $selectedSku = (string) ($product->listing_target_sku ?? $product->sku);

            $selectedVariant = $variantOptions->first(
                fn (array $variant) => (string) ($variant['sku'] ?? '') === $selectedSku
            ) ?? $variantOptions->first();

            $selectedProduct = $this->resolveSuggestionSelectedProduct($product, $selectedSku);
            $selectedVariant = is_array($selectedVariant) ? $selectedVariant : [];

            $image = $selectedVariant['image']
                ?? $selectedProduct->main_image_url
                ?? $product->main_image_url
                ?? null;

            $description = $this->resolveSuggestionDescription($product, $selectedProduct, $locale);
            $category = $this->resolveSuggestionCategoryLabel($store, $locale, $product, $selectedProduct);

            return [
                'sku' => $selectedSku,
                'product_sku' => $selectedSku,
                'name' => (string) ($selectedProduct->display_name ?? $product->display_name ?? $selectedProduct->sku ?? $product->sku),
                'description' => $description,
                'short_description' => $description,
                'url' => route('storefront.product.show', $selectedSku),
                'image' => $image,
                'thumbnail' => $image,
                'price' => ($selectedVariant['price'] ?? $selectedVariant['effective_price'] ?? $product->effective_price) !== null
                    ? '€ ' . number_format(
                        (float) ($selectedVariant['price'] ?? $selectedVariant['effective_price'] ?? $product->effective_price),
                        $store->is_b2b ? 3 : 2,
                        ',',
                        '.'
                    )
                    : null,
                'category' => $category,
                'category_path' => $category,
                'collection' => null,
                'collection_label' => null,
                'color' => $this->resolveSuggestionVariantAttributeLabel($selectedVariant, 'color'),
                'color_label' => $this->resolveSuggestionVariantAttributeLabel($selectedVariant, 'color'),
                'format' => $this->resolveSuggestionVariantAttributeLabel($selectedVariant, 'format'),
                'format_label' => $this->resolveSuggestionVariantAttributeLabel($selectedVariant, 'format'),
                'quantity_min' => (int) ($selectedVariant['quantity_min'] ?? 1),
                'quantity_step' => (int) ($selectedVariant['quantity_step'] ?? 1),
                'pack_multiple' => (int) ($selectedVariant['pack_multiple'] ?? 1),
                'min_order_qty' => (int) ($selectedVariant['min_order_qty'] ?? 1),
            ];
        })
        ->unique(fn (array $item) => (string) ($item['product_sku'] ?? $item['sku'] ?? ''))
        ->values();
    }

    private function resolveSuggestionSelectedProduct(Product $product, string $selectedSku): Product
    {
        if (trim((string) $product->sku) === trim($selectedSku)) {
            return $product;
        }

        $children = $product->relationLoaded('children') ? collect($product->getRelation('children')) : collect();
        $selectedChild = $children->first(fn ($child) => $child instanceof Product && trim((string) $child->sku) === trim($selectedSku));

        return $selectedChild instanceof Product ? $selectedChild : $product;
    }

    private function resolveSuggestionCategoryLabel(Store $store, string $locale, Product $product, ?Product $selectedProduct = null): ?string
    {
        $source = $selectedProduct instanceof Product ? $selectedProduct : $product;
        $existingCategory = trim((string) ($source->category_path_description ?? ''));

        if ($existingCategory !== '') {
            return $existingCategory;
        }

        $fam = Product::normalizeErpCodeValue($source->fam_99) ?: Product::normalizeErpCodeValue($product->fam_99);
        $sfam = Product::normalizeErpCodeValue($source->sfam_99) ?: Product::normalizeErpCodeValue($product->sfam_99);
        $gruppo = Product::normalizeErpCodeValue($source->gruppo_99) ?: Product::normalizeErpCodeValue($product->gruppo_99);
        $sgruppo = Product::normalizeErpCodeValue($source->sgruppo_99) ?: Product::normalizeErpCodeValue($product->sgruppo_99);

        $category = collect([
            $fam ? $this->categoryLabel($store, $locale, $fam) : null,
            ($fam && $sfam) ? $this->categoryLabel($store, $locale, $fam, $sfam) : null,
            ($fam && $sfam && $gruppo) ? $this->categoryLabel($store, $locale, $fam, $sfam, $gruppo) : null,
            $sgruppo,
        ])->map(fn ($value) => trim((string) $value))->filter()->unique()->implode(' / ');

        return $category !== '' ? $category : null;
    }

    private function resolveSuggestionDescription(Product $product, Product $selectedProduct, string $locale): ?string
    {
        $selectedTranslation = $this->loadedTranslation($selectedProduct, $locale);
        $productTranslation = $this->loadedTranslation($product, $locale);

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

    private function categoryLabel(Store $store, string $locale, ?string $fam = null, ?string $sfam = null, ?string $gruppo = null, ?string $sgruppo = null): string
    {
        $fam = Product::normalizeErpCodeValue($fam);
        $sfam = Product::normalizeErpCodeValue($sfam);
        $gruppo = Product::normalizeErpCodeValue($gruppo);
        $sgruppo = Product::normalizeErpCodeValue($sgruppo);
        $fallback = $sgruppo ?? $gruppo ?? $sfam ?? $fam ?? 'Categoria';

        $cacheKey = implode('|', [(int) $store->ditta_cg18, (int) $store->erp_site_code, $locale, $fam, $sfam, $gruppo, $sgruppo]);

        if (array_key_exists($cacheKey, $this->categoryLabelCache)) {
            return $this->categoryLabelCache[$cacheKey];
        }

        $query = GroupDescription::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
            ->forLocale($locale);

        if ($fam !== null && $sfam === null) {
            $query->famiglie()->where('fam_code', $fam);
        } elseif ($fam !== null && $sfam !== null && $gruppo === null) {
            $query->sottofamiglie()->where('fam_code', $fam)->where('sfam_code', $sfam);
        } elseif ($fam !== null && $sfam !== null && $gruppo !== null) {
            $query->gruppi()->where('fam_code', $fam)->where('sfam_code', $sfam)->where('gruppo_code', $gruppo);
        } else {
            return $this->categoryLabelCache[$cacheKey] = $fallback;
        }

        return $this->categoryLabelCache[$cacheKey] = ($query->value('description') ?: $fallback);
    }

    private function baseVisibleProductsQuery(Store $store, ?int $tipocf = null, ?int $clifor = null): Builder
    {
        [$tipocf, $clifor] = $this->resolveStorefrontCustomerContext($store, $tipocf, $clifor);

        if ($store->is_b2b && $tipocf !== null && $tipocf > 0 && $clifor !== null && $clifor > 0) {
            return Product::query()->visibleForCustomer((int) $store->erp_site_code, $tipocf, $clifor);
        }

        $query = Product::query()->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)->active();

        if (!$store->is_b2b) {
            return $query->where(function (Builder $outer) use ($store) {
                $outer->where(function (Builder $simple) {
                    $simple->where('type', 'simple')
                        ->where('stock_qty', '>', 0);
                });

                $outer->orWhere(function (Builder $configurable) use ($store) {
                    $configurable->where('type', 'configurable')
                        ->whereExists(function ($sub) use ($store) {
                            $sub->selectRaw('1')
                                ->from('products as c')
                                ->whereColumn('c.parent_code', 'products.sku')
                                ->whereColumn('c.ditta_cg18', 'products.ditta_cg18')
                                ->whereColumn('c.site_type', 'products.site_type')
                                ->where('c.ditta_cg18', (int) $store->ditta_cg18)
                                ->where('c.site_type', (int) $store->erp_site_code)
                                ->where('c.type', 'simple')
                                ->where('c.is_active', 1)
                                ->where('c.stock_qty', '>', 0);
                        });
                });
            });
        }

        $visibleGroupCodes = $this->visibleGroupCodes($store);

        return $query->where(function (Builder $outer) use ($visibleGroupCodes) {
            $outer->where(function (Builder $simple) use ($visibleGroupCodes) {
                $simple->where('type', 'simple')
                    ->where(function (Builder $visibility) use ($visibleGroupCodes) {
                        if ($visibleGroupCodes->isNotEmpty()) {
                            $visibility->whereIn('codgrupfis_mg61', $visibleGroupCodes->all());
                        } else {
                            $visibility->whereRaw('1 = 0');
                        }

                        $visibility->orWhere(fn (Builder $q) => $q->whereNull('codgrupfis_mg61')->where('fam_99', 'H'));
                    });
            });

            $outer->orWhere(function (Builder $configurable) use ($visibleGroupCodes) {
                $configurable->where('type', 'configurable')
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

                                $visibility->orWhere(fn ($q) => $q->whereNull('c.codgrupfis_mg61')->where('c.fam_99', 'H'));
                            });
                    });
            });
        });
    }

    private function resolveCategoryListingSkus(Store $store, ?string $fam = null, ?string $sfam = null, ?string $gruppo = null, ?string $sgruppo = null, ?int $tipocf = null, ?int $clifor = null): Collection
    {
        $cacheKey = $this->categoryScopedCacheKey($store, null, $fam, $sfam, $gruppo, $sgruppo, $tipocf, $clifor);

        if (array_key_exists($cacheKey, $this->categoryListingSkusCache)) {
            return $this->categoryListingSkusCache[$cacheKey];
        }

        $categoryProducts = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->forCategoryTree($fam, $sfam, $gruppo, $sgruppo)
            ->get(['sku', 'parent_code', 'type']);

        if ($categoryProducts->isEmpty()) {
            return $this->categoryListingSkusCache[$cacheKey] = collect();
        }

        $parentCodes = $categoryProducts->pluck('parent_code')->map(fn ($code) => Product::normalizeErpCodeValue($code))->filter()->unique()->values();
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

                return $parentCode !== null && isset($activeParentSkuSet[$parentCode])
                    ? $parentCode
                    : trim((string) $product->sku);
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function attachVisibleChildrenToProducts(Store $store, Collection $products, ?int $tipocf = null, ?int $clifor = null, ?string $locale = null, bool $forDetail = false): void
    {
        $configurableProducts = $products->filter(fn ($product) => $product instanceof Product && $product->type === 'configurable')->values();

        if ($configurableProducts->isEmpty()) {
            return;
        }

        $parentSkus = $configurableProducts->map(fn (Product $product) => trim((string) $product->sku))->filter()->unique()->values();

        if ($parentSkus->isEmpty()) {
            return;
        }

        $locale ??= app()->getLocale();

        $children = $this->baseVisibleProductsQuery($store, $tipocf, $clifor)
            ->where('type', 'simple')
            ->whereIn('parent_code', $parentSkus->all())
            ->with($forDetail ? $this->detailProductWithRelations($locale) : $this->listingProductWithRelations($locale))
            ->orderBy('sku')
            ->get();

        $childrenByParent = $children->groupBy(fn (Product $product) => trim((string) $product->parent_code));

        foreach ($configurableProducts as $product) {
            $group = $childrenByParent->get(trim((string) $product->sku), collect());
            $product->setRelation('children', new EloquentCollection($group->all()));
        }
    }

    private function attachResolvedConfigurableContext(Store $store, Product $product, string $locale, ?int $tipocf = null, ?int $clifor = null): void
    {
        $product->loadMissing('comparisons');

        if ($product->type === 'configurable') {
            $this->attachVisibleChildrenToProducts($store, collect([$product]), $tipocf, $clifor, $locale, true);
            $variantProducts = $product->getRelation('children');

            if (!$variantProducts instanceof EloquentCollection || $variantProducts->isEmpty()) {
                $variantProducts = new EloquentCollection([$product]);
            }

            $variantProducts->loadMissing('comparisons');
            $selectedProduct = $variantProducts->first() instanceof Product ? $variantProducts->first() : $product;

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
                ->with($this->detailProductWithRelations($locale))
                ->where('sku', $parentCode)
                ->first();

            if ($baseProduct instanceof Product) {
                $this->attachVisibleChildrenToProducts($store, collect([$baseProduct]), $tipocf, $clifor, $locale, true);
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
            $product->setAttribute('category_path_description', collect([$famDescription, $sfamDescription, $gruppoDescription, $sgruppo])->filter()->implode(' / '));
        }
    }

    private function enrichProductPresentation(Store $store, Collection $products, string $locale, array $activeFilters = [], bool $includeVariantPrices = true): void
    {
        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $translation = $this->loadedTranslation($product, $locale);
            $productMainImageUrl = $this->mainImageUrlFromLoadedMedia($product);

            $product->setAttribute('display_name', $translation?->name ?? $product->sku);
            $product->setAttribute('display_description', $translation?->description);
            $product->setAttribute('display_short_description', $translation?->short_description);
            $product->setAttribute('display_seo_title', $translation?->seo_title ?? $translation?->name ?? $product->sku);
            $product->setAttribute('display_seo_description', $translation?->seo_description);
            $product->setAttribute('main_image_url', $productMainImageUrl);
            $product->setAttribute('effective_price', $this->resolveEffectivePrice($store, $product));
            $product->setAttribute('is_sellable', $this->isSellable($store, $product));

            $variantProducts = $product->type === 'configurable'
                ? (($product->getRelation('children') instanceof EloquentCollection) ? $product->getRelation('children')->where('is_active', true)->values() : collect())
                : collect([$product]);

            $variantOptions = $variantProducts->map(function (Product $variant) use ($store, $locale, $includeVariantPrices) {
                $attributeRows = collect($variant->productAttributeValues ?? [])
                    ->sortBy(fn ($item) => [(int) ($item->attribute->sort_order ?? 0), (string) ($item->attribute->code ?? '')])
                    ->values();

                $presentation = $attributeRows->map(function ($row) use ($locale) {
                    $attributeLabel = $this->loadedTranslation($row->attribute, $locale)?->label ?? $row->attribute?->code ?? 'Attributo';
                    $attributeValue = $this->loadedTranslation($row->value, $locale)?->label ?? $row->value?->value_code ?? $row->raw_value ?? '—';
                    $attributeCode = trim((string) ($row->attribute?->code ?? ''));

                    return [
                        'code' => $attributeCode !== '' ? $attributeCode : null,
                        'label' => $attributeLabel,
                        'value' => $attributeValue,
                        'normalized_label' => mb_strtolower(trim((string) $attributeLabel)),
                        'normalized_code' => mb_strtolower($attributeCode),
                        'swatch_url' => $row->value instanceof AttributeValue ? $this->attributeValueSwatchUrl($row->value) : null,
                    ];
                })->values();

                $mainImageUrl = $this->mainImageUrlFromLoadedMedia($variant);
                $hoverImageUrl = $this->hoverImageUrlFromLoadedMedia($variant, $mainImageUrl);
                $quantityConstraints = $this->resolveListingQuantityConstraints($variant);
                $variantEffectivePrice = $includeVariantPrices
                    ? $this->resolveEffectivePrice($store, $variant, (float) ($quantityConstraints['quantity_min'] ?? 1))
                    : ($variant->public_price !== null ? (float) $variant->public_price : null);

                return [
                    'sku' => $variant->sku,
                    'label' => $this->loadedTranslation($variant, $locale)?->name ?? $variant->sku,
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
                    'color' => $presentation->first(fn (array $row) => in_array($row['normalized_label'] ?? null, ['colore', 'color'], true) || in_array($row['normalized_code'] ?? null, ['colore', 'color'], true)),
                    'format' => $presentation->first(fn (array $row) => in_array($row['normalized_label'] ?? null, ['formato', 'format'], true) || in_array($row['normalized_code'] ?? null, ['formato', 'format'], true)),
                    'value_keys' => collect($variant->productAttributeValues ?? [])
                        ->mapWithKeys(fn ($row) => [(string) ($row->attribute?->code ?? '') => (string) $row->value_key])
                        ->filter(fn ($value, $key) => $key !== '' && $value !== '')
                        ->all(),
                    'is_active' => (bool) $variant->is_active,
                    'stock_qty' => $variant->stock_qty !== null ? (float) $variant->stock_qty : null,
                    'has_image' => $mainImageUrl !== null,
                ];
            })->values();

            $listingTargetSku = $this->resolveListingTargetSkuFromVariants($variantOptions, $activeFilters, $product->sku);
            $selectedListingOption = $variantOptions->first(fn (array $variant) => (string) ($variant['sku'] ?? '') === $listingTargetSku) ?? $variantOptions->first();

            $product->setAttribute('effective_price', $selectedListingOption['price'] ?? $selectedListingOption['effective_price'] ?? $product->getAttribute('effective_price'));
            $product->setAttribute('main_image_url', $selectedListingOption['image'] ?? $productMainImageUrl ?? $product->getAttribute('main_image_url'));
            $product->setAttribute('listing_hover_image_url', $selectedListingOption['hover_image'] ?? null);
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
        $loadedAssets = $value->relationLoaded('mediaAssets') ? collect($value->getRelation('mediaAssets')) : collect();
        $swatch = $loadedAssets->firstWhere('role', MediaAsset::ROLE_SWATCH);

        if (!$swatch instanceof MediaAsset) {
            return null;
        }

        $source = $swatch->local_path ?? $swatch->erp_full_path ?? $swatch->url ?? null;

        return media_url($source);
    }

    private function mainImageUrlFromLoadedMedia(Product $product): ?string
    {
        $mediaAssets = collect($product->mediaAssets ?? [])
            ->filter(fn ($asset) => in_array($asset->role, [MediaAsset::ROLE_MAIN, MediaAsset::ROLE_GALLERY], true))
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values();

        return ($mediaAssets->firstWhere('role', MediaAsset::ROLE_MAIN) ?? $mediaAssets->first())?->url;
    }

    private function hoverImageUrlFromLoadedMedia(Product $product, ?string $mainImageUrl = null): ?string
    {
        return collect($product->mediaAssets ?? [])
            ->filter(fn ($asset) => in_array($asset->role, [MediaAsset::ROLE_MAIN, MediaAsset::ROLE_GALLERY], true))
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(fn ($asset) => $asset->url ?? null)
            ->filter(fn ($url) => $url !== null && $url !== $mainImageUrl)
            ->first();
    }

    private function resolveEffectivePrice(Store $store, Product $product, int|float $qty = 1): ?float
    {
        $publicPrice = $product->public_price !== null ? (float) $product->public_price : null;
        $customer = auth('customer')->user();
        $cacheKey = implode('|', [(int) ($store->id ?? 0), (int) ($store->ditta_cg18 ?? 0), $customer?->getAuthIdentifier() ?? 'guest', trim((string) ($product->sku ?? '')), number_format(max(1, (float) $qty), 3, '.', '')]);

        if (array_key_exists($cacheKey, $this->effectivePriceCache)) {
            return $this->effectivePriceCache[$cacheKey];
        }

        try {
            $resolved = app(ProductPriceService::class)->resolveForListing($store, $product, max(1, (float) $qty), $customer);

            return $this->effectivePriceCache[$cacheKey] = array_key_exists('price', $resolved) && $resolved['price'] !== null
                ? (float) $resolved['price']
                : $publicPrice;
        } catch (\Throwable) {
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

        return [$resolvedTipocf > 0 ? $resolvedTipocf : $tipocf, $resolvedClifor > 0 ? $resolvedClifor : $clifor];
    }

    private function isSellable(Store $store, Product $product): bool
    {
        if (!$product->is_active) {
            return false;
        }

        $qty = (float) ($product->stock_qty ?? 0);

        return !$store->is_b2b ? $qty > 0 : ($qty > 0 || !$product->no_backorder);
    }

    private function resolveListingQuantityConstraints(Product $product): array
    {
        $minOrderQty = max(1, (int) ceil((float) ($product->min_order_qty ?? 1)));
        $packMultiple = max(1, (int) ceil((float) ($product->pzconf_mg68 ?? 0)));
        $quantityMin = max($minOrderQty, $packMultiple);

        if ($packMultiple > 1 && $quantityMin % $packMultiple !== 0) {
            $quantityMin = (int) (ceil($quantityMin / $packMultiple) * $packMultiple);
        }

        return [
            'min_order_qty' => $minOrderQty,
            'pack_multiple' => $packMultiple,
            'quantity_min' => $quantityMin,
            'quantity_step' => max(1, $packMultiple > 1 ? $packMultiple : $quantityMin),
            'show_pack_multiple' => $packMultiple > 1,
        ];
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginatorContract
    {
        return new LengthAwarePaginator(collect(), 0, $perPage, 1, ['path' => request()->url(), 'query' => request()->query()]);
    }

    private function baseProductWithRelations(): array
    {
        return $this->detailProductWithRelations(app()->getLocale());
    }

    private function listingProductWithRelations(string $locale): array
{
    $locales = $this->localesForLoading($locale);

    return [
        'translations' => fn ($query) => $query->whereIn('locale', $locales),
        'mediaAssets',
        'productAttributeValues' => fn ($query) => $query->whereHas('attribute', function (Builder $attribute) {
            $attribute
                ->where('is_variant', true)
                ->orWhere('is_filterable', true);
        }),
        'productAttributeValues.attribute.translations' => fn ($query) => $query->whereIn('locale', $locales),
        'productAttributeValues.value.translations' => fn ($query) => $query->whereIn('locale', $locales),
        'productAttributeValues.value.mediaAssets',
    ];
}

    private function detailProductWithRelations(string $locale): array
    {
        $locales = $this->localesForLoading($locale);

        return [
            'translations' => fn ($query) => $query->whereIn('locale', $locales),
            'mediaAssets',
            'comparisons',
            'productAttributeValues.attribute.translations' => fn ($query) => $query->whereIn('locale', $locales),
            'productAttributeValues.value.translations' => fn ($query) => $query->whereIn('locale', $locales),
            'productAttributeValues.value.mediaAssets',
        ];
    }

    private function visibleGroupCodes(Store $store): Collection
    {
        $cacheKey = implode('|', [(int) $store->ditta_cg18, (int) $store->erp_site_code]);

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

    private function filterFacetSortSignature(): string
    {
        return Attribute::query()
            ->where('is_filterable', true)
            ->orderBy('id')
            ->get(['id', 'sort_order'])
            ->map(fn (Attribute $attribute) => $attribute->id.':'.((int) ($attribute->sort_order ?? 999)))
            ->implode('|');
    }

    private function categoryScopedCacheKey(Store $store, ?string $locale = null, ?string $fam = null, ?string $sfam = null, ?string $gruppo = null, ?string $sgruppo = null, ?int $tipocf = null, ?int $clifor = null): string
    {
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
        return (clone $query)
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
            'sku_asc' => $query->orderByRaw("CASE WHEN type = 'configurable' THEN 0 ELSE 1 END")->orderBy('sku'),
            'sku_desc' => $query->orderByRaw("CASE WHEN type = 'configurable' THEN 0 ELSE 1 END")->orderByDesc('sku'),
            'name_asc' => $query
                ->leftJoin('product_translations as sort_translations', fn ($join) => $join->on('sort_translations.product_id', '=', 'products.id')->where('sort_translations.locale', '=', app()->getLocale()))
                ->select('products.*')
                ->orderByRaw("CASE WHEN products.type = 'configurable' THEN 0 ELSE 1 END")
                ->orderByRaw('COALESCE(sort_translations.name, products.sku) asc')
                ->orderBy('products.sku'),
            'name_desc' => $query
                ->leftJoin('product_translations as sort_translations', fn ($join) => $join->on('sort_translations.product_id', '=', 'products.id')->where('sort_translations.locale', '=', app()->getLocale()))
                ->select('products.*')
                ->orderByRaw("CASE WHEN products.type = 'configurable' THEN 0 ELSE 1 END")
                ->orderByRaw('COALESCE(sort_translations.name, products.sku) desc')
                ->orderBy('products.sku'),
            'price_asc' => $query->orderByRaw('COALESCE(public_price, 999999999) asc')->orderBy('sku'),
            'price_desc' => $query->orderByRaw('COALESCE(public_price, 0) desc')->orderBy('sku'),
            'newest' => $query->orderByDesc('flgnovita_webt01')->orderByDesc('erp_lastchange')->orderBy('sku'),
            default => $query->orderByRaw("CASE WHEN type = 'configurable' THEN 0 ELSE 1 END")->orderBy('sku'),
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
            $matchedVariants = $variantOptions
                ->filter(function (array $variant) use ($normalizedFilters) {
                    $valueKeys = collect($variant['value_keys'] ?? []);

                    foreach ($normalizedFilters as $attributeCode => $acceptedValues) {
                        $variantValue = (string) $valueKeys->get((string) $attributeCode, '');

                        if ($variantValue === '' || !in_array($variantValue, $acceptedValues, true)) {
                            return false;
                        }
                    }

                    return !empty($variant['sku']);
                })
                ->values();

            if ($matchedVariants->isNotEmpty()) {
                return (string) ($matchedVariants->random()['sku'] ?? $fallbackSku);
            }
        }

        $availableVariants = $variantOptions
            ->filter(fn (array $variant) => !empty($variant['sku']))
            ->values();

        if ($availableVariants->isEmpty()) {
            return $fallbackSku;
        }

        foreach ([
            fn (array $variant) => ((float) ($variant['stock_qty'] ?? 0)) > 0 && (bool) ($variant['has_image'] ?? false),
            fn (array $variant) => (bool) ($variant['has_image'] ?? false),
            fn (array $variant) => ((float) ($variant['stock_qty'] ?? 0)) > 0,
            fn (array $variant) => !empty($variant['sku']),
        ] as $predicate) {
            $candidates = $availableVariants
                ->filter(fn (array $variant) => $predicate($variant))
                ->values();

            if ($candidates->isNotEmpty()) {
                return (string) ($candidates->random()['sku'] ?? $fallbackSku);
            }
        }

        return $fallbackSku;
    }

    private function applyAttributeFilters(Builder $query, array $filters): void
    {
        $normalizedFilters = collect($filters)
            ->mapWithKeys(function ($values, string|int $code) {
                $values = collect(is_array($values) ? $values : [$values])->map(fn ($value) => trim((string) $value))->filter()->unique()->values();

                return $values->isNotEmpty() ? [(string) $code => $values] : [];
            });

        foreach ($normalizedFilters as $attributeCode => $values) {
            $query->where(function (Builder $productQuery) use ($attributeCode, $values) {
                $productQuery->whereHas('productAttributeValues', function (Builder $attributeQuery) use ($attributeCode, $values) {
                    $attributeQuery
                        ->whereHas('attribute', fn (Builder $attribute) => $attribute->where('code', $attributeCode))
                        ->whereIn('value_key', $values->all());
                });

                $productQuery->orWhereExists(function ($subQuery) use ($attributeCode, $values) {
                    $subQuery->selectRaw('1')
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
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($term)) . '%';

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
                ->orWhereHas('translations', function (Builder $translation) use ($like, $locale) {
                    $translation->where('locale', $locale)
                        ->where(fn (Builder $text) => $text->where('name', 'like', $like)->orWhere('description', 'like', $like)->orWhere('short_description', 'like', $like));
                })
                ->orWhereExists(function ($subQuery) use ($like) {
                    $subQuery->selectRaw('1')
                        ->from('product_comparisons as pc')
                        ->whereColumn('pc.ditta_cg18', 'products.ditta_cg18')
                        ->whereColumn('pc.site_type', 'products.site_type')
                        ->whereColumn('pc.sku', 'products.sku')
                        ->where('pc.comparison_sku', 'like', $like);
                })
                ->orWhereExists(function ($subQuery) use ($like, $locale) {
                    $subQuery->selectRaw('1')
                        ->from('products as child_search_products')
                        ->leftJoin('product_translations as child_search_translations', function ($join) use ($locale) {
                            $join->on('child_search_translations.product_id', '=', 'child_search_products.id')
                                ->where('child_search_translations.locale', '=', $locale);
                        })
                        ->whereColumn('child_search_products.parent_code', 'products.sku')
                        ->whereColumn('child_search_products.ditta_cg18', 'products.ditta_cg18')
                        ->whereColumn('child_search_products.site_type', 'products.site_type')
                        ->where('child_search_products.type', 'simple')
                        ->where('child_search_products.is_active', 1)
                        ->where(function ($child) use ($like) {
                            $child->where('child_search_products.sku', 'like', $like)
                                ->orWhere('child_search_products.barcode', 'like', $like)
                                ->orWhere('child_search_products.marca_mg64', 'like', $like)
                                ->orWhere('child_search_translations.name', 'like', $like)
                                ->orWhere('child_search_translations.description', 'like', $like)
                                ->orWhere('child_search_translations.short_description', 'like', $like);
                        });
                });
        });
    }

    private function localesForLoading(string $locale): array
    {
        return collect([$locale, config('app.fallback_locale', 'en')])->filter()->unique()->values()->all();
    }

    private function loadedTranslation(mixed $model, string $locale): mixed
    {
        if (!is_object($model) || !method_exists($model, 'relationLoaded') || !$model->relationLoaded('translations')) {
            return null;
        }

        $translations = collect($model->getRelation('translations'));
        $fallback = (string) config('app.fallback_locale', 'en');

        return $translations->firstWhere('locale', $locale)
            ?: $translations->firstWhere('locale', $fallback)
            ?: $translations->first();
    }
}
