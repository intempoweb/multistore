<?php

namespace Tests\Unit\Storefront;

use App\Http\Controllers\Storefront\StoreLocatorController;
use App\Models\Product;
use App\Models\Store;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\StoreLocator\GoogleMapsGeocodingService;
use App\Services\Storefront\StoreLocator\StoreLocatorRepository;
use App\Services\Storefront\StorefrontContext;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\TestCase;

class StoreLocatorControllerTest extends TestCase
{
    private Store $store;

    private ThemeResolver $themeResolver;

    private StorefrontContext $storefrontContext;

    private CatalogRepository $catalogRepository;

    private StoreLocatorRepository $storeLocatorRepository;

    private GoogleMapsGeocodingService $geocoding;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new Store([
            'ditta_cg18' => 1,
            'erp_site_code' => 1,
            'company_code' => 'INTEMPO',
            'site_code' => 'INTEMPO',
            'domain' => 'intempo.test',
            'name' => 'Intempo',
            'is_b2b' => false,
            'theme' => 'intempodistribution',
            'default_locale' => 'it',
            'supported_locales' => ['it', 'en'],
            'is_active' => true,
        ]);

        app()->instance('currentStore', $this->store);
        app()->setLocale('it');

        $this->themeResolver = $this->createMock(
            ThemeResolver::class
        );

        $this->storefrontContext = app(
            StorefrontContext::class
        );

        $this->catalogRepository = $this->createMock(
            CatalogRepository::class
        );

        $this->storeLocatorRepository = $this->createMock(
            StoreLocatorRepository::class
        );

