<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\Catalog\CatalogRequestNormalizer;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class CatalogRequestNormalizerTest extends TestCase
{
    public function test_it_normalizes_sort_and_seo_filter_query_parameters(): void
    {
        $request = Request::create('/catalog', 'GET', [
            'sort' => 'price_desc',
            'grid' => '4',
            'page' => '2',
            'colore' => ['rosso', 'rosso', 'blu'],
            'formato' => '15-x-21',
            'ignored' => 'value',
        ]);

        $facets = collect([
            [
                'code' => 'COLOR',
                'slug' => 'colore',
                'values' => [
                    ['key' => 'R', 'slug' => 'rosso'],
                    ['key' => 'B', 'slug' => 'blu'],
                ],
            ],
            [
                'code' => 'FORMAT',
                'slug' => 'formato',
                'values' => [
                    ['key' => '1521', 'slug' => '15-x-21'],
                ],
            ],
        ]);

        $normalizer = new CatalogRequestNormalizer;

        $this->assertSame(
            'price_desc',
            $normalizer->sort($request->query('sort'))
        );

        $this->assertSame(
            'default',
            $normalizer->sort('unsupported')
        );

        $this->assertSame([
            'COLOR' => ['R', 'B'],
            'FORMAT' => ['1521'],
        ], $normalizer->filters($request, $facets));
    }

    public function test_it_accepts_comma_separated_filter_values(): void
    {
        $request = Request::create('/catalog', 'GET', [
            'colore' => 'rosso,blu',
            'formato' => '15-x-21',
        ]);

        $facets = collect([
            [
                'code' => 'COLOR',
                'slug' => 'colore',
                'values' => [
                    ['key' => 'R', 'slug' => 'rosso'],
                    ['key' => 'B', 'slug' => 'blu'],
                ],
            ],
            [
                'code' => 'FORMAT',
                'slug' => 'formato',
                'values' => [
                    ['key' => '1521', 'slug' => '15-x-21'],
                ],
            ],
        ]);

        $filters = (new CatalogRequestNormalizer)
            ->filters($request, $facets);

        $this->assertSame([
            'COLOR' => ['R', 'B'],
            'FORMAT' => ['1521'],
        ], $filters);
    }

    public function test_it_keeps_legacy_array_filter_urls_compatible(): void
    {
        $request = Request::create('/catalog', 'GET', [
            'colore' => [
                'rosso',
                'blu',
            ],
        ]);

        $facets = collect([
            [
                'code' => 'COLOR',
                'slug' => 'colore',
                'values' => [
                    ['key' => 'R', 'slug' => 'rosso'],
                    ['key' => 'B', 'slug' => 'blu'],
                ],
            ],
        ]);

        $filters = (new CatalogRequestNormalizer)
            ->filters($request, $facets);

        $this->assertSame([
            'COLOR' => ['R', 'B'],
        ], $filters);
    }

    public function test_it_removes_duplicates_from_comma_separated_values(): void
    {
        $request = Request::create('/catalog', 'GET', [
            'colore' => 'rosso,rosso,blu',
        ]);

        $facets = collect([
            [
                'code' => 'COLOR',
                'slug' => 'colore',
                'values' => [
                    ['key' => 'R', 'slug' => 'rosso'],
                    ['key' => 'B', 'slug' => 'blu'],
                ],
            ],
        ]);

        $filters = (new CatalogRequestNormalizer)
            ->filters($request, $facets);

        $this->assertSame([
            'COLOR' => ['R', 'B'],
        ], $filters);
    }

    public function test_it_can_reserve_page_specific_query_parameters(): void
    {
        $request = Request::create('/search', 'GET', [
            'q' => 'agenda',
            'colore' => 'rosso',
        ]);

        $facets = collect([
            [
                'code' => 'COLOR',
                'slug' => 'colore',
                'values' => [
                    ['key' => 'R', 'slug' => 'rosso'],
                ],
            ],
        ]);

        $filters = (new CatalogRequestNormalizer)
            ->filters(
                $request,
                $facets,
                ['q']
            );

        $this->assertSame([
            'COLOR' => ['R'],
        ], $filters);
    }

    public function test_it_normalizes_legacy_arrays_for_public_query(): void
    {
        $request = Request::create('/catalog', 'GET', [
            'colore' => [
                'rosso',
                'blu',
            ],
            'formato' => [
                '20x25',
                '21x30',
            ],
            'sort' => 'price_asc',
            'grid' => '3',
            'page' => '2',
            'q' => 'agenda',
            'agent_context' => '123',
        ]);

        $facets = collect([
            [
                'code' => 'COLOR',
                'slug' => 'colore',
            ],
            [
                'code' => 'FORMAT',
                'slug' => 'formato',
            ],
        ]);

        $query = (new CatalogRequestNormalizer)
            ->publicQuery(
                $request,
                $facets
            );

        $this->assertSame(
            'rosso,blu',
            $query['colore']
        );

        $this->assertSame(
            '20x25,21x30',
            $query['formato']
        );

        $this->assertSame(
            'price_asc',
            $query['sort']
        );

        $this->assertSame(
            '3',
            $query['grid']
        );

        $this->assertSame(
            '2',
            $query['page']
        );

        $this->assertSame(
            'agenda',
            $query['q']
        );

        $this->assertSame(
            '123',
            $query['agent_context']
        );
    }

    public function test_it_normalizes_csv_values_for_public_query(): void
    {
        $request = Request::create('/catalog', 'GET', [
            'colore' => 'rosso,rosso,blu',
            'formato' => '20x25,21x30',
        ]);

        $facets = collect([
            [
                'code' => 'COLOR',
                'slug' => 'colore',
            ],
            [
                'code' => 'FORMAT',
                'slug' => 'formato',
            ],
        ]);

        $query = (new CatalogRequestNormalizer)
            ->publicQuery(
                $request,
                $facets
            );

        $this->assertSame(
            'rosso,blu',
            $query['colore']
        );

        $this->assertSame(
            '20x25,21x30',
            $query['formato']
        );
    }

    public function test_it_removes_empty_facets_from_public_query(): void
    {
        $request = Request::create('/catalog', 'GET', [
            'colore' => [
                '',
                '   ',
            ],
            'q' => 'agenda',
        ]);

        $facets = collect([
            [
                'code' => 'COLOR',
                'slug' => 'colore',
            ],
        ]);

        $query = (new CatalogRequestNormalizer)
            ->publicQuery(
                $request,
                $facets
            );

        $this->assertArrayNotHasKey(
            'colore',
            $query
        );

        $this->assertSame(
            'agenda',
            $query['q']
        );
    }
}