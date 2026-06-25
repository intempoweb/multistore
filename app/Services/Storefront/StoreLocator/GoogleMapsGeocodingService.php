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
            return [
                'ok' => false,
                'status' => 'empty_address',
                'error' => 'Indirizzo non valorizzato.',
                'latitude' => null,
                'longitude' => null,
            ];
        }

        if (!filter_var(config('services.google_maps.geocoding_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'ok' => false,
                'status' => 'disabled',
                'error' => 'Geocoding Google Maps disabilitato.',
                'latitude' => null,
                'longitude' => null,
            ];
        }

        $apiKey = trim((string) config('services.google_maps.geocoding_api_key', ''));

        if ($apiKey === '') {
            return [
                'ok' => false,
                'status' => 'missing_api_key',
                'error' => 'Chiave Google Maps non configurata.',
                'latitude' => null,
                'longitude' => null,
            ];
        }

        try {
            $params = [
                'address' => $address,
                'key' => $apiKey,
                'language' => config('services.google_maps.geocoding_language', 'it'),
            ];

            $country = trim((string) config('services.google_maps.geocoding_country', ''));

            if ($country !== '') {
                $params['components'] = 'country:' . $country;
            }

            $response = Http::timeout(12)->get('https://maps.googleapis.com/maps/api/geocode/json', $params);

            if (!$response->successful()) {
                return [
                    'ok' => false,
                    'status' => 'http_error',
                    'error' => 'Errore HTTP Google Maps: ' . $response->status(),
                    'latitude' => null,
                    'longitude' => null,
                ];
            }

            $payload = $response->json();
            $status = (string) data_get($payload, 'status', 'UNKNOWN');

            if ($status !== 'OK') {
                return [
                    'ok' => false,
                    'status' => strtolower($status),
                    'error' => data_get($payload, 'error_message') ?: 'Geocoding non riuscito: ' . $status,
                    'latitude' => null,
                    'longitude' => null,
                ];
            }

            $location = data_get($payload, 'results.0.geometry.location');
            $lat = data_get($location, 'lat');
            $lng = data_get($location, 'lng');

            if (!is_numeric($lat) || !is_numeric($lng)) {
                return [
                    'ok' => false,
                    'status' => 'missing_coordinates',
                    'error' => 'Coordinate assenti nella risposta Google Maps.',
                    'latitude' => null,
                    'longitude' => null,
                ];
            }

            return [
                'ok' => true,
                'status' => 'ok',
                'error' => null,
                'latitude' => round((float) $lat, 7),
                'longitude' => round((float) $lng, 7),
            ];
        } catch (Throwable $e) {
            Log::warning('Google Maps geocoding failed', [
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => 'exception',
                'error' => mb_substr($e->getMessage(), 0, 500),
                'latitude' => null,
                'longitude' => null,
            ];
        }
    }
}
