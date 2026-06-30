<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seo['title'] ?? trim($__env->yieldContent('title', $store?->name ?? 'INTEMPO')) }}</title>
    @include('storefront.base.partials.seo')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/themes/b2c/intempob2c.css') }}" rel="stylesheet">
    @stack('styles')
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script src="{{ asset('js/search.js') }}" defer></script>
    <script src="{{ asset('js/themes/b2c/intempob2c.js') }}" defer></script>
    @stack('head-scripts')
</head>
@php
    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];
@endphp
<body
    class="intempo-b2c-site storefront-page"
    data-storefront-layout="b2c-intemposhop"
    data-storefront-site-type="b2c"
    data-minicart-url="{{ route('storefront.cart.mini', $contextParams) }}"
    data-search-url="{{ route('storefront.search.index', $contextParams) }}"
    data-search-suggest-url="{{ route('storefront.search.suggest', $contextParams) }}"
    data-suggest-url="{{ route('storefront.search.suggest', $contextParams) }}"
    data-cart-add-url="{{ route('storefront.cart.add', $contextParams) }}"
>
    @include('storefront.themes.b2c.intemposhop.partials.header', ['contextParams' => $contextParams, 'agentContextId' => $agentContextId])

    <main class="intempo-b2c-main storefront-main">
        @hasSection('fullwidth')<div class="container-fluid px-0">@yield('fullwidth')</div>@endif
        @includeIf('storefront.base.partials.alerts')
        @yield('content')
    </main>

    <div class="offcanvas offcanvas-end storefront-minicart-offcanvas intempo-b2c-minicart-offcanvas" tabindex="-1" id="storefrontMinicart" aria-labelledby="storefrontMinicartLabel">
        <div class="offcanvas-header">
            <h2 class="offcanvas-title h5" id="storefrontMinicartLabel">{{ __('Carrello') }}</h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('Chiudi') }}"></button>
        </div>
        <div class="offcanvas-body" data-minicart-container>
            <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>{{ __('Caricamento carrello...') }}</div>
        </div>
    </div>

    @include('storefront.themes.b2c.intemposhop.partials.footer', ['contextParams' => $contextParams, 'agentContextId' => $agentContextId])
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
    <script src="{{ asset('js/storefront-filters.js') }}" defer></script>
    <script src="{{ asset('js/product-card.js') }}" defer></script>
    <script>document.addEventListener('DOMContentLoaded', function () { window.lucide?.createIcons({ strokeWidth: 1.45 }); });</script>
    @stack('scripts')
</body>
</html>
