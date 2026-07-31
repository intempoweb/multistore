<?php

namespace App\Services\MediaKit\Selection;

use App\Models\MediaKitRequest;
use App\Models\Product;
use App\Services\MediaKit\MediaKitContext;
use App\Services\MediaKit\MediaKitSelection;
use App\Services\Visibility\ProductVisibilityService;
use Illuminate\Database\Eloquent\Builder;

final class CatalogSelectionResolver implements SelectionResolver
{
    use LoadsMediaAssets;

    public function __construct(
        private readonly ProductVisibilityService $visibility,
    ) {
    }

    public function supports(string $sourceType): bool
    {
        return $sourceType === MediaKitRequest::SOURCE_CATALOG;
    }

    public function resolve(MediaKitRequest $request, MediaKitContext $context): MediaKitSelection
    {
        $meta = $request->meta ?? [];
        $productIds = collect($meta['product_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $skus = collect($meta['skus'] ?? [])->map(fn ($sku) => trim((string) $sku))->filter()->unique()->values();

        if ($productIds->isEmpty() && $skus->isEmpty()) {
            return new MediaKitSelection(collect(), $request->source_type, $request->source_reference, [
                'Nessun prodotto o SKU indicato.',
            ]);
        }

        $query = $this->baseQuery($context);

        $query->where(function (Builder $where) use ($productIds, $skus): void {
            if ($productIds->isNotEmpty()) {
                $where->whereIn('p.id', $productIds->all());
            }

            if ($skus->isNotEmpty()) {
                $method = $productIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                $where->{$method}('p.sku', $skus->all());
            }
        });

        $products = $this->withMediaAssets($query)
            ->limit(max(1, (int) config('mediakit.max_products', 1000)))
            ->get();

        return new MediaKitSelection(
            $products,
            $request->source_type,
            $request->source_reference,
            $this->missingSkuWarnings($skus, $products),
        );
    }

    private function baseQuery(MediaKitContext $context): Builder
    {
        if (
            $context->applyCustomerAcl
            && $context->tipoCf !== null
            && $context->clifor !== null
        ) {
            return $this->visibility->visibleProductsQuery(
                $context->ditta(),
                $context->siteType(),
                $context->tipoCf,
                $context->clifor,
            );
        }

        return Product::query()
            ->from('products as p')
            ->select('p.*')
            ->where('p.ditta_cg18', $context->ditta())
            ->where('p.site_type', $context->siteType());
    }

    private function missingSkuWarnings($requestedSkus, $products): array
    {
        $found = $products->pluck('sku')->map(fn ($sku) => trim((string) $sku));
        $missing = $requestedSkus->diff($found)->values();

        return $missing->isEmpty()
            ? []
            : ['SKU non trovati o non visibili: ' . $missing->implode(', ')];
    }
}
