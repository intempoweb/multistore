@extends($storefrontLayout)

@section('title', __('themes_b2c.store_locator.title') . ' - ' . ($store->name ?? 'Store'))

@section('content')
@php
    $locationsJson = $locations->values();
    $hasMap = filled($googleMapsApiKey);
    $productName = $selectedProduct?->translationOrFallback($locale)?->name ?? $selectedProduct?->sku;
    $resultCount = $locations->count();
    $storeLocatorPayload = [
        'locations' => $locationsJson,
        'i18n' => [
            'defaultStoreName' => __('themes_b2c.store_locator.default_store_name'),
            'yourPosition' => __('themes_b2c.store_locator.your_position'),
            'call' => __('themes_b2c.store_locator.call'),
            'email' => __('themes_b2c.store_locator.email'),
            'website' => __('themes_b2c.store_locator.website'),
            'directions' => __('themes_b2c.store_locator.directions'),
        ],
    ];
@endphp

<div class="store-locator-page bg-white">
    <section class="container py-5 py-lg-6">
        <div class="store-locator-hero mb-4 mb-lg-5">
            <div class="row g-4 align-items-end">
                <div class="col-12 col-lg-8">
                    <div class="d-inline-flex align-items-center gap-2 rounded-pill border px-3 py-2 small text-muted mb-3">
                        <span class="storefront-dot-danger rounded-circle bg-danger"></span>
                        {{ __('themes_b2c.store_locator.title') }}
                    </div>

                    <h1 class="display-5 fw-semibold letter-spacing-tight mb-3">
                        {{ __('themes_b2c.store_locator.hero_title') }}
                    </h1>

                    <p class="storefront-store-locator-intro lead text-muted mb-0">
                        @if($selectedProduct)
                            {!! __('themes_b2c.store_locator.product_intro', ['product' => '<span class="text-body fw-semibold">'.e($productName).'</span>']) !!}
                        @else
                            {{ __('themes_b2c.store_locator.generic_intro') }}
                        @endif
                    </p>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                        <button type="button" class="btn btn-dark rounded-pill px-4" data-store-locator-geolocate>
                            <i class="fa-solid fa-location-crosshairs me-2"></i>
                            {{ __('themes_b2c.store_locator.use_position') }}
                        </button>

                        @if($selectedProduct)
                            <a href="{{ route('storefront.store-locator.index') }}" class="btn btn-outline-dark rounded-pill px-4">
                                {{ __('themes_b2c.store_locator.view_all') }}
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

                        <script type="application/json" data-store-locator-payload>
                            @json($storeLocatorPayload)
                        </script>

                        @unless($hasMap)
                            <div class="h-100 d-flex align-items-center justify-content-center p-5 text-center text-muted">
                                <div>
                                    <div class="storefront-icon-56 rounded-circle bg-white border d-inline-flex align-items-center justify-content-center mb-3">
                                        <i class="fa-regular fa-map text-muted"></i>
                                    </div>
                                    <p class="mb-0">{{ __('themes_b2c.store_locator.google_maps_missing') }}</p>
                                    <small>{{ __('themes_b2c.store_locator.list_still_available') }}</small>
                                </div>
                            </div>
                        @endunless
                    </div>
                </div>

                <div class="col-12 col-xl-4">
                    <aside class="store-locator-panel bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center gap-3 border-bottom p-3 p-md-4">
                            <div>
                                <div class="small text-muted mb-1">{{ __('themes_b2c.store_locator.results') }}</div>
                                <h2 class="h5 fw-semibold mb-0">
                                    {{ $resultCount }} {{ $resultCount === 1 ? __('themes_b2c.store_locator.store_singular') : __('themes_b2c.store_locator.store_plural') }}
                                </h2>
                                @if($selectedProduct)
                                    <div class="small text-muted mt-1 text-truncate">
                                        {{ $productName }}
                                    </div>
                                @endif
                            </div>

                            @if($userLatitude !== null && $userLongitude !== null)
                                <span class="badge rounded-pill text-bg-light border px-3 py-2">{{ __('themes_b2c.store_locator.by_distance') }}</span>
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
                                                        {{ __('themes_b2c.store_locator.call') }}
                                                    </a>
                                                @endif

                                                @if(filled($location['email']))
                                                    <a class="btn btn-sm btn-light border rounded-pill px-3" href="mailto:{{ $location['email'] }}">
                                                        {{ __('themes_b2c.store_locator.email') }}
                                                    </a>
                                                @endif

                                                @if($location['latitude'] !== null && $location['longitude'] !== null)
                                                    <a
                                                        class="btn btn-sm btn-outline-dark rounded-pill px-3"
                                                        href="https://www.google.com/maps/dir/?api=1&destination={{ $location['latitude'] }},{{ $location['longitude'] }}"
                                                        target="_blank"
                                                        rel="noopener"
                                                    >
                                                        {{ __('themes_b2c.store_locator.directions') }}
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <div class="p-4 p-md-5 text-center text-muted">
                                    <div class="storefront-icon-56 rounded-circle bg-light border d-inline-flex align-items-center justify-content-center mb-3">
                                        <i class="fa-regular fa-face-frown"></i>
                                    </div>
                                    <h2 class="h6 fw-semibold text-body mb-2">{{ __('themes_b2c.store_locator.empty_title') }}</h2>
                                    <p class="mb-0 small">{{ __('themes_b2c.store_locator.empty_text') }}</p>
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
    <link rel="stylesheet" href="{{ asset('css/store-locator.css') }}">
@endpush
@push('scripts')
    <script defer src="{{ asset('js/store-locator.js') }}?v={{ @filemtime(public_path('js/store-locator.js')) ?: time() }}"></script>

    @if($hasMap)
        <script defer src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($googleMapsApiKey) }}&callback=initStoreLocatorMap"></script>
    @endif
@endpush
@endsection
