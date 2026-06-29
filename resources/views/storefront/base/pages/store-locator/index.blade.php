@extends($storefrontLayout)

@section('title', 'Punti vendita - ' . ($store->name ?? 'Store'))

@section('content')
@php
    $locationsJson = $locations->values();
    $hasMap = filled($googleMapsApiKey);
    $productName = $selectedProduct?->translationOrFallback($locale)?->name ?? $selectedProduct?->sku;
    $resultCount = $locations->count();
@endphp

<div class="store-locator-page bg-white">
    <section class="container py-5 py-lg-6">
        <div class="store-locator-hero mb-4 mb-lg-5">
            <div class="row g-4 align-items-end">
                <div class="col-12 col-lg-8">
                    <div class="d-inline-flex align-items-center gap-2 rounded-pill border px-3 py-2 small text-muted mb-3">
                        <span class="rounded-circle bg-danger" style="width:7px;height:7px;"></span>
                        Punti vendita
                    </div>

                    <h1 class="display-5 fw-semibold letter-spacing-tight mb-3">
                        Trova il negozio più vicino
                    </h1>

                    <p class="lead text-muted mb-0" style="max-width:760px;">
                        @if($selectedProduct)
                            Rivenditori che potrebbero trattare <span class="text-body fw-semibold">{{ $productName }}</span>.
                        @else
                            Consulta i rivenditori disponibili per questo store.
                        @endif
                    </p>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                        <button type="button" class="btn btn-dark rounded-pill px-4" data-store-locator-geolocate>
                            <i class="fa-solid fa-location-crosshairs me-2"></i>
                            Usa posizione
                        </button>

                        @if($selectedProduct)
                            <a href="{{ route('storefront.store-locator.index') }}" class="btn btn-outline-dark rounded-pill px-4">
                                Vedi tutti
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="store-locator-shell border rounded-4 overflow-hidden bg-light-subtle shadow-sm">
            <div class="row g-0">
                <div class="col-12 col-xl-8">
                    <div class="store-locator-map-wrap position-relative h-100">
                        <div
                            class="store-locator-map h-100 bg-light"
                            data-store-locator-map
                            @if(!$hasMap) hidden @endif
                        ></div>

                        @unless($hasMap)
                            <div class="h-100 d-flex align-items-center justify-content-center p-5 text-center text-muted">
                                <div>
                                    <div class="rounded-circle bg-white border d-inline-flex align-items-center justify-content-center mb-3" style="width:56px;height:56px;">
                                        <i class="fa-regular fa-map text-muted"></i>
                                    </div>
                                    <p class="mb-0">Configura Google Maps per visualizzare la mappa.</p>
                                    <small>La lista dei punti vendita resta disponibile.</small>
                                </div>
                            </div>
                        @endunless
                    </div>
                </div>

                <div class="col-12 col-xl-4">
                    <aside class="store-locator-panel bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center gap-3 border-bottom p-3 p-md-4">
                            <div>
                                <div class="small text-muted mb-1">Risultati</div>
                                <h2 class="h5 fw-semibold mb-0">
                                    {{ $resultCount }} {{ $resultCount === 1 ? 'negozio' : 'negozi' }}
                                </h2>
                                @if($selectedProduct)
                                    <div class="small text-muted mt-1 text-truncate">
                                        {{ $productName }}
                                    </div>
                                @endif
                            </div>

                            @if($userLatitude !== null && $userLongitude !== null)
                                <span class="badge rounded-pill text-bg-light border px-3 py-2">per distanza</span>
                            @endif
                        </div>

                        <div class="store-locator-list" data-store-locator-list>
                            @forelse($locations as $location)
                                <article class="store-locator-card border-bottom p-3 p-md-4" data-store-locator-card data-location-id="{{ $location['id'] }}">
                                    <div class="d-flex gap-3">
                                        <div class="store-locator-pin flex-shrink-0 rounded-circle bg-dark text-white d-flex align-items-center justify-content-center">
                                            <i class="fa-solid fa-location-dot"></i>
                                        </div>

                                        <div class="min-w-0 flex-grow-1">
                                            <div class="d-flex justify-content-between gap-3 mb-1">
                                                <h3 class="h6 fw-semibold mb-0 text-truncate">{{ $location['name'] }}</h3>

                                                @if($location['distance_km'] !== null)
                                                    <div class="small fw-semibold text-nowrap text-muted">
                                                        {{ number_format((float) $location['distance_km'], 1, ',', '.') }} km
                                                    </div>
                                                @endif
                                            </div>

                                            <p class="small text-muted mb-3">{{ $location['address_line'] }}</p>

                                            <div class="d-flex flex-wrap gap-2">
                                                @if(filled($location['phone']))
                                                    <a class="btn btn-sm btn-light border rounded-pill px-3" href="tel:{{ preg_replace('/\s+/', '', $location['phone']) }}">
                                                        Chiama
                                                    </a>
                                                @endif

                                                @if(filled($location['email']))
                                                    <a class="btn btn-sm btn-light border rounded-pill px-3" href="mailto:{{ $location['email'] }}">
                                                        Email
                                                    </a>
                                                @endif

                                                @if($location['latitude'] !== null && $location['longitude'] !== null)
                                                    <a
                                                        class="btn btn-sm btn-outline-dark rounded-pill px-3"
                                                        href="https://www.google.com/maps/dir/?api=1&destination={{ $location['latitude'] }},{{ $location['longitude'] }}"
                                                        target="_blank"
                                                        rel="noopener"
                                                    >
                                                        Indicazioni
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <div class="p-4 p-md-5 text-center text-muted">
                                    <div class="rounded-circle bg-light border d-inline-flex align-items-center justify-content-center mb-3" style="width:56px;height:56px;">
                                        <i class="fa-regular fa-face-frown"></i>
                                    </div>
                                    <h2 class="h6 fw-semibold text-body mb-2">Nessun punto vendita disponibile</h2>
                                    <p class="mb-0 small">Non ci sono punti vendita geocodificati per questa ricerca.</p>
                                </div>
                            @endforelse
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </section>
</div>

