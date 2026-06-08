<?php

namespace App\Repositories\Storefront;

use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
        string $sort = 'default'
    ): LengthAwarePaginator {
        Log::debug('SearchRepository::search called', [
            'store_id' => $store->id ?? null,
            'store_name' => $store->name ?? null,
            'locale' => $locale,
            'query' => $query,
            'tipocf' => $tipocf,
            'clifor' => $clifor,
            'per_page' => $perPage,
            'sort' => $sort,
        ]);

        $results = $this->catalogRepository->searchProducts(
            store: $store,
            locale: $locale,
            query: $query,
            tipocf: $tipocf,
            clifor: $clifor,
            perPage: $perPage,
            sort: $sort
        );

        Log::debug('SearchRepository::search completed', [
            'query' => $query,
            'total' => $results->total(),
            'current_page' => $results->currentPage(),
            'per_page' => $results->perPage(),
            'returned_items' => count($results->items()),
        ]);

        return $results;
    }

    public function suggest(
        Store $store,
        string $locale,
        string $query,
        ?int $tipocf = null,
        ?int $clifor = null,
        int $limit = 6
    ): Collection {
        Log::debug('SearchRepository::suggest called', [
            'store_id' => $store->id ?? null,
            'store_name' => $store->name ?? null,
            'locale' => $locale,
            'query' => $query,
            'tipocf' => $tipocf,
            'clifor' => $clifor,
            'limit' => $limit,
        ]);

        $results = $this->catalogRepository->suggestProducts(
            store: $store,
            locale: $locale,
            query: $query,
            tipocf: $tipocf,
            clifor: $clifor,
            limit: $limit
        );

        Log::debug('SearchRepository::suggest completed', [
            'query' => $query,
            'results_count' => $results->count(),
            'results_sample' => $results->take(10)->values()->all(),
        ]);

        return $results;
    }
}