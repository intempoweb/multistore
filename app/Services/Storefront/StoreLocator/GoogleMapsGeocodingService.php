<?php

namespace App\Services\Storefront\StoreLocator;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleMapsGeocodingService
{
    public function geocode(string $address): array
    {
        $address = trim($address);

        if ($address === '') {
            return $this->failure(
                status: 'empty_address',
                error: 'Indirizzo non valorizzato.'
            );
        }

        if (
            !filter_var(
                config('services.google_maps.geocoding_enabled', true),
                FILTER_VALIDATE_BOOLEAN
            )
        ) {
            return $this->failure(
                status: 'disabled',
                error: 'Geocoding Google Maps disabilitato.'
            );
        }

        $apiKey = trim(
            (string) config(
                'services.google_maps.geocoding_api_key',
                ''
            )
        );

        if ($apiKey === '') {
            return $this->failure(
                status: 'missing_api_key',
                error: 'Chiave Google Maps non configurata.'
            );
        }

        try {
            $params = [
                'address' => $address,
                'key' => $apiKey,
                'language' => config(
                    'services.google_maps.geocoding_language',
                    'it'
                ),
            ];

            /*
            |--------------------------------------------------------------------------
            | Restrizione paese opzionale
            |--------------------------------------------------------------------------
            |
            | Se GOOGLE_MAPS_GEOCODING_COUNTRY è vuoto non viene applicata
            | alcuna restrizione geografica e il geocoding può funzionare
            | worldwide.
            |
            */

            $country = trim(
                (string) config(
                    'services.google_maps.geocoding_country',
                    ''
                )
            );

            if ($country !== '') {
                $params['components'] = 'country:' . $country;
            }

            $response = Http::timeout(12)
                ->get(
                    'https://maps.googleapis.com/maps/api/geocode/json',
                    $params
                );

            if (!$response->successful()) {
                return $this->failure(
                    status: 'http_error',
                    error: 'Errore HTTP Google Maps: ' . $response->status()
                );
            }

            $payload = $response->json();

            $status = strtoupper(
                trim(
                    (string) data_get(
                        $payload,
                        'status',
                        'UNKNOWN'
                    )
                )
            );

            if ($status !== 'OK') {
                return $this->failure(
                    status: strtolower($status),
                    error: (string) (
                        data_get($payload, 'error_message')
                        ?: 'Geocoding non riuscito: ' . $status
                    )
                );
            }

            $result = data_get($payload, 'results.0');

            if (!is_array($result)) {
                return $this->failure(
                    status: 'missing_result',
                    error: 'Risultato assente nella risposta Google Maps.'
                );
            }

            $lat = data_get(
                $result,
                'geometry.location.lat'
            );

            $lng = data_get(
                $result,
                'geometry.location.lng'
            );

            if (!is_numeric($lat) || !is_numeric($lng)) {
                return $this->failure(
                    status: 'missing_coordinates',
                    error: 'Coordinate assenti nella risposta Google Maps.'
                );
            }

            $formattedAddress = trim(
                (string) data_get(
                    $result,
                    'formatted_address',
                    ''
                )
            );

            $placeId = trim(
                (string) data_get(
                    $result,
                    'place_id',
                    ''
                )
            );

            return [
                'ok' => true,
                'status' => 'ok',
                'error' => null,

                'query' => $address,

                'latitude' => round(
                    (float) $lat,
                    7
                ),

                'longitude' => round(
                    (float) $lng,
                    7
                ),

                'formatted_address' => $formattedAddress !== ''
                    ? $formattedAddress
                    : null,

                'place_id' => $placeId !== ''
                    ? $placeId
                    : null,
            ];
        } catch (Throwable $e) {
            Log::warning(
                'Google Maps geocoding failed',
                [
                    'address' => $address,
                    'message' => $e->getMessage(),
                ]
            );

            return $this->failure(
                status: 'exception',
                error: mb_substr(
                    $e->getMessage(),
                    0,
                    500
                ),
                query: $address
            );
        }
    }

    private function failure(
        string $status,
        string $error,
        ?string $query = null
    ): array {
        return [
            'ok' => false,
            'status' => $status,
            'error' => $error,
            'query' => $query,
            'latitude' => null,
            'longitude' => null,
            'formatted_address' => null,
            'place_id' => null,
        ];
    }
}