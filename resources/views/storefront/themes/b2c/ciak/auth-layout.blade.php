<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,follow">
    <title>@yield('title', $store?->name ?? 'CIAK')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/themes/b2c/ciak.css') }}" rel="stylesheet">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body
    class="ciak-storefront ciak-auth-page"
    data-storefront-layout="b2c-ciak-auth"
    data-storefront-site-type="b2c"
    data-minicart-url="{{ route('storefront.cart.mini') }}"
>
    @include('storefront.themes.b2c.ciak.partials.header', ['cartCount' => 0])
    <main class="ciak-auth-main">
        @yield('content')
    </main>
    <div class="offcanvas offcanvas-end storefront-minicart-offcanvas ciak-minicart-offcanvas" tabindex="-1" id="storefrontMinicart" aria-labelledby="storefrontMinicartLabel">
        <div class="offcanvas-header">
            <h2 class="offcanvas-title h5" id="storefrontMinicartLabel">{{ __('Carrello') }}</h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('Chiudi') }}"></button>
        </div>
        <div class="offcanvas-body" data-minicart-container></div>
    </div>
    @includeFirst(['storefront.themes.b2c.ciak.partials.footer', 'storefront.base.partials.footer'])
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/auth.js') }}" defer></script>
    <script src="{{ asset('js/product-card.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