@push('styles')
    <style>
        .store-locator-page {
            --store-locator-height: min(66vh, 620px);
            --store-locator-min-height: 520px;
        }

        .store-locator-shell {
            height: var(--store-locator-height);
            min-height: var(--store-locator-min-height);
        }

        .store-locator-shell > .row {
            height: 100%;
        }

        .store-locator-map-wrap,
        .store-locator-map {
            height: 100%;
            min-height: 0;
        }

        .store-locator-map {
            position: relative;
        }

        .store-locator-panel {
            height: 100%;
            max-height: 100%;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .store-locator-panel > .border-bottom {
            flex: 0 0 auto;
        }

        .store-locator-list {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .store-locator-card {
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease;
        }

        .store-locator-card:hover {
            background: #fafafa;
        }

        .store-locator-card.active {
            background: #f5f5f5;
            border-left: 4px solid #212529;
        }

        .store-locator-card h3 {
            font-size: .9rem;
            line-height: 1.2;
        }

        .store-locator-card p {
            font-size: .78rem;
            line-height: 1.25;
        }

        .store-locator-pin {
            width: 30px;
            height: 30px;
            font-size: 12px;
        }

        .letter-spacing-tight {
            letter-spacing: -.04em;
        }

        .min-w-0 {
            min-width: 0;
        }

        @media (min-width: 1400px) {
            .store-locator-page {
                --store-locator-height: 640px;
                --store-locator-min-height: 640px;
            }
        }

        @media (max-width: 1199.98px) {
            .store-locator-shell {
                height: auto;
                min-height: 0;
            }

            .store-locator-shell > .row {
                height: auto;
            }

            .store-locator-map-wrap,
            .store-locator-map {
                height: 420px;
                min-height: 420px;
            }

            .store-locator-panel {
                height: auto;
                max-height: none;
            }

            .store-locator-list {
                max-height: none;
                overflow-y: visible;
            }
        }
    </style>
@endpush

@if($hasMap)
    @push('scripts')
        <script>
            window.storeLocatorData = @json($locationsJson);

            window.initStoreLocatorMap = function () {
                const mapElement = document.querySelector('[data-store-locator-map]');
                const locations = Array.isArray(window.storeLocatorData) ? window.storeLocatorData : [];

                if (!mapElement || !locations.length || !window.google) {
                    return;
                }

                const map = new google.maps.Map(mapElement, {
                    center: { lat: 43.7696, lng: 11.2558 },
                    zoom: 6,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: true,
                    clickableIcons: false,
                    styles: [
                        { featureType: 'poi', stylers: [{ visibility: 'off' }] },
                        { featureType: 'transit', stylers: [{ visibility: 'off' }] },
                        { featureType: 'road', elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
                    ],
                });

                const bounds = new google.maps.LatLngBounds();
                const infoWindow = new google.maps.InfoWindow();
                const markers = [];

                locations.forEach(function (location) {
                    if (location.latitude === null || location.longitude === null) {
                        return;
                    }

                    const position = {
                        lat: Number(location.latitude),
                        lng: Number(location.longitude),
                    };

                    const marker = new google.maps.Marker({
                        position,
                        map,
                        title: location.name || 'Punto vendita',
                    });

                    marker.addListener('click', function () {
                        infoWindow.setContent(
                            '<div style="min-width:220px">' +
                                '<strong>' + (location.name || 'Punto vendita') + '</strong><br>' +
                                '<span style="color:#6b7280">' + (location.address_line || '') + '</span>' +
                                (location.distance_km !== null ? '<br><small>' + location.distance_km + ' km</small>' : '') +
                            '</div>'
                        );
                        infoWindow.open({ anchor: marker, map });
                    });

                    markers.push({ marker, location });

                    bounds.extend(position);
                });

                // Bind cards to markers
                const cards = document.querySelectorAll('[data-store-locator-card]');
                cards.forEach(card => {
                    card.addEventListener('click', () => {
                        const locationId = card.getAttribute('data-location-id');
                        const markerObj = markers.find(m => String(m.location.id) === locationId);
                        if (!markerObj) return;

                        // Trigger marker click
                        google.maps.event.trigger(markerObj.marker, 'click');

                        // Center and zoom map
                        map.panTo(markerObj.marker.getPosition());
                        map.setZoom(13);

                        // Update active card styling
                        cards.forEach(c => c.classList.remove('active'));
                        card.classList.add('active');
                    });
                });

                if (!bounds.isEmpty()) {
                    map.fitBounds(bounds, 64);
                }
            };

            document.querySelectorAll('[data-store-locator-geolocate]').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (!navigator.geolocation) {
                        return;
                    }

                    button.disabled = true;
                    navigator.geolocation.getCurrentPosition(function (position) {
                        const url = new URL(window.location.href);
                        url.searchParams.set('lat', position.coords.latitude);
                        url.searchParams.set('lng', position.coords.longitude);
                        window.location.href = url.toString();
                    }, function () {
                        button.disabled = false;
                    }, {
                        enableHighAccuracy: true,
                        timeout: 9000,
                        maximumAge: 300000,
                    });
                });
            });
        </script>
        <script async defer src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($googleMapsApiKey) }}&callback=initStoreLocatorMap"></script>
    @endpush
@endif
@endsection
