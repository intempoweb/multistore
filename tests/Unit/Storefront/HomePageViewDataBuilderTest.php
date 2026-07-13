<?php

namespace Tests\Unit\Storefront;

use App\Data\Storefront\HomePageInput;
use App\Models\Store;
use App\Services\Storefront\Home\HomePageViewDataBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class HomePageViewDataBuilderTest extends TestCase
{
    public function test_it_selects_the_ciak_presenter_for_the_ciak_b2c_theme(): void
    {
        $store = new Store(['is_b2b' => false, 'theme' => 'ciak', 'name' => 'CIAK']);
        $input = $this->input($store, collect([
            ['label' => 'Agenda', 'slug' => 'agenda'],
            ['label' => 'Taccuini e quaderni', 'slug' => 'taccuini-e-quaderni'],
        ]));

        $data = app(HomePageViewDataBuilder::class)->build($input);

        $this->assertArrayHasKey('heroMedia', $data);
        $this->assertArrayHasKey('formatGroups', $data);
        $this->assertArrayHasKey('featuredRows', $data);
        $this->assertCount(2, $data['formatGroups']);
    }

    public function test_it_selects_the_fipell_presenter_for_the_fipell_b2b_theme(): void
    {
        $store = new Store(['is_b2b' => true, 'theme' => 'fipell', 'name' => 'Fipell']);

        $data = app(HomePageViewDataBuilder::class)->build($this->input($store));

        $this->assertArrayHasKey('heroCards', $data);
        $this->assertArrayHasKey('featuredCards', $data);
        $this->assertArrayHasKey('quickOrderEnabled', $data);
    }

    public function test_it_prepares_intempo_b2c_home_data_outside_the_blade(): void
    {
        $store = new Store(['is_b2b' => false, 'theme' => 'intemposhop', 'name' => 'Intempo']);
        $input = $this->input($store, collect([
            ['label' => 'Agende', 'slug' => 'agende'],
            ['label' => 'Pelletteria lifestyle', 'slug' => 'pelletteria-lifestyle'],
            ['label' => 'Home office', 'slug' => 'home-office'],
        ]));

        $data = app(HomePageViewDataBuilder::class)->build($input);

        $this->assertArrayHasKey('catalogueUrl', $data);
        $this->assertArrayHasKey('locatorUrl', $data);
        $this->assertArrayHasKey('intempoAreas', $data);
        $this->assertArrayHasKey('storyTitle', $data);
        $this->assertCount(3, $data['intempoAreas']);
    }

    public function test_it_selects_the_listing_presenter_for_intempo_distribution(): void
    {
        $store = new Store(['is_b2b' => true, 'theme' => 'intempodistribution', 'name' => 'InTempo']);
        $request = Request::create('/it', 'GET', ['grid' => '3']);

        $data = app(HomePageViewDataBuilder::class)->build($this->input($store, request: $request));

        $this->assertSame(3, $data['grid']);
        $this->assertSame('col-12 col-md-6 col-xl-4', $data['productColClass']);
        $this->assertArrayHasKey('homeProductRows', $data);
    }

    private function input(
        Store $store,
        ?Collection $categories = null,
        ?Request $request = null,
    ): HomePageInput {
        return new HomePageInput(
            store: $store,
            locale: 'it',
            request: $request ?? Request::create('/it'),
            products: new LengthAwarePaginator([], 0, 72),
            listingCardsByProductSku: collect(),
            filterFacets: collect(),
            activeFilters: [],
            currentSort: 'default',
            rootCategories: $categories ?? collect(),
            storefrontPage: null,
            storefrontPageBlocks: collect(),
        );
    }
}
