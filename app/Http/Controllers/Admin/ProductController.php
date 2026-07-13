<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\GroupDescription;
use App\Models\Product;
use App\Models\ProductComparison;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        /** @var Store $store */
        $store = $this->currentStore();
        $locale = app()->getLocale();

        $q = Product::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
            ->with([
                'translations',
                'mediaAssets',
                'productAttributeValues.attribute.translations',
                'productAttributeValues.value.translations',
                'productAttributeValues.value.mediaAssets',
            ])
            ->orderBy('sku');

        if ($request->filled('sku')) {
            $q->where('sku', 'like', '%' . trim((string) $request->input('sku')) . '%');
        }

        if ($request->filled('type')) {
            $q->where('type', trim((string) $request->input('type')));
        }

        if ($request->filled('fam_99')) {
            $q->where('fam_99', Product::normalizeErpCodeValue((string) $request->input('fam_99')));
        }

        if ($request->filled('sfam_99')) {
            $q->where('sfam_99', Product::normalizeErpCodeValue((string) $request->input('sfam_99')));
        }

        if ($request->filled('gruppo_99')) {
            $q->where('gruppo_99', Product::normalizeErpCodeValue((string) $request->input('gruppo_99')));
        }

        if ($request->filled('sgruppo_99')) {
            $q->where('sgruppo_99', Product::normalizeErpCodeValue((string) $request->input('sgruppo_99')));
        }

        if ($request->filled('has_price')) {
            $hasPrice = filter_var(
                $request->input('has_price'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($hasPrice === true) {
                $q->where(function ($sub) use ($store) {
                    $sub->whereNotNull('public_price')
                        ->orWhereExists(function ($tierSub) use ($store) {
                            $tierSub->selectRaw('1')
                                ->from('price_tiers')
                                ->whereColumn('price_tiers.sku', 'products.sku')
                                ->where('price_tiers.ditta_cg18', (int) $store->ditta_cg18);
                        });
                });
            } elseif ($hasPrice === false) {
                $q->whereNull('public_price')
                    ->whereNotExists(function ($tierSub) use ($store) {
                        $tierSub->selectRaw('1')
                            ->from('price_tiers')
                            ->whereColumn('price_tiers.sku', 'products.sku')
                            ->where('price_tiers.ditta_cg18', (int) $store->ditta_cg18);
                    });
            }
        }

        if ($request->filled('is_active')) {
            $isActiveRaw = strtolower(trim((string) $request->input('is_active')));

            if ($isActiveRaw !== 'all') {
                $q->where(
                    'is_active',
                    filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL)
                );
            }
        } else {
            $q->where('is_active', true);
        }

        $products = $q->paginate(50)->withQueryString();

        $items = collect($products->items());

        $this->enrichProductCategoryDescriptions($store, $items, $locale);
        $this->decorateProductsWithAttributePresentation($items, $locale);

        $priceOverviewMap = $this->loadPriceOverviewMap($store, $items);

        foreach ($products->items() as $product) {
            $overview = $priceOverviewMap->get($product->sku, [
                'tier_rows_count' => 0,
                'listini_count' => 0,
                'customer_count' => 0,
                'group_count' => 0,
                'min_price_net' => null,
                'max_price_net' => null,
            ]);

            $product->setAttribute('tier_rows_count', (int) ($overview['tier_rows_count'] ?? 0));
            $product->setAttribute('listini_count', (int) ($overview['listini_count'] ?? 0));
            $product->setAttribute('customer_count', (int) ($overview['customer_count'] ?? 0));
            $product->setAttribute('group_count', (int) ($overview['group_count'] ?? 0));
            $product->setAttribute('min_price_net', isset($overview['min_price_net']) ? (float) $overview['min_price_net'] : null);
            $product->setAttribute('max_price_net', isset($overview['max_price_net']) ? (float) $overview['max_price_net'] : null);
            $product->setAttribute('has_public_price', $product->public_price !== null);
            $product->setAttribute('has_any_price', $product->public_price !== null || (int) ($overview['tier_rows_count'] ?? 0) > 0);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $products->items(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                ],
            ]);
        }

        return view('admin.products.index', [
            'store' => $store,
            'products' => $products,
            'filters' => [
                'sku' => (string) $request->input('sku', ''),
                'type' => (string) $request->input('type', ''),
                'is_active' => (string) $request->input('is_active', ''),
                'has_price' => (string) $request->input('has_price', ''),
                'fam_99' => (string) $request->input('fam_99', ''),
                'sfam_99' => (string) $request->input('sfam_99', ''),
                'gruppo_99' => (string) $request->input('gruppo_99', ''),
                'sgruppo_99' => (string) $request->input('sgruppo_99', ''),
            ],
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse|JsonResponse
    {
        /** @var Store $store */
        $store = $this->currentStore();
        $locale = app()->getLocale();

        $data = $request->validated();
        $data['ditta_cg18'] = (int) $store->ditta_cg18;
        $data['site_type'] = (int) $store->erp_site_code;

        $product = Product::create($data);

        $this->enrichSingleProductCategoryDescriptions($store, $product, $locale);
        $this->decorateSingleProductWithAttributePresentation($product, $locale);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Product created',
                'data' => $product,
            ], 201);
        }

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Prodotto creato correttamente.');
    }

    public function show(Request $request, Product $product): View|JsonResponse
    {
        /** @var Store $store */
        $store = $this->currentStore();
        $locale = app()->getLocale();

        $this->guardStoreContext($product, $store);

        $product->load([
            'configurable',
            // 'comparisons', // removed
            'translations',
            'productAttributeValues.attribute.translations',
            'productAttributeValues.value.translations',
            'productAttributeValues.value.mediaAssets',
            'mediaAssets',
        ]);

        $this->loadComparisonsForProduct($product);

        $this->enrichSingleProductCategoryDescriptions($store, $product, $locale);
        $this->decorateSingleProductWithAttributePresentation($product, $locale);

        $parent = $product->parent()
            ->with([
                'translations',
                'mediaAssets',
                'productAttributeValues.attribute.translations',
                'productAttributeValues.value.translations',
                'productAttributeValues.value.mediaAssets',
            ])
            ->first();

        if ($parent instanceof Product) {
            $this->loadComparisonsForProduct($parent);
            $this->enrichSingleProductCategoryDescriptions($store, $parent, $locale);
            $this->decorateSingleProductWithAttributePresentation($parent, $locale);
        }

        $children = $product->children()
            ->with([
                'translations',
                'mediaAssets',
                'productAttributeValues.attribute.translations',
                'productAttributeValues.value.translations',
                'productAttributeValues.value.mediaAssets',
            ])
            ->get();

        $this->enrichProductCategoryDescriptions($store, $children, $locale);
        $this->decorateProductsWithAttributePresentation($children, $locale);
        $children->each(function (Product $child) {
            $this->loadComparisonsForProduct($child);
        });

        $productAttributePresentation = collect($product->getAttribute('product_attribute_presentation') ?? []);
        $childrenAttributePresentation = $children
            ->mapWithKeys(function (Product $child) {
                return [
                    $child->getKey() => collect($child->getAttribute('product_attribute_presentation') ?? []),
                ];
            });

        $productComparisons = $this->mapProductComparisons($product);

        $priceTiers = DB::table('price_tiers')
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('sku', $product->sku)
            ->orderBy('listino_id')
            ->orderByRaw('CAST(qty_from AS DECIMAL(18,3)) asc')
            ->get();

        $listinoIds = $priceTiers
            ->pluck('listino_id')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        $customerListinoAssignments = collect();
        $customerGroupAssignments = collect();

        if (!empty($listinoIds)) {
            $customerListinoAssignments = DB::table('customer_listino_assignments as cla')
                ->join('customers as c', function ($join) {
                    $join->on('c.ditta_cg18', '=', 'cla.ditta_cg18')
                        ->on('c.clifor_cg44', '=', 'cla.clifor_cg44');
                })
                ->select([
                    'cla.ditta_cg18',
                    'c.tipocf_cg44',
                    'cla.clifor_cg44',
                    'c.ragsoanag_cg16',
                    'cla.listino_id as codlistinoded',
                    'cla.is_active',
                ])
                ->where('cla.ditta_cg18', (int) $store->ditta_cg18)
                ->whereIn('cla.listino_id', $listinoIds)
                ->where('cla.is_active', true)
                ->orderBy('cla.listino_id')
                ->orderBy('c.tipocf_cg44')
                ->orderBy('cla.clifor_cg44')
                ->get();

            $customerGroupAssignments = DB::table('customer_listino_assignments as cla')
                ->join('customers as c', function ($join) {
                    $join->on('c.ditta_cg18', '=', 'cla.ditta_cg18')
                        ->on('c.clifor_cg44', '=', 'cla.clifor_cg44');
                })
                ->join('customer_visible_groups as cvg', function ($join) {
                    $join->on('cvg.ditta_cg18', '=', 'c.ditta_cg18')
                        ->on('cvg.tipocf_cg44', '=', 'c.tipocf_cg44')
                        ->on('cvg.clifor_cg44', '=', 'c.clifor_cg44')
                        ->where('cvg.is_active', '=', 1);
                })
                ->select([
                    'cla.listino_id as codlistinoded',
                    'c.tipocf_cg44',
                    'cla.clifor_cg44',
                    'c.ragsoanag_cg16',
                    'cvg.codice_xx32 as group_code',
                    'cvg.descrizione_xx32 as group_description',
                ])
                ->where('cla.ditta_cg18', (int) $store->ditta_cg18)
                ->whereIn('cla.listino_id', $listinoIds)
                ->where('cla.is_active', true)
                ->orderBy('cla.listino_id')
                ->orderBy('cvg.codice_xx32')
                ->orderBy('c.tipocf_cg44')
                ->orderBy('cla.clifor_cg44')
                ->get();
        }

        $tierSummaryByListino = $priceTiers
            ->groupBy('listino_id')
            ->map(function (Collection $rows, $listinoId) use ($customerListinoAssignments, $customerGroupAssignments) {
                $prices = $rows
                    ->pluck('price_net')
                    ->filter(fn ($value) => $value !== null)
                    ->map(fn ($value) => (float) $value)
                    ->values();

                $customers = $customerListinoAssignments
                    ->where('codlistinoded', (int) $listinoId)
                    ->values();

                $groups = $customerGroupAssignments
                    ->where('codlistinoded', (int) $listinoId)
                    ->map(fn ($row) => trim((string) ($row->group_code ?? '')))
                    ->filter()
                    ->unique()
                    ->values();

                return (object) [
                    'listino_id' => (int) $listinoId,
                    'rows_count' => $rows->count(),
                    'min_price_net' => $prices->isNotEmpty() ? $prices->min() : null,
                    'max_price_net' => $prices->isNotEmpty() ? $prices->max() : null,
                    'customers_count' => $customers->count(),
                    'groups_count' => $groups->count(),
                    'customers' => $customers,
                ];
            })
            ->sortKeys()
            ->values();

        $childPriceOverviewMap = $this->loadPriceOverviewMap($store, $children);

        foreach ($children as $child) {
            $overview = $childPriceOverviewMap->get($child->sku, [
                'tier_rows_count' => 0,
                'listini_count' => 0,
                'customer_count' => 0,
                'group_count' => 0,
                'min_price_net' => null,
                'max_price_net' => null,
            ]);

            $child->setAttribute('tier_rows_count', (int) ($overview['tier_rows_count'] ?? 0));
            $child->setAttribute('listini_count', (int) ($overview['listini_count'] ?? 0));
            $child->setAttribute('customer_count', (int) ($overview['customer_count'] ?? 0));
            $child->setAttribute('group_count', (int) ($overview['group_count'] ?? 0));
            $child->setAttribute('min_price_net', isset($overview['min_price_net']) ? (float) $overview['min_price_net'] : null);
            $child->setAttribute('max_price_net', isset($overview['max_price_net']) ? (float) $overview['max_price_net'] : null);
        }

        $product->setAttribute('tier_rows_count', $priceTiers->count());
        $product->setAttribute('listini_count', count($listinoIds));
        $product->setAttribute('customer_count', $customerListinoAssignments->count());
        $product->setAttribute(
            'group_count',
            $customerGroupAssignments
                ->map(fn ($row) => trim((string) ($row->group_code ?? '')))
                ->filter()
                ->unique()
                ->count()
        );
        $product->setAttribute(
            'min_price_net',
            $priceTiers->pluck('price_net')->filter(fn ($value) => $value !== null)->isNotEmpty()
                ? (float) $priceTiers->pluck('price_net')->filter(fn ($value) => $value !== null)->min()
                : null
        );
        $product->setAttribute(
            'max_price_net',
            $priceTiers->pluck('price_net')->filter(fn ($value) => $value !== null)->isNotEmpty()
                ? (float) $priceTiers->pluck('price_net')->filter(fn ($value) => $value !== null)->max()
                : null
        );

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'product' => $product,
                    'parent' => $parent,
                    'children' => $children,
                    'price_tiers' => $priceTiers,
                    'tier_summary_by_listino' => $tierSummaryByListino,
                    'customer_listino_assignments' => $customerListinoAssignments,
                    'customer_group_assignments' => $customerGroupAssignments,
                    'product_attribute_presentation' => $productAttributePresentation,
                    'children_attribute_presentation' => $childrenAttributePresentation,
                    'product_comparisons' => $productComparisons,
                ],
            ]);
        }

        return view('admin.products.show', [
            'store' => $store,
            'product' => $product,
            'parent' => $parent,
            'children' => $children,
            'priceTiers' => $priceTiers,
            'tierSummaryByListino' => $tierSummaryByListino,
            'customerListinoAssignments' => $customerListinoAssignments,
            'customerGroupAssignments' => $customerGroupAssignments,
            'productAttributePresentation' => $productAttributePresentation,
            'childrenAttributePresentation' => $childrenAttributePresentation,
            'productComparisons' => $productComparisons,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse|JsonResponse
    {
        /** @var Store $store */
        $store = $this->currentStore();
        $locale = app()->getLocale();

        $this->guardStoreContext($product, $store);

        $data = $request->validated();
        unset($data['ditta_cg18'], $data['site_type'], $data['sku']);

        $product->update($data);

        $product = $product->fresh([
            'translations',
            'mediaAssets',
            'productAttributeValues.attribute.translations',
            'productAttributeValues.value.translations',
            'productAttributeValues.value.mediaAssets',
        ]);

        if ($product instanceof Product) {
            $this->enrichSingleProductCategoryDescriptions($store, $product, $locale);
            $this->decorateSingleProductWithAttributePresentation($product, $locale);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Product updated',
                'data' => $product,
            ]);
        }

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Prodotto aggiornato correttamente.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        /** @var Store $store */
        $store = $this->currentStore();

        $this->guardStoreContext($product, $store);

        $product->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Product deleted',
            ]);
        }

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Prodotto eliminato correttamente.');
    }

    private function loadComparisonsForProduct(Product $product): void
    {
        $comparisons = ProductComparison::query()
            ->where('ditta_cg18', (int) $product->ditta_cg18)
            ->where('site_type', (int) $product->site_type)
            ->where('sku', (string) $product->sku)
            ->orderBy('source')
            ->orderBy('comparison_sku')
            ->orderBy('id')
            ->get();

        $product->setRelation('comparisons', $comparisons);
    }

    private function mapProductComparisons(Product $product): Collection
    {
        return collect($product->comparisons ?? [])
            ->filter(fn ($item) => $item instanceof ProductComparison)
            ->map(function (ProductComparison $comparison) {
                return [
                    'source' => $comparison->source,
                    'comparison_sku' => $comparison->comparison_sku,
                    'erp_lastchange' => $comparison->erp_lastchange,
                ];
            })
            ->values();
    }

    private function decorateProductsWithAttributePresentation(Collection $products, string $locale): void
    {
        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $this->decorateSingleProductWithAttributePresentation($product, $locale);
        }
    }

    private function decorateSingleProductWithAttributePresentation(Product $product, string $locale): void
    {
        $product->loadMissing([
            'productAttributeValues.attribute.translations',
            'productAttributeValues.value.translations',
            'productAttributeValues.value.mediaAssets',
        ]);

        $attributePresentation = $this->mapProductAttributePresentation($product, $locale);

        $product->setAttribute('product_attribute_presentation', $attributePresentation);
        $product->setAttribute(
            'grammatura_value',
            $this->findAttributePresentationValue($attributePresentation, ['A23'], ['grammatura'])
        );
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
                $swatchUrl = $row->value?->swatch()?->url;

                return [
                    'code' => $attributeCode !== '' ? $attributeCode : null,
                    'label' => $attributeLabel,
                    'value' => $attributeValue,
                    'raw_value' => $row->raw_value,
                    'normalized_label' => mb_strtolower(trim((string) $attributeLabel)),
                    'normalized_code' => mb_strtolower($attributeCode),
                    'swatch_url' => $swatchUrl,
                ];
            })
            ->values();
    }

    private function findAttributePresentationValue(Collection $rows, array $codes = [], array $labels = []): ?string
    {
        $normalizedCodes = collect($codes)
            ->map(fn ($code) => mb_strtolower(trim((string) $code)))
            ->filter()
            ->values()
            ->all();

        $normalizedLabels = collect($labels)
            ->map(fn ($label) => mb_strtolower(trim((string) $label)))
            ->filter()
            ->values()
            ->all();

        $row = $rows->first(function (array $item) use ($normalizedCodes, $normalizedLabels) {
            return in_array($item['normalized_code'] ?? null, $normalizedCodes, true)
                || in_array($item['normalized_label'] ?? null, $normalizedLabels, true);
        });

        return is_array($row) ? ($row['value'] ?? null) : null;
    }

    private function currentStore(): Store
    {
        /** @var Store $store */
        $store = admin_store();

        return $store;
    }

    private function guardStoreContext(Product $product, Store $store): void
    {
        if (
            (int) $product->ditta_cg18 !== (int) $store->ditta_cg18 ||
            (int) $product->site_type !== (int) $store->erp_site_code
        ) {
            abort(404);
        }
    }

    private function loadPriceOverviewMap(Store $store, Collection $products): Collection
    {
        if ($products->isEmpty()) {
            return collect();
        }

        $skus = $products
            ->pluck('sku')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($skus)) {
            return collect();
        }

        $tiers = DB::table('price_tiers')
            ->select([
                'sku',
                'listino_id',
                'price_net',
            ])
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->whereIn('sku', $skus)
            ->orderBy('sku')
            ->orderBy('listino_id')
            ->get();

        $listinoIds = $tiers
            ->pluck('listino_id')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        $customerCountsByListino = collect();
        $groupCountsByListino = collect();

        if (!empty($listinoIds)) {
            $customerCountsByListino = DB::table('customer_listino_assignments')
                ->selectRaw('listino_id, COUNT(*) as aggregate_count')
                ->where('ditta_cg18', (int) $store->ditta_cg18)
                ->whereIn('listino_id', $listinoIds)
                ->where('is_active', true)
                ->groupBy('listino_id')
                ->pluck('aggregate_count', 'listino_id');

            $groupCountsByListino = DB::table('customer_listino_assignments as cla')
                ->join('customers as c', function ($join) {
                    $join->on('c.ditta_cg18', '=', 'cla.ditta_cg18')
                        ->on('c.clifor_cg44', '=', 'cla.clifor_cg44');
                })
                ->join('customer_visible_groups as cvg', function ($join) {
                    $join->on('cvg.ditta_cg18', '=', 'c.ditta_cg18')
                        ->on('cvg.tipocf_cg44', '=', 'c.tipocf_cg44')
                        ->on('cvg.clifor_cg44', '=', 'c.clifor_cg44')
                        ->where('cvg.is_active', '=', 1);
                })
                ->selectRaw('cla.listino_id, COUNT(DISTINCT cvg.codice_xx32) as aggregate_count')
                ->where('cla.ditta_cg18', (int) $store->ditta_cg18)
                ->whereIn('cla.listino_id', $listinoIds)
                ->where('cla.is_active', true)
                ->groupBy('cla.listino_id')
                ->pluck('aggregate_count', 'cla.listino_id');
        }

        return $tiers
            ->groupBy('sku')
            ->map(function (Collection $rows) use ($customerCountsByListino, $groupCountsByListino) {
                $listini = $rows
                    ->pluck('listino_id')
                    ->filter(fn ($value) => $value !== null)
                    ->map(fn ($value) => (int) $value)
                    ->unique()
                    ->values();

                $prices = $rows
                    ->pluck('price_net')
                    ->filter(fn ($value) => $value !== null)
                    ->map(fn ($value) => (float) $value)
                    ->values();

                $customerCount = $listini
                    ->sum(fn ($listinoId) => (int) ($customerCountsByListino[$listinoId] ?? 0));

                $groupCount = $listini
                    ->sum(fn ($listinoId) => (int) ($groupCountsByListino[$listinoId] ?? 0));

                return [
                    'tier_rows_count' => $rows->count(),
                    'listini_count' => $listini->count(),
                    'customer_count' => $customerCount,
                    'group_count' => $groupCount,
                    'min_price_net' => $prices->isNotEmpty() ? $prices->min() : null,
                    'max_price_net' => $prices->isNotEmpty() ? $prices->max() : null,
                ];
            });
    }

    private function enrichProductCategoryDescriptions(Store $store, Collection $products, string $locale): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $maps = $this->loadCategoryDescriptionMaps($store, $products, $locale);

        foreach ($products as $product) {
            if ($product instanceof Product) {
                $this->applyCategoryDescriptionsToProduct($product, $maps);
            }
        }
    }

    private function enrichSingleProductCategoryDescriptions(Store $store, Product $product, string $locale): void
    {
        $this->enrichProductCategoryDescriptions($store, collect([$product]), $locale);
    }

    private function loadCategoryDescriptionMaps(Store $store, Collection $products, string $locale): array
    {
        $famCodes = $products
            ->map(fn (Product $product) => Product::normalizeErpCodeValue($product->fam_99))
            ->filter()
            ->unique()
            ->values();

        $sfamRows = collect();
        $groupRows = collect();

        $famDescriptions = collect();
        $sfamDescriptions = collect();
        $gruppoDescriptions = collect();

        if ($famCodes->isNotEmpty()) {
            $famDescriptions = GroupDescription::query()
                ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
                ->forLocale($locale)
                ->active()
                ->whereIn('fam_code', $famCodes->all())
                ->whereNull('sfam_code')
                ->whereNull('gruppo_code')
                ->pluck('description', 'fam_code');
        }

        $sfamCodes = $products
            ->map(fn (Product $product) => Product::normalizeErpCodeValue($product->sfam_99))
            ->filter()
            ->unique()
            ->values();

        if ($famCodes->isNotEmpty() && $sfamCodes->isNotEmpty()) {
            $sfamRows = GroupDescription::query()
                ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
                ->forLocale($locale)
                ->active()
                ->whereIn('fam_code', $famCodes->all())
                ->whereIn('sfam_code', $sfamCodes->all())
                ->whereNotNull('sfam_code')
                ->whereNull('gruppo_code')
                ->get();

            $sfamDescriptions = $sfamRows->mapWithKeys(function (GroupDescription $row) {
                return [
                    $row->fam_code . '|' . $row->sfam_code => $row->description,
                ];
            });
        }

        $gruppoCodes = $products
            ->map(fn (Product $product) => Product::normalizeErpCodeValue($product->gruppo_99))
            ->filter()
            ->unique()
            ->values();

        if ($famCodes->isNotEmpty() && $sfamCodes->isNotEmpty() && $gruppoCodes->isNotEmpty()) {
            $groupRows = GroupDescription::query()
                ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
                ->forLocale($locale)
                ->active()
                ->whereIn('fam_code', $famCodes->all())
                ->whereIn('sfam_code', $sfamCodes->all())
                ->whereIn('gruppo_code', $gruppoCodes->all())
                ->whereNotNull('sfam_code')
                ->whereNotNull('gruppo_code')
                ->get();

            $gruppoDescriptions = $groupRows->mapWithKeys(function (GroupDescription $row) {
                return [
                    $row->fam_code . '|' . $row->sfam_code . '|' . $row->gruppo_code => $row->description,
                ];
            });
        }

        return [
            'fam' => $famDescriptions,
            'sfam' => $sfamDescriptions,
            'gruppo' => $gruppoDescriptions,
        ];
    }

    private function applyCategoryDescriptionsToProduct(Product $product, array $maps): void
    {
        $famCode = Product::normalizeErpCodeValue($product->fam_99);
        $sfamCode = Product::normalizeErpCodeValue($product->sfam_99);
        $gruppoCode = Product::normalizeErpCodeValue($product->gruppo_99);
        $sgruppoCode = Product::normalizeErpCodeValue($product->sgruppo_99);

        $famDescription = $famCode !== null
            ? ($maps['fam'][$famCode] ?? null)
            : null;

        $sfamDescription = ($famCode !== null && $sfamCode !== null)
            ? ($maps['sfam'][$famCode . '|' . $sfamCode] ?? null)
            : null;

        $gruppoDescription = ($famCode !== null && $sfamCode !== null && $gruppoCode !== null)
            ? ($maps['gruppo'][$famCode . '|' . $sfamCode . '|' . $gruppoCode] ?? null)
            : null;

        $product->setAttribute('fam_description', $famDescription);
        $product->setAttribute('sfam_description', $sfamDescription);
        $product->setAttribute('gruppo_description', $gruppoDescription);
        $product->setAttribute('sgruppo_description', null);

        $categoryPath = collect([
            $famDescription ?: $famCode,
            $sfamDescription ?: $sfamCode,
            $gruppoDescription ?: $gruppoCode,
            $sgruppoCode,
        ])->filter()->implode(' / ');

        $product->setAttribute('category_path_description', $categoryPath !== '' ? $categoryPath : null);
    }
}
