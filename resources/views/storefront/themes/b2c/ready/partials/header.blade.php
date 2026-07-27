@php
    $navigationItems = collect($navigationTree ?? [])->filter(fn ($category) => filled($category['label'] ?? null) && filled($category['slug'] ?? null))->values();
    $readyLogo = 'https://ready-to.it/wp-content/uploads/2024/03/logo-ready.svg';
    $searchQuery = trim((string) ($searchQuery ?? request('q', '')));
@endphp

<header class="ready-header" data-intempo-header>
    <div class="ready-header-bar ready-shell">
        <button type="button" class="ready-header-icon ready-menu-button" data-bs-toggle="offcanvas" data-bs-target="#readyMobileMenu" aria-label="{{ __('themes_b2c.intempo.open_menu') }}">
            <i data-lucide="menu"></i>
        </button>

        <a href="{{ route('storefront.home', $contextParams) }}" class="ready-brand" aria-label="Ready">
            <img src="{{ $readyLogo }}" alt="Ready" loading="eager" decoding="async">
        </a>

        <div class="ready-header-actions">
            <button type="button" class="ready-header-icon" data-intempo-search-toggle aria-label="{{ __('Cerca') }}"><i data-lucide="search"></i></button>
            <a href="{{ auth('customer')->check() ? route('storefront.wishlist.index', $contextParams) : route('storefront.login', $contextParams) }}" class="ready-header-icon" aria-label="{{ __('themes_b2c.intempo.favorites') }}"><i data-lucide="heart"></i><span class="ready-header-count d-none" data-wishlist-count-badge>0</span></a>
            <a href="{{ auth('customer')->check() ? route('storefront.account.index', $contextParams) : route('storefront.login', $contextParams) }}" class="ready-header-icon" aria-label="{{ __('themes_b2c.intempo.account') }}"><i data-lucide="user-round"></i></a>
            <button type="button" class="ready-header-icon" data-bs-toggle="offcanvas" data-bs-target="#storefrontMinicart" aria-controls="storefrontMinicart" data-minicart-trigger aria-label="{{ __('themes_b2c.intempo.cart') }}"><i data-lucide="shopping-cart"></i><span class="ready-header-count d-none" data-minicart-count-badge>0</span></button>
        </div>
    </div>

    <div class="intempo-b2c-search-panel ready-search-panel" data-intempo-search-panel hidden>
        <form
            action="{{ route('storefront.search.index', $contextParams) }}"
            method="GET"
            class="ready-shell intempo-b2c-search-form storefront-search-form"
            role="search"
            data-storefront-search-form
            data-search-url="{{ route('storefront.search.index', $contextParams) }}"
            data-search-min-chars="2"
            data-suggest-url="{{ route('storefront.search.suggest', $contextParams) }}"
            data-search-suggest-url="{{ route('storefront.search.suggest', $contextParams) }}"
            data-cart-add-url="{{ route('storefront.cart.add', $contextParams) }}"
        >
            <div class="storefront-search-shell" data-storefront-search-shell>
                <div class="intempo-b2c-search-control storefront-search-control">
                    <i data-lucide="search" class="storefront-search-icon" aria-hidden="true"></i>
                    <input
                        type="search"
                        name="q"
                        id="ready-header-search"
                        class="storefront-search-input"
                        value="{{ $searchQuery }}"
                        placeholder="{{ __('themes_b2c.intempo.search') }}"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        aria-autocomplete="list"
                        aria-expanded="false"
                        aria-controls="ready-search-suggestions"
                        data-storefront-search-input
                        data-search-input
                    >
                    <button type="button" class="intempo-b2c-icon-btn storefront-search-clear {{ $searchQuery !== '' ? '' : 'd-none' }}" data-storefront-search-clear data-search-clear aria-label="{{ __('themes_b2c.intempo.clear_search') }}"><i data-lucide="x"></i></button>
                    <button type="submit" class="intempo-b2c-icon-btn storefront-search-submit" aria-label="{{ __('themes_b2c.intempo.search') }}"><i data-lucide="arrow-right"></i></button>
                </div>
                <div id="ready-search-suggestions" class="storefront-search-suggestions d-none" role="listbox" aria-label="{{ __('Suggerimenti ricerca') }}" data-storefront-search-suggestions data-search-suggestions>
                    <div class="storefront-search-suggestions-inner" data-storefront-search-suggestions-inner data-search-suggestions-inner></div>
                </div>
            </div>
        </form>
    </div>

    <div class="offcanvas offcanvas-start intempo-b2c-mobile-menu ready-mobile-menu" tabindex="-1" id="readyMobileMenu">
        <div class="offcanvas-header">
            <a href="{{ route('storefront.home', $contextParams) }}" class="ready-brand" aria-label="Ready"><img src="{{ $readyLogo }}" alt="Ready" loading="eager" decoding="async"></a>
            <button type="button" class="ready-header-icon" data-bs-dismiss="offcanvas" aria-label="{{ __('themes_b2c.intempo.close') }}"><i data-lucide="x"></i></button>
        </div>
        <div class="offcanvas-body">
            <form action="{{ route('storefront.search.index', $contextParams) }}" method="GET" class="intempo-b2c-mobile-search">
                <i data-lucide="search" aria-hidden="true"></i>
                <input type="search" name="q" value="{{ $searchQuery }}" placeholder="{{ __('themes_b2c.intempo.search_shop') }}">
            </form>

            <nav class="ready-mobile-links" aria-label="{{ __('themes_b2c.intempo.mobile_menu') }}">
                <a href="{{ route('storefront.catalog.index', $contextParams) }}">{{ __('themes_b2c.intempo.all_catalog') }}<i data-lucide="arrow-right"></i></a>
                @foreach($navigationItems as $category)
                    <a href="{{ route('storefront.category.show', array_merge(['slug' => $category['slug']], $contextParams)) }}">
                        {{ $category['label'] }}
                        <i data-lucide="arrow-up-right"></i>
                    </a>
                @endforeach
                <a href="{{ route('storefront.store-locator.index', $contextParams) }}">{{ __('themes_b2c.intempo.points_of_sale') }}<i data-lucide="map-pin"></i></a>
            </nav>
        </div>
    </div>
</header>
