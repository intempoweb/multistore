<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\ViewData\StorefrontSidebarDataBuilder;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class StorefrontSidebarDataBuilderTest extends TestCase
{
    public function test_it_prepares_multiple_selected_values_for_the_same_facet(): void
    {
        $request = Request::create('/catalog', 'GET', [
            'colore' => ['rosso', 'blu'],
        ]);
        $builder = new StorefrontSidebarDataBuilder($request);

        $sidebar = $builder->build([
            'sidebarActionUrl' => '/catalog',
            'sidebarResetUrl' => '/catalog',
            'activeFilters' => ['COLOR' => ['R', 'B']],
            'filterFacets' => [[
                'code' => 'COLOR',
                'label' => 'Colore',
                'slug' => 'colore',
                'values' => [
                    ['key' => 'R', 'label' => 'Rosso', 'slug' => 'rosso', 'count' => 4],
                    ['key' => 'B', 'label' => 'Blu', 'slug' => 'blu', 'count' => 3],
                    ['key' => 'V', 'label' => 'Verde', 'slug' => 'verde', 'count' => 2],
                ],
            ]],
        ]);

        $this->assertTrue($sidebar['has_active_filters']);
        $this->assertCount(2, $sidebar['active_pills']);
        $this->assertSame(
            [true, true, false],
            $sidebar['facets']->first()['values']->pluck('checked')->all(),
        );
        $this->assertSame(
            ['Colore: Rosso', 'Colore: Blu'],
            $sidebar['active_pills']->pluck('label')->all(),
        );
    }
}
