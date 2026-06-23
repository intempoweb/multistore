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

        $this->assertSame('price_desc', $normalizer->sort($request->query('sort')));
        $this->assertSame('default', $normalizer->sort('unsupported'));
        $this->assertSame([
            'COLOR' => ['R', 'B'],
            'FORMAT' => ['1521'],
        ], $normalizer->filters($request, $facets));
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
                'values' => [['key' => 'R', 'slug' => 'rosso']],
            ],
        ]);

        $filters = (new CatalogRequestNormalizer)->filters($request, $facets, ['q']);

        $this->assertSame(['COLOR' => ['R']], $filters);
    }
}