        $this->geocoding = $this->createMock(
            GoogleMapsGeocodingService::class
        );
    }

    public function test_location_search_uses_geocoded_coordinates_instead_of_browser_coordinates(): void
    {
        $this->geocoding
            ->expects($this->once())
            ->method('geocode')
            ->with('Lecce')
            ->willReturn([
                'ok' => true,
                'status' => 'ok',
                'error' => null,
                'query' => 'Lecce',
                'latitude' => 40.3515255,
                'longitude' => 18.1749660,
                'formatted_address' => '73100 Lecce LE, Italia',
                'place_id' => 'lecce-test',
            ]);

        $this->storeLocatorRepository
            ->expects($this->once())
            ->method('locations')
            ->with(
                $this->identicalTo($this->store),
                null,
                40.3515255,
                18.1749660,
                5
            )
            ->willReturn(
                $this->sampleLocations()
            );

        $response = $this->controller()->locations(
            $this->request([
                'q' => 'Lecce',

                // Coordinate volutamente diverse:
                // devono essere ignorate perché q ha precedenza.
                'lat' => '43.7699685',
                'lng' => '11.2576706',

                'limit' => 5,
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame(1, $payload['count']);

        $this->assertSame(
            'Lecce',
            $payload['search']['query']
        );

        $this->assertTrue(
            $payload['search']['resolved']
        );

        $this->assertSame(
            40.3515255,
            $payload['search']['latitude']
        );

        $this->assertSame(
            18.174966,
            $payload['search']['longitude']
        );

        $this->assertSame(
            '73100 Lecce LE, Italia',
            $payload['search']['formatted_address']
        );
    }

    public function test_invalid_sku_returns_zero_results_without_querying_store_locator_repository(): void
    {
        $this->catalogRepository
            ->expects($this->once())
            ->method('getProductBySku')
            ->with(
                $this->identicalTo($this->store),
                'it',
                'SKU-CHE-NON-ESISTE',
                null,
                null
            )
            ->willReturn(null);

        $this->geocoding
            ->expects($this->never())
            ->method('geocode');

        $this->storeLocatorRepository
            ->expects($this->never())
            ->method('locations');

        $response = $this->controller()->locations(
            $this->request([
                'sku' => 'SKU-CHE-NON-ESISTE',
                'limit' => 5,
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame([], $payload['items']);
        $this->assertSame(0, $payload['count']);

        $this->assertSame(
            'SKU-CHE-NON-ESISTE',
            $payload['product']['sku']
        );

        $this->assertTrue(
            $payload['product']['requested']
        );

        $this->assertFalse(
            $payload['product']['resolved']
        );
    }

    public function test_valid_sku_without_location_filters_store_locator_by_product(): void
    {
        $product = $this->product(
            '9239DRG32'
        );

        $this->catalogRepository
            ->expects($this->once())
            ->method('getProductBySku')
            ->with(
                $this->identicalTo($this->store),
                'it',
                '9239DRG32',
                null,
                null
            )
            ->willReturn($product);

        $this->geocoding
            ->expects($this->never())
            ->method('geocode');

        $this->storeLocatorRepository
            ->expects($this->once())
            ->method('locations')
            ->with(
                $this->identicalTo($this->store),
                $this->identicalTo($product),
                null,
                null,
                5
            )
            ->willReturn(
                $this->sampleLocations()
            );

        $response = $this->controller()->locations(
            $this->request([
                'sku' => '9239DRG32',
                'limit' => 5,
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame(1, $payload['count']);

        $this->assertSame(
            '9239DRG32',
            $payload['product']['sku']
        );

        $this->assertTrue(
            $payload['product']['requested']
        );

        $this->assertTrue(
            $payload['product']['resolved']
        );

        $this->assertSame(
            '',
            $payload['search']['query']
        );

        $this->assertTrue(
            $payload['search']['resolved']
        );

        $this->assertNull(
            $payload['search']['latitude']
        );

        $this->assertNull(
            $payload['search']['longitude']
        );
    }

    public function test_location_and_valid_sku_filter_by_product_and_geographic_distance(): void
    {
        $product = $this->product(
            '9239DRG32'
        );

        $this->catalogRepository
            ->expects($this->once())
            ->method('getProductBySku')
            ->with(
                $this->identicalTo($this->store),
                'it',
                '9239DRG32',
                null,
                null
            )
            ->willReturn($product);

        $this->geocoding
            ->expects($this->once())
            ->method('geocode')
            ->with('Firenze')
            ->willReturn([
                'ok' => true,
                'status' => 'ok',
                'error' => null,
                'query' => 'Firenze',
                'latitude' => 43.7699685,
                'longitude' => 11.2576706,
                'formatted_address' => 'Firenze FI, Italia',
                'place_id' => 'firenze-test',
            ]);

        $this->storeLocatorRepository
            ->expects($this->once())
            ->method('locations')
            ->with(
                $this->identicalTo($this->store),
                $this->identicalTo($product),
                43.7699685,
                11.2576706,
                5
            )
            ->willReturn(
                $this->sampleLocations()
            );

        $response = $this->controller()->locations(
            $this->request([
                'q' => 'Firenze',
                'sku' => '9239DRG32',
                'limit' => 5,
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame(1, $payload['count']);

        $this->assertSame(
            '9239DRG32',
            $payload['product']['sku']
        );

        $this->assertTrue(
            $payload['product']['resolved']
        );

        $this->assertSame(
            'Firenze',
            $payload['search']['query']
        );

        $this->assertTrue(
            $payload['search']['resolved']
        );

        $this->assertSame(
            43.7699685,
            $payload['search']['latitude']
        );

        $this->assertSame(
            11.2576706,
            $payload['search']['longitude']
        );

        $this->assertSame(
            'Firenze FI, Italia',
            $payload['search']['formatted_address']
        );
    }

    public function test_unresolved_location_returns_zero_results_without_querying_repository(): void
    {
        $this->geocoding
            ->expects($this->once())
            ->method('geocode')
            ->with('Localita Che Non Esiste')
            ->willReturn([
                'ok' => false,
                'status' => 'zero_results',
                'error' => 'Geocoding non riuscito: ZERO_RESULTS',
                'query' => 'Localita Che Non Esiste',
                'latitude' => null,
                'longitude' => null,
                'formatted_address' => null,
                'place_id' => null,
            ]);

        $this->storeLocatorRepository
            ->expects($this->never())
            ->method('locations');

        $response = $this->controller()->locations(
            $this->request([
                'q' => 'Localita Che Non Esiste',
                'limit' => 5,
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame([], $payload['items']);
        $this->assertSame(0, $payload['count']);

        $this->assertFalse(
            $payload['search']['resolved']
        );

        $this->assertSame(
            'zero_results',
            $payload['search']['status']
        );

        $this->assertNull(
            $payload['search']['latitude']
        );

        $this->assertNull(
            $payload['search']['longitude']
        );
    }

    public function test_valid_browser_coordinates_are_used_when_no_location_query_is_present(): void
    {
        $this->geocoding
            ->expects($this->never())
            ->method('geocode');

        $this->storeLocatorRepository
            ->expects($this->once())
            ->method('locations')
            ->with(
                $this->identicalTo($this->store),
                null,
                43.7699685,
                11.2576706,
                5
            )
            ->willReturn(
                $this->sampleLocations()
            );

        $response = $this->controller()->locations(
            $this->request([
                'lat' => '43.7699685',
                'lng' => '11.2576706',
                'limit' => 5,
            ])
        );

        $payload = $response->getData(true);

        $this->assertSame(1, $payload['count']);

        $this->assertSame(
            43.7699685,
            $payload['search']['latitude']
        );

        $this->assertSame(
            11.2576706,
            $payload['search']['longitude']
        );

        $this->assertTrue(
            $payload['search']['resolved']
        );
    }

    public function test_invalid_browser_coordinates_are_discarded(): void
    {
        $this->geocoding
            ->expects($this->never())
            ->method('geocode');

        $this->storeLocatorRepository
            ->expects($this->once())
            ->method('locations')
            ->with(
                $this->identicalTo($this->store),
                null,
                null,
                null,
                5
            )
            ->willReturn(
                $this->sampleLocations()
            );

        $response = $this->controller()->locations(
            $this->request([
                'lat' => '1000',
                'lng' => '-500',
                'limit' => 5,
            ])
        );

        $payload = $response->getData(true);

        $this->assertNull(
            $payload['search']['latitude']
        );

        $this->assertNull(
            $payload['search']['longitude']
        );
    }

    private function controller(): StoreLocatorController
    {
        return new StoreLocatorController(
            $this->themeResolver,
            $this->storefrontContext,
            $this->catalogRepository,
            $this->storeLocatorRepository,
            $this->geocoding,
        );
    }

    private function request(array $query = []): Request
    {
        return Request::create(
            '/it/punti-vendita/locations',
            'GET',
            $query
        );
    }

    private function product(string $sku): Product
    {
        return new Product([
            'ditta_cg18' => 1,
            'site_type' => 1,
            'sku' => $sku,
            'type' => 'simple',
            'is_active' => true,
        ]);
    }

    private function sampleLocations(): Collection
    {
        return collect([
            [
                'id' => 1,
                'name' => 'Negozio Test',
                'address' => 'Via Test 1',
                'postcode' => '50100',
                'city' => 'FIRENZE',
                'province' => 'FI',
                'address_line' => 'Via Test 1, 50100 FIRENZE FI',
                'phone' => null,
                'email' => null,
                'website' => null,
                'latitude' => 43.7707167,
                'longitude' => 11.2536349,
                'distance_km' => 0.3,
            ],
        ]);
    }
}