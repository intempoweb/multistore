<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', $store?->name ?? 'B2C Store')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <script src="{{ asset('js/app.js') }}" defer></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

<body
    class="bg-light"
    data-storefront-layout="b2c-default"
    data-minicart-url="{{ route('storefront.cart.mini') }}"
>

<header class="bg-dark text-white py-3">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-3">

        <div class="fw-bold">
            <i class="fa-solid fa-building me-2"></i>
            {{ $store?->name ?? 'B2C Store' }}
        </div>

        <nav class="d-flex align-items-center gap-3 flex-wrap">
            <a href="{{ route('storefront.home') }}" class="text-white text-decoration-none">
                <i class="fa-solid fa-house me-1"></i>
                Home
            </a>

            <a href="{{ route('storefront.catalog.index') }}" class="text-white text-decoration-none">
                <i class="fa-solid fa-box me-1"></i>
                Catalogo
            </a>

            <div class="minicart-wrapper">
                <a href="{{ route('storefront.cart.index') }}" class="text-white text-decoration-none position-relative">
                    <i class="fa-solid fa-cart-shopping"></i>

                    <span
    class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
    data-cart-count-badge
    style="display: none;"
>
    0
</span>
                </a>

                <div id="minicart-container" class="minicart-dropdown">
                    <div class="small text-muted">Caricamento carrello...</div>
                </div>
            </div>

            @auth('customer')
                <a href="{{ route('storefront.account.index') }}" class="text-white text-decoration-none">
                    <i class="fa-solid fa-user me-1"></i>
                    Account
                </a>

                <form method="POST" action="{{ route('storefront.logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-light">
                        <i class="fa-solid fa-right-from-bracket me-1"></i>
                        Logout
                    </button>
                </form>
            @else
                <a href="{{ route('storefront.login') }}" class="btn btn-sm btn-outline-light">
                    <i class="fa-solid fa-right-to-bracket me-1"></i>
                    Accedi
                </a>
            @endauth
        </nav>

    </div>
</header>

<main class="py-4">
    <div class="container">
        @yield('content')
    </div>
</main>

<footer class="bg-dark text-white py-3 mt-5">
    <div class="container text-center small">
        © {{ date('Y') }} {{ $store?->name ?? 'B2C Store' }}
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>