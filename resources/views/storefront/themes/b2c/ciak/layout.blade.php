<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seo['title'] ?? trim($__env->yieldContent('title', $store?->name ?? 'CIAK')) }}</title>
    @include('storefront.base.partials.seo')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/themes/b2c/ciak.css') }}" rel="stylesheet">
    @stack('styles')
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body class="ciak-site" data-storefront-layout="b2c-ciak" data-minicart-url="{{ route('storefront.cart.mini') }}">
    @include('storefront.themes.b2c.ciak.partials.header')
    <main class="ciak-main">@yield('content')</main>
    @include('storefront.themes.b2c.ciak.partials.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
    <script>document.addEventListener('DOMContentLoaded', function () { window.lucide?.createIcons({ strokeWidth: 1.35 }); });</script>
    @stack('scripts')
</body>
</html>
