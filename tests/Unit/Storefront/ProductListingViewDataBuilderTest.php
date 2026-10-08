<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\Catalog\CatalogRequestNormalizer;
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

        $product = (object) [
            'sku' => 'SKU-1',
        ];

        $products = new LengthAwarePaginator(
            [$product],
            1,
            24
        );

        $data = (new ProductListingViewDataBuilder(
            new CatalogRequestNormalizer
        ))->build(
            request: $request,
            products: $products,
            listingCardsByProductSku: collect([
                'SKU-1' => [
                    'price' => 10,
                ],
            ]),
            filterFacets: collect([
                [
                    'code' => 'COLOR',
                    'slug' => 'colore',
                ],
            ]),
            activeFilters: [
                'COLOR' => ['R', 'B'],
            ],
            childrenCategories: collect(),
            currentSort: 'name_asc',
            actionUrl: '/catalog',
            context: 'catalog',
        );

        $this->assertSame(
            3,
            $data['grid']
        );

        $this->assertSame(
            'col-12 col-md-6 col-xl-4',
            $data['productColClass']
        );

        $this->assertTrue(
            $data['hasActiveFilters']
        );

        $this->assertTrue(
            $data['hasSidebar']
        );

        $this->assertSame(
            'rosso,blu',
            $data['baseQuery']['colore']
        );

        $this->assertSame(
            'rosso,blu',
            $data['paginationQuery']['colore']
        );

        $this->assertSame(
            $product,
            $data['listingRows']->first()['product']
        );

        $this->assertSame(
            10,
            $data['listingRows']
                ->first()['listingCard']
                ->get('price')
        );
    }

    public function test_it_preserves_non_facet_query_parameters(): void
    {
        $request = Request::create('/search', 'GET', [
            'q' => 'agenda',
            'grid' => '4',
            'colore' => ['rosso', 'blu'],
            'agent_context' => '123',
        ]);

        $products = new LengthAwarePaginator(
            [],
            0,
            24
        );

        $data = (new ProductListingViewDataBuilder(
            new CatalogRequestNormalizer
        ))->build(
            request: $request,
            products: $products,
            listingCardsByProductSku: collect(),
            filterFacets: collect([
                [
                    'code' => 'COLOR',
                    'slug' => 'colore',
                ],
            ]),
            activeFilters: [
                'COLOR' => ['R', 'B'],
            ],
            childrenCategories: collect(),
            currentSort: 'default',
            actionUrl: '/search',
            context: 'search',
        );

        $this->assertSame(
            'agenda',
            $data['baseQuery']['q']
        );

        $this->assertSame(
            '123',
            $data['baseQuery']['agent_context']
        );

        $this->assertSame(
            'rosso,blu',
            $data['baseQuery']['colore']
        );

        $this->assertSame(
            'rosso,blu',
            $data['paginationQuery']['colore']
        );
    }

    public function test_it_preserves_clean_csv_filter_query_parameters(): void
    {
        $request = Request::create('/catalog', 'GET', [
            'colore' => 'rosso,blu',
            'formato' => '20x25,21x30',
            'sort' => 'price_asc',
            'grid' => '3',
            'page' => '2',
        ]);

        $products = new LengthAwarePaginator(
            [],
            0,
            24
        );

        $data = (new ProductListingViewDataBuilder(
            new CatalogRequestNormalizer
        ))->build(
            request: $request,
            products: $products,
            listingCardsByProductSku: collect(),
            filterFacets: collect([
                [
                    'code' => 'COLOR',
                    'slug' => 'colore',
                ],
                [
                    'code' => 'FORMAT',
                    'slug' => 'formato',
                ],
            ]),
            activeFilters: [
                'COLOR' => ['R', 'B'],
                'FORMAT' => ['20x25', '21x30'],
            ],
            childrenCategories: collect(),
            currentSort: 'price_asc',
            actionUrl: '/catalog',
            context: 'catalog',
        );

        $this->assertSame(
            'rosso,blu',
            $data['baseQuery']['colore']
        );

        $this->assertSame(
            '20x25,21x30',
            $data['baseQuery']['formato']
        );

        $this->assertArrayNotHasKey(
            'page',
            $data['baseQuery']
        );

        $this->assertArrayNotHasKey(
            'sort',
            $data['baseQuery']
        );

        $this->assertArrayNotHasKey(
            'grid',
            $data['baseQuery']
        );

        $this->assertSame(
            'rosso,blu',
            $data['paginationQuery']['colore']
        );

        $this->assertSame(
            '20x25,21x30',
            $data['paginationQuery']['formato']
        );

        $this->assertSame(
            'price_asc',
            $data['paginationQuery']['sort']
        );

        $this->assertSame(
            '3',
            $data['paginationQuery']['grid']
        );

        $this->assertSame(
            '2',
            $data['paginationQuery']['page']
        );
    }
}