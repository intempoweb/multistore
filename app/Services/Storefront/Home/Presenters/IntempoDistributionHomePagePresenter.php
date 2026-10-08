<?php

namespace App\Services\Storefront\Home\Presenters;

use App\Data\Storefront\HomePageInput;
use App\Models\Store;
use App\Services\Storefront\Catalog\CatalogRequestNormalizer;
use App\Services\Storefront\Home\HomePagePresenter;

final class IntempoDistributionHomePagePresenter implements HomePagePresenter
{
    public function __construct(
        private CatalogRequestNormalizer $requestNormalizer,
    ) {}

    public function supports(Store $store): bool
    {
        return strtolower(trim((string) $store->theme)) === 'intempodistribution';
    }

    public function present(HomePageInput $input): array
    {
        $grid = (int) $input->request->query('grid', 4);
        $grid = in_array($grid, [2, 3, 4], true) ? $grid : 4;

        $normalizedQuery = $this->requestNormalizer->publicQuery(
            $input->request,
            $input->filterFacets
        );

        $baseQuery = collect($normalizedQuery)
            ->except(['page', 'grid', 'sort'])
            ->all();

        return [
            'listingCardsByProductSku' => $input->listingCardsByProductSku,
            'filterFacets' => $input->filterFacets,
            'activeFilters' => collect($input->activeFilters),
            'childrenCategories' => collect(),
            'grid' => $grid,
            'productColClass' => match ($grid) {
                2 => 'col-12 col-md-6',
                3 => 'col-12 col-md-6 col-xl-4',
                default => 'col-12 col-sm-6 col-lg-4 col-xl-3',
            },
            'baseQuery' => $baseQuery,
            'hasActiveFilters' => collect($input->activeFilters)
                ->flatMap(fn ($values) => is_array($values) ? $values : [$values])
                ->filter(fn ($value) => trim((string) $value) !== '')
                ->isNotEmpty(),
            'productsTotal' => $input->products->total() ?? 0,
            'homeProductsActionUrl' => route('storefront.home'),
            'homeProductRows' => collect($input->products->items())->map(
                fn ($product) => [
                    'product' => $product,
                    'listingCard' => collect(
                        $input->listingCardsByProductSku->get(
                            (string) $product->sku,
                            []
                        )
                    ),
                ]
            ),
        ];
    }
}