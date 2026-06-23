<?php

namespace App\Services\Storefront\ViewData;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class ProductListingViewDataBuilder
{
    public function build(
        Request $request,
        mixed $products,
        Collection $listingCardsByProductSku,
        Collection $filterFacets,
        array $activeFilters,
        Collection $childrenCategories,
        string $currentSort,
        string $actionUrl,
        ?string $resetUrl = null,
        string $context = 'category',
    ): array {
        $grid = (int) $request->query('grid', 4);
        $grid = in_array($grid, [2, 3, 4], true) ? $grid : 4;
        $agentContextId = (string) $request->input('agent_context', '');
        $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];
        $activeFiltersCollection = collect($activeFilters);
        $hasActiveFilters = $activeFiltersCollection
            ->flatMap(fn ($values) => is_array($values) ? $values : [$values])
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->isNotEmpty();
        $baseQuery = $request->except(['page', 'grid', 'sort']);

        if ($agentContextId !== '') {
            $baseQuery['agent_context'] = $agentContextId;
        }

        $categoryRows = $childrenCategories->map(function ($category) use ($contextParams) {
            $label = trim((string) ($category['label'] ?? 'Categoria'));
            $description = trim((string) ($category['description'] ?? ''));
            $slug = $category['slug'] ?? null;

            return [
                'label' => $label,
                'description' => $description,
                'show_description' => $description !== '' && $description !== $label,
                'url' => $slug
                    ? route('storefront.category.show', array_merge(['slug' => $slug], $contextParams))
                    : null,
            ];
        })->filter(fn ($category) => filled($category['url']))->values();

        return [
            'listingCardsByProductSku' => $listingCardsByProductSku,
            'filterFacets' => $filterFacets,
            'activeFilters' => $activeFiltersCollection,
            'childrenCategories' => $childrenCategories,
            'agentContextId' => $agentContextId,
            'contextParams' => $contextParams,
            'grid' => $grid,
            'productColClass' => match ($grid) {
                2 => 'col-12 col-md-6',
                3 => 'col-12 col-md-6 col-xl-4',
                default => 'col-12 col-sm-6 col-lg-4 col-xl-3',
            },
            'ciakProductColClass' => match ($grid) {
                2 => 'col-12 col-md-6',
                3 => 'col-12 col-sm-6 col-xl-4',
                default => 'col-12 col-sm-6 col-lg-4 col-xxl-3',
            },
            'currentSort' => $currentSort,
            'baseQuery' => $baseQuery,
            'paginationQuery' => $request->query(),
            'hasActiveFilters' => $hasActiveFilters,
            'hasSidebar' => $childrenCategories->isNotEmpty() || $filterFacets->isNotEmpty() || $hasActiveFilters,
            'productsTotal' => method_exists($products, 'total') ? $products->total() : collect($products)->count(),
            'listingActionUrl' => $actionUrl,
            'listingResetUrl' => $resetUrl ?? $actionUrl,
            'listingContext' => $context,
            'categoryRows' => $categoryRows,
            'listingRows' => collect(method_exists($products, 'items') ? $products->items() : $products)->map(fn ($product) => [
                'product' => $product,
                'listingCard' => collect($listingCardsByProductSku->get((string) $product->sku, [])),
            ])->values(),
        ];
    }
}
