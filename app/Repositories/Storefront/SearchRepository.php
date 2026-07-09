<?php

namespace App\Repositories\Storefront;

use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SearchRepository
{
    public function __construct(
        private CatalogRepository $catalogRepository
    ) {
    }

    public function search(
        Store $store,
        string $locale,
        string $query,
        ?int $tipocf = null,
        ?int $clifor = null,
        int $perPage = 24,
        string $sort = 'default',
        array $filters = []
    ): LengthAwarePaginator {
        $results = $this->catalogRepository->searchProducts(
            store: $store,
            locale: $locale,
            query: $query,
            tipocf: $tipocf,
            clifor: $clifor,
            perPage: $perPage,
            sort: $sort,
            filters: $filters
        );

        return $results;
    }

    public function facets(
        Store $store,
        string $locale,
        string $query,
        ?int $tipocf = null,
        ?int $clifor = null,
        array $activeFilters = []
    ): Collection {
        return $this->catalogRepository->getSearchFilterFacets(
            store: $store,
            locale: $locale,
            query: $query,
            tipocf: $tipocf,
            clifor: $clifor,
            activeFilters: $activeFilters
        );
    }

    public function suggest(
        Store $store,
        string $locale,
        string $query,
        ?int $tipocf = null,
        ?int $clifor = null,
        int $limit = 6
    ): Collection {
        $results = $this->catalogRepository->suggestProducts(
            store: $store,
            locale: $locale,
            query: $query,
            tipocf: $tipocf,
            clifor: $clifor,
            limit: $limit
        );

        return $results;
    }
}
