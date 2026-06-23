<?php

namespace App\Data\Storefront;

use App\Models\Store;
use App\Models\StorefrontPage;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final readonly class HomePageInput
{
    public function __construct(
        public Store $store,
        public string $locale,
        public Request $request,
        public mixed $products,
        public Collection $listingCardsByProductSku,
        public Collection $filterFacets,
        public array $activeFilters,
        public string $currentSort,
        public Collection $rootCategories,
        public ?StorefrontPage $storefrontPage,
        public Collection $storefrontPageBlocks,
    ) {}
}
