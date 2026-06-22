{{-- resources/views/storefront/themes/b2c/ciak/layout.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $seo['title'] ?? trim($__env->yieldContent('title', $store?->name ?? 'CIAK')) }}</title>
    @include('storefront.base.partials.seo')

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/themes/b2c/ciak.css') }}?v={{ @filemtime(public_path('css/themes/b2c/ciak.css')) ?: 1 }}" rel="stylesheet">

    @stack('styles')

    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('js/search.js') }}" defer></script>

    @stack('head-scripts')
</head>

@php
    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];
@endphp

<body
    class="ciak-storefront storefront-page"
    data-storefront-layout="b2c-ciak"
    data-storefront-site-type="b2c"
    data-minicart-url="{{ Route::has('storefront.cart.mini') ? route('storefront.cart.mini', $contextParams) : '' }}"
    data-search-url="{{ Route::has('storefront.search.index') ? route('storefront.search.index', $contextParams) : '' }}"
    data-search-suggest-url="{{ Route::has('storefront.search.suggest') ? route('storefront.search.suggest', $contextParams) : '' }}"
    data-suggest-url="{{ Route::has('storefront.search.suggest') ? route('storefront.search.suggest', $contextParams) : '' }}"
    data-cart-add-url="{{ Route::has('storefront.cart.add') ? route('storefront.cart.add', $contextParams) : '' }}"
>

    @includeFirst([
        'storefront.themes.b2c.ciak.partials.header',
        'storefront.base.partials.header',
    ], ['contextParams' => $contextParams, 'agentContextId' => $agentContextId])

    <main class="ciak-main storefront-main">
        @hasSection('fullwidth')
            <div class="container-fluid px-0 ciak-fullwidth-content">
                @yield('fullwidth')
            </div>
        @endif
        <div class="container-fluid ciak-page-container">
            @includeFirst([
                'storefront.themes.b2c.ciak.partials.alerts',
                'storefront.base.partials.alerts',
            ])

            @yield('content')
        </div>
    </main>

    <div
        class="offcanvas offcanvas-end storefront-minicart-offcanvas ciak-minicart-offcanvas"
        tabindex="-1"
        id="storefrontMinicart"
        aria-labelledby="storefrontMinicartLabel"
    >
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="storefrontMinicartLabel">
                Carrello
            </h5>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="offcanvas"
                aria-label="Chiudi"
            ></button>
        </div>

        <div class="offcanvas-body" data-minicart-container>
            <div class="text-center text-muted py-4">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                Caricamento carrello...
            </div>
        </div>
    </div>

    @includeFirst([
        'storefront.themes.b2c.ciak.partials.footer',
        'storefront.base.partials.footer',
    ], ['contextParams' => $contextParams, 'agentContextId' => $agentContextId])

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/storefront-filters.js') }}" defer></script>
    <script src="{{ asset('js/product-card.js') }}" defer></script>

    @stack('scripts')
</body>
</html>
