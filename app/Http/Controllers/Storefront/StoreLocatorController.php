<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\StoreLocator\StoreLocatorRepository;
use App\Services\Storefront\StorefrontContext;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreLocatorController extends Controller
{
    public function __construct(
        private ThemeResolver $themeResolver,
        private StorefrontContext $storefrontContext,
        private CatalogRepository $catalogRepository,
        private StoreLocatorRepository $locations,
    ) {}

    public function index(Request $request): View
    {
        $store = $this->storefrontContext->store();
        abort_if($store->is_b2b, 404);

        $product = $this->resolveProduct($request);
        $latitude = $this->coordinate($request->query('lat'));
        $longitude = $this->coordinate($request->query('lng'));
        $items = $this->locations->locations($store, $product, $latitude, $longitude, 120);

        return view($this->themeResolver->view('store-locator.index', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'locale' => $this->storefrontContext->locale(),
            'locations' => $items,
            'selectedProduct' => $product,
            'selectedSku' => trim((string) $request->query('sku', '')),
            'userLatitude' => $latitude,
            'userLongitude' => $longitude,
            'googleMapsApiKey' => config('services.google_maps.api_key'),
        ]);
    }

    public function locations(Request $request): JsonResponse
    {
        $store = $this->storefrontContext->store();
        abort_if($store->is_b2b, 404);

        $product = $this->resolveProduct($request);
        $items = $this->locations->locations(
            store: $store,
            product: $product,
            latitude: $this->coordinate($request->query('lat')),
            longitude: $this->coordinate($request->query('lng')),
            limit: (int) $request->integer('limit', 120),
        );

        return response()->json([
            'items' => $items->values(),
            'count' => $items->count(),
        ]);
    }

    private function resolveProduct(Request $request): ?Product
    {
        $sku = trim((string) $request->query('sku', ''));

        if ($sku === '') {
            return null;
        }

        $store = $this->storefrontContext->store();
        $product = $this->catalogRepository->getProductBySku(
            $store,
            $this->storefrontContext->locale(),
            $sku,
            null,
            null
        );

        return $product instanceof Product ? $product : null;
    }

    private function coordinate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
