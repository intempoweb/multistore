@php
    $normalizeReadyNavItem = null;
    $normalizeReadyNavItem = static function ($item) use ($contextParams, &$normalizeReadyNavItem) {
        $data = is_array($item) ? $item : (array) $item;
        $label = trim((string) ($data['title'] ?? $data['label'] ?? $data['name'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        $url = trim((string) ($data['url'] ?? ''));

        if ($url === '' && $slug !== '') {
            $url = route('storefront.category.show', array_merge(['slug' => $slug], $contextParams));
        }

        if ($label === '' || $url === '') {
            return null;
        }

        $children = collect($data['children'] ?? [])
            ->map($normalizeReadyNavItem)
            ->filter()
            ->values();

        return [
            'label' => $label,
            'slug' => $slug,
            'url' => $url,
            'children' => $children,
        ];
    };

    $navigationSources = collect($navigationTree ?? []);

    if ($navigationSources->isEmpty()) {
        $navigationSources = collect($leftCategories ?? [])
            ->merge($rightCategories ?? []);
    }

    if ($navigationSources->isEmpty()) {
        $navigationSources = collect($rootCategories ?? []);
    }

    $navigationItems = $navigationSources
        ->map($normalizeReadyNavItem)
        ->filter()
        ->unique(fn ($category) => $category['slug'] !== '' ? $category['slug'] : $category['url'])
        ->values();

    $collectionItems = collect($readyCollectionItems ?? [])
        ->map($normalizeReadyNavItem)
        ->filter()
        ->unique(fn ($category) => $category['label'].'|'.($category['slug'] !== '' ? $category['slug'] : $category['url']))
        ->values();

    if ($collectionItems->isEmpty()) {
        $collectionItems = collect($intempoAreas ?? [])
            ->map($normalizeReadyNavItem)
            ->filter()
            ->unique(fn ($category) => $category['label'].'|'.($category['slug'] !== '' ? $category['slug'] : $category['url']))
            ->values();
    }

    if ($collectionItems->isEmpty()) {
        $collectionItems = $navigationItems->filter(function ($category) {
            $text = mb_strtolower(trim((string) (($category['label'] ?? '').' '.($category['slug'] ?? ''))));

            return str_contains($text, 'ready') || str_contains($text, 'collez');
        })->values();
    }

    $readyLogo = trim((string) ($storeLogo ?? ''));

    if ($readyLogo === '') {
        $readyLogo = media_url(config('mail.storefront.stores.ready.logo'));
    }
    $searchQuery = trim((string) ($searchQuery ?? request('q', '')));
@endphp

<header class="ready-header" data-intempo-header>
    <div class="intempo-b2c-topbar ready-topbar">
    <div class="intempo-b2c-shell intempo-b2c-topbar-inner">
        <span>
            <i data-lucide="truck" aria-hidden="true"></i>

            <span
                id="ready-shipping-message"
                data-messages='@json([
                    __("themes_b2c.intempo.free_shipping_italy_banner"),
                    __("themes_b2c.intempo.free_shipping_europe_banner")
                ])'
            >
                {{ __("themes_b2c.intempo.free_shipping_italy_banner") }}
            </span>
        </span>
    </div>
</div>
    <div class="ready-header-bar">
        <button type="button" class="ready-header-icon ready-menu-button" data-bs-toggle="offcanvas" data-bs-target="#readyMobileMenu" aria-label="{{ __('themes_b2c.intempo.open_menu') }}">
            <i data-lucide="menu"></i>
        </button>

        <a href="{{ route('storefront.home', $contextParams) }}" class="ready-brand" aria-label="Ready">
            <img src="{{ $readyLogo }}" alt="Ready" loading="eager" decoding="async">
        </a>

        <div class="ready-header-actions">
            @if(($supportedLocales ?? collect())->count() > 1)
                <div class="dropdown ready-language-dropdown">
                    <button
                        class="ready-header-icon ready-language-switch"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-label="{{ __('Lingua') }}"
                    >
                        <span>{{ strtoupper($locale ?? app()->getLocale()) }}</span>
                        <i data-lucide="chevron-down"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @foreach($supportedLocales as $supportedLocale)
                            <li>
                                <a
                                    class="dropdown-item"
                                    href="{{ $localizedLocaleUrls[$supportedLocale] ?? ($currentUrl ?? url()->current()) }}"
                                >
                                    {{ strtoupper($supportedLocale) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <button type="button" class="ready-header-icon" data-intempo-search-toggle aria-label="{{ __('Cerca') }}"><i data-lucide="search"></i></button>
            <a href="{{ auth('customer')->check() ? route('storefront.wishlist.index', $contextParams) : route('storefront.login', $contextParams) }}" class="ready-header-icon" aria-label="{{ __('themes_b2c.intempo.favorites') }}"><i data-lucide="heart"></i><span class="ready-header-count d-none" data-wishlist-count-badge>0</span></a>
            <a href="{{ auth('customer')->check() ? route('storefront.account.index', $contextParams) : route('storefront.login', $contextParams) }}" class="ready-header-icon" aria-label="{{ __('themes_b2c.intempo.account') }}"><i data-lucide="user-round"></i></a>
            <button type="button" class="ready-header-icon" data-bs-toggle="offcanvas" data-bs-target="#storefrontMinicart" aria-controls="storefrontMinicart" data-minicart-trigger aria-label="{{ __('themes_b2c.intempo.cart') }}"><i data-lucide="shopping-cart"></i><span class="ready-header-count d-none" data-minicart-count-badge>0</span></button>
        </div>
    </div>

    <nav class="ready-category-nav" aria-label="Categorie principali">
        <a class="ready-nav-link" href="{{ route('storefront.catalog.index', $contextParams) }}">Tutto il catalogo</a>
        @foreach($navigationItems as $category)
            @include('storefront.themes.b2c.ready.partials.header-category', ['category' => $category, 'contextParams' => $contextParams])
        @endforeach
        @if($collectionItems->isNotEmpty())
            <div class="ready-category-dropdown">
                <button type="button" data-bs-toggle="dropdown" aria-expanded="false">Collezioni<i data-lucide="chevron-down"></i></button>
                <ul class="dropdown-menu">
                    @foreach($collectionItems as $category)
                        <li><a class="dropdown-item" href="{{ $category['url'] }}">{{ $category['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </nav>

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
</header>

    <div
    class="offcanvas offcanvas-start intempo-b2c-mobile-menu ready-mobile-menu"
    tabindex="-1"
    id="readyMobileMenu"
    aria-labelledby="readyMobileMenuLabel"
>
        <div class="offcanvas-header">
            <a
            href="{{ route('storefront.home', $contextParams) }}"
            class="ready-brand"
            id="readyMobileMenuLabel"
            aria-label="Ready"
        >
            <img src="{{ $readyLogo }}" alt="Ready" loading="eager" decoding="async">
        </a>
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
                    @php
                        $children = collect($category['children'] ?? [])->filter(fn ($child) => filled($child['label'] ?? null) && filled($child['url'] ?? null))->values();
                        $mobileCategoryId = 'ready-mobile-category-' . md5((string) ($category['slug'] !== '' ? $category['slug'] : $category['url']));
                    @endphp
                    <div class="ready-mobile-category">
                        <div class="ready-mobile-category-head">
                            <a href="{{ $category['url'] }}">
                                {{ $category['label'] }}
                                <i data-lucide="arrow-up-right"></i>
                            </a>
                            @if($children->isNotEmpty())
                                <button type="button" data-bs-toggle="collapse" data-bs-target="#{{ $mobileCategoryId }}" aria-controls="{{ $mobileCategoryId }}" aria-expanded="false" aria-label="Apri sottocategorie {{ $category['label'] }}">
                                    <i data-lucide="chevron-down"></i>
                                </button>
                            @endif
                        </div>
                        @if($children->isNotEmpty())
                            <div class="collapse ready-mobile-children" id="{{ $mobileCategoryId }}">
                                @foreach($children as $child)
                                    @php
                                        $grandchildren = collect($child['children'] ?? [])
                                            ->filter(fn ($grandchild) => filled($grandchild['label'] ?? null) && filled($grandchild['url'] ?? null))
                                            ->values();
                                    @endphp
                                    <a href="{{ $child['url'] }}">{{ $child['label'] }}<i data-lucide="arrow-up-right"></i></a>
                                    @foreach($grandchildren as $grandchild)
                                        <a class="is-third" href="{{ $grandchild['url'] }}">{{ $grandchild['label'] }}</a>
                                    @endforeach
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
                @if($collectionItems->isNotEmpty())
                    <span class="ready-mobile-section-title">Collezioni</span>
                    @foreach($collectionItems as $category)
                        <a href="{{ $category['url'] }}">{{ $category['label'] }}<i data-lucide="arrow-up-right"></i></a>
                    @endforeach
                @endif
                @if(($supportedLocales ?? collect())->count() > 1)
                    <span class="ready-mobile-section-title">{{ __('Lingua') }}</span>
                    <div class="ready-mobile-language-links">
                        @foreach($supportedLocales as $supportedLocale)
                            <a
                                href="{{ $localizedLocaleUrls[$supportedLocale] ?? ($currentUrl ?? url()->current()) }}"
                                class="{{ ($supportedLocale === ($locale ?? app()->getLocale())) ? 'is-active' : '' }}"
                            >
                                {{ strtoupper($supportedLocale) }}
                                @if($supportedLocale === ($locale ?? app()->getLocale()))
                                    <i data-lucide="check"></i>
                                @else
                                    <i data-lucide="arrow-up-right"></i>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif

                <a href="{{ route('storefront.store-locator.index', $contextParams) }}">{{ __('themes_b2c.intempo.points_of_sale') }}<i data-lucide="map-pin"></i></a>
            </nav>
        </div>
    </div>
