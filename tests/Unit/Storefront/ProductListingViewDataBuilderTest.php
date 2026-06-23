<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\ViewData\ProductListingViewDataBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ProductListingViewDataBuilderTest extends TestCase
{
    public function test_it_prepares_listing_rows_grid_and_multiple_filters_for_the_view(): void
    {
        $request = Request::create('/catalog', 'GET', [
            'grid' => '3',
            'sort' => 'name_asc',
            'colore' => ['rosso', 'blu'],
        ]);
        $product = (object) ['sku' => 'SKU-1'];
        $products = new LengthAwarePaginator([$product], 1, 24);

        $data = (new ProductListingViewDataBuilder)->build(
            request: $request,
            products: $products,
            listingCardsByProductSku: collect(['SKU-1' => ['price' => 10]]),
            filterFacets: collect([['code' => 'COLOR']]),
            activeFilters: ['COLOR' => ['R', 'B']],
            childrenCategories: collect(),
            currentSort: 'name_asc',
            actionUrl: '/catalog',
            context: 'catalog',
        );

        $this->assertSame(3, $data['grid']);
        $this->assertSame('col-12 col-md-6 col-xl-4', $data['productColClass']);
        $this->assertTrue($data['hasActiveFilters']);
        $this->assertTrue($data['hasSidebar']);
        $this->assertSame(['rosso', 'blu'], $data['baseQuery']['colore']);
        $this->assertSame($product, $data['listingRows']->first()['product']);
        $this->assertSame(10, $data['listingRows']->first()['listingCard']->get('price'));
    }
}
