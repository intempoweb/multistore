<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\StoreLocator\GoogleMapsGeocodingService;
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
        private GoogleMapsGeocodingService $geocoding,
    ) {}

    public function index(Request $request): View
    {
        $store = $this->storefrontContext->store();

        abort_if($store->isB2B(), 404);

        $search = trim((string) $request->query('q', ''));
        $selectedSku = trim((string) $request->query('sku', ''));

        $product = $this->resolveProduct($selectedSku);

        $productRequested = $selectedSku !== '';
        $productResolved = !$productRequested || $product instanceof Product;

        $latitude = $this->latitude(
            $request->query('lat')
        );

        $longitude = $this->longitude(
            $request->query('lng')
        );

        $geocodingResult = null;

        /*
        |--------------------------------------------------------------------------
        | Ricerca geografica
        |--------------------------------------------------------------------------
        |
        | Una località esplicitamente inserita dall'utente ha sempre precedenza
        | sulle eventuali coordinate lat/lng presenti nella query string.
        |
        | Questo evita che una precedente geolocalizzazione browser interferisca
        | con una nuova ricerca testuale.
        |
        | Esempio:
        |
        | ?lat=43.7&lng=11.2&q=Lecce
        |
        | deve cercare Lecce, non utilizzare le vecchie coordinate.
        |
        */

        if (mb_strlen($search) >= 2) {
            $latitude = null;
            $longitude = null;

            $geocodingResult = $this->geocoding->geocode(
                $search
            );

            if ($geocodingResult['ok'] ?? false) {
                $latitude = $this->latitude(
                    $geocodingResult['latitude'] ?? null
                );

                $longitude = $this->longitude(
                    $geocodingResult['longitude'] ?? null
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Validità della ricerca
        |--------------------------------------------------------------------------
        |
        | Non interroghiamo il repository quando:
        |
        | - è stato indicato uno SKU che non esiste;
        | - è stata indicata una località che Google non riesce a risolvere.
        |
        | In entrambi i casi mostriamo zero risultati invece di effettuare un
        | fallback all'elenco completo dei punti vendita.
        |
        */

        $locationRequested = mb_strlen($search) >= 2;

        $locationResolved = !$locationRequested
            || ($latitude !== null && $longitude !== null);

        if (
            !$productResolved
            || !$locationResolved
        ) {
            $items = collect();
        } else {
            $items = $this->locations->locations(
                store: $store,
                product: $product,
                latitude: $latitude,
                longitude: $longitude,
                limit: 120,
            );
        }

        return view(
            $this->themeResolver->view(
                'store-locator.index',
                $store
            ),
            [
                'store' => $store,
                'storefrontLayout' => $this->themeResolver->layout(
                    $store
                ),
                'locale' => $this->storefrontContext->locale(),

                'locations' => $items,

                'selectedProduct' => $product,
                'selectedSku' => $selectedSku,
                'productResolved' => $productResolved,

                'searchQuery' => $search,

                'userLatitude' => $latitude,
                'userLongitude' => $longitude,

                'geocodingResult' => $geocodingResult,

                'googleMapsApiKey' => config(
                    'services.google_maps.api_key'
                ),
            ]
        );
    }

    public function locations(Request $request): JsonResponse
    {
        $store = $this->storefrontContext->store();

        abort_if($store->isB2B(), 404);

        $search = trim((string) $request->query('q', ''));
        $selectedSku = trim((string) $request->query('sku', ''));

        $product = $this->resolveProduct($selectedSku);

        $productRequested = $selectedSku !== '';
        $productResolved = !$productRequested || $product instanceof Product;

        $latitude = $this->latitude(
            $request->query('lat')
        );

        $longitude = $this->longitude(
            $request->query('lng')
        );

        $geocodingResult = null;

        /*
        |--------------------------------------------------------------------------
        | Località esplicita
        |--------------------------------------------------------------------------
        |
        | Se "q" contiene una località valida come lunghezza, le coordinate
        | eventualmente ricevute dal browser vengono ignorate e sostituite dal
        | risultato del geocoding.
        |
        */

        if (mb_strlen($search) >= 2) {
            $latitude = null;
            $longitude = null;

            $geocodingResult = $this->geocoding->geocode(
                $search
            );

            if ($geocodingResult['ok'] ?? false) {
                $latitude = $this->latitude(
                    $geocodingResult['latitude'] ?? null
                );

                $longitude = $this->longitude(
                    $geocodingResult['longitude'] ?? null
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SKU non valido
        |--------------------------------------------------------------------------
        |
        | Uno SKU richiesto ma non risolto non equivale all'assenza del filtro
        | prodotto. Restituiamo zero risultati e segnaliamo esplicitamente che
        | il prodotto non è stato trovato.
        |
        */

        if (!$productResolved) {
            return response()->json([
                'items' => [],
                'count' => 0,

                'product' => [
                    'sku' => $selectedSku,
                    'requested' => true,
                    'resolved' => false,
                ],

                'search' => [
                    'query' => $search,
                    'resolved' => $search === '',
                    'latitude' => null,
                    'longitude' => null,
                    'status' => null,
                    'formatted_address' => null,
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Località non risolta
        |--------------------------------------------------------------------------
        |
        | Se l'utente ha inserito una località ma Google non riesce a risolverla,
        | non facciamo fallback alla vecchia ricerca testuale tramite LIKE.
        |
        */

        if (
            mb_strlen($search) >= 2
            && ($latitude === null || $longitude === null)
        ) {
            return response()->json([
                'items' => [],
                'count' => 0,

                'product' => [
                    'sku' => $selectedSku !== ''
                        ? $selectedSku
                        : null,
                    'requested' => $productRequested,
                    'resolved' => true,
                ],

                'search' => [
                    'query' => $search,
                    'resolved' => false,
                    'latitude' => null,
                    'longitude' => null,
                    'status' => $geocodingResult['status']
                        ?? 'not_resolved',
                    'formatted_address' => null,
                ],
            ]);
        }

        $items = $this->locations->locations(
            store: $store,
            product: $product,
            latitude: $latitude,
            longitude: $longitude,
            limit: (int) $request->integer(
                'limit',
                120
            ),
        );

        return response()->json([
            'items' => $items->values(),
            'count' => $items->count(),

            'product' => [
                'sku' => $selectedSku !== ''
                    ? $selectedSku
                    : null,
                'requested' => $productRequested,
                'resolved' => true,
            ],

            'search' => [
                'query' => $search,
                'resolved' => $search === ''
                    || (
                        $latitude !== null
                        && $longitude !== null
                    ),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'status' => $geocodingResult['status']
                    ?? null,
                'formatted_address' => $geocodingResult['formatted_address']
                    ?? null,
            ],
        ]);
    }

    private function resolveProduct(string $sku): ?Product
    {
        $sku = trim($sku);

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

        return $product instanceof Product
            ? $product
            : null;
    }

    private function latitude(mixed $value): ?float
    {
        $coordinate = $this->coordinate(
            $value
        );

        if (
            $coordinate === null
            || $coordinate < -90
            || $coordinate > 90
        ) {
            return null;
        }

        return $coordinate;
    }

    private function longitude(mixed $value): ?float
    {
        $coordinate = $this->coordinate(
            $value
        );

        if (
            $coordinate === null
            || $coordinate < -180
            || $coordinate > 180
        ) {
            return null;
        }

        return $coordinate;
    }

    private function coordinate(mixed $value): ?float
    {
        if (
            $value === null
            || $value === ''
            || !is_numeric($value)
        ) {
            return null;
        }

        $coordinate = (float) $value;

        return is_finite($coordinate)
            ? $coordinate
            : null;
    }
}