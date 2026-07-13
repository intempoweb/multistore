@php
    $navigationItems = collect($navigationTree ?? [])->filter(fn ($category) => filled($category['label'] ?? null) && filled($category['slug'] ?? null))->values();
    $leftMenuItems = collect($leftCategories ?? [])->filter(fn ($category) => filled($category['label'] ?? null) && filled($category['slug'] ?? null))->values();
    $rightMenuItems = collect($rightCategories ?? [])->filter(fn ($category) => filled($category['label'] ?? null) && filled($category['slug'] ?? null))->values();
    $menuIconFor = static function (array $item): ?string {
        $text = mb_strtolower(trim((string) (($item['label'] ?? '').' '.($item['slug'] ?? ''))));

        return match (true) {
            str_contains($text, 'diar'), str_contains($text, 'agenda'), str_contains($text, 'agende') => asset('images/themes/b2c/intempo/icons/intempo-diaries-icons.png'),
            str_contains($text, 'lifestyle'), str_contains($text, 'pelletter'), str_contains($text, 'bors'), str_contains($text, 'portabloc') => asset('images/themes/b2c/intempo/icons/intempo-pelletteria-icons.png'),
            str_contains($text, 'home'), str_contains($text, 'office'), str_contains($text, 'ufficio'), str_contains($text, 'arredo'), str_contains($text, 'casa') => asset('images/themes/b2c/intempo/icons/intempo-home-office-icons.png'),
            default => null,
        };
    };
@endphp

<header class="intempo-b2c-header">
    <div class="intempo-b2c-topbar">
        <div class="intempo-b2c-shell intempo-b2c-topbar-inner">
            <span><i data-lucide="truck" aria-hidden="true"></i>{{ __('themes_b2c.intempo.free_shipping_banner') }}</span>
        </div>
    </div>

    <div class="intempo-b2c-nav-wrap" data-intempo-header>
        <div class="intempo-b2c-mobilebar">
            <button type="button" class="intempo-b2c-icon-btn" data-bs-toggle="offcanvas" data-bs-target="#intempoB2cMobileMenu" aria-label="{{ __('themes_b2c.intempo.open_menu') }}">
                <i data-lucide="menu"></i>
            </button>
            <a href="{{ route('storefront.home', $contextParams) }}" class="intempo-b2c-brand" aria-label="{{ $storeName ?? 'INTEMPO' }}">
                @if(!empty($storeLogo))
                    <img src="{{ $storeLogo }}" alt="{{ $storeName ?? 'INTEMPO' }}">
                @else
                    <span>INTEMPO</span>
                @endif
            </a>
            <div class="intempo-b2c-mobile-actions">
                <button type="button" class="intempo-b2c-icon-btn" data-intempo-search-toggle aria-label="{{ __('themes_b2c.intempo.search') }}"><i data-lucide="search"></i></button>
                <a href="{{ route('storefront.cart.index', $contextParams) }}" class="intempo-b2c-icon-btn" aria-label="{{ __('themes_b2c.intempo.cart') }}"><i data-lucide="shopping-bag"></i><span class="intempo-b2c-count d-none" data-cart-count-badge>0</span></a>
            </div>
        </div>

        <div class="intempo-b2c-desktopbar intempo-b2c-shell">
            <nav class="intempo-b2c-meganav intempo-b2c-meganav-left" aria-label="{{ __('themes_b2c.intempo.main_categories') }}">
                <a class="intempo-b2c-nav-link" href="{{ route('storefront.catalog.index', $contextParams) }}">{{ __('themes_b2c.intempo.all_catalog') }}</a>
                @foreach($leftMenuItems as $category)
                    @include('storefront.themes.b2c.intemposhop.partials.header-category', ['category' => $category, 'contextParams' => $contextParams, 'menuIconFor' => $menuIconFor])
                @endforeach
            </nav>

            <a href="{{ route('storefront.home', $contextParams) }}" class="intempo-b2c-brand intempo-b2c-brand-center" aria-label="{{ $storeName ?? 'INTEMPO' }}">
                @if(!empty($storeLogo))
                    <img src="{{ $storeLogo }}" alt="{{ $storeName ?? 'INTEMPO' }}">
                @else
                    <span>INTEMPO</span>
                @endif
            </a>

            <nav class="intempo-b2c-meganav intempo-b2c-meganav-right" aria-label="{{ __('themes_b2c.intempo.other_categories') }}">
                @foreach($rightMenuItems as $category)
                    @include('storefront.themes.b2c.intemposhop.partials.header-category', ['category' => $category, 'contextParams' => $contextParams, 'menuIconFor' => $menuIconFor])
                @endforeach
            </nav>

            <div class="intempo-b2c-actions">
                @if($supportedLocales->count() > 1)
                    <div class="dropdown">
                        <button class="intempo-b2c-language" type="button" data-bs-toggle="dropdown" aria-expanded="false">{{ strtoupper($locale) }}<i data-lucide="chevron-down"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @foreach($supportedLocales as $supportedLocale)
                                <li><a class="dropdown-item" href="{{ $localizedLocaleUrls[$supportedLocale] ?? $currentUrl }}">{{ strtoupper($supportedLocale) }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <button type="button" class="intempo-b2c-icon-btn" data-intempo-search-toggle aria-label="{{ __('Cerca') }}"><i data-lucide="search"></i></button>
                <a href="{{ auth('customer')->check() ? route('storefront.wishlist.index', $contextParams) : route('storefront.login', $contextParams) }}" class="intempo-b2c-icon-btn" aria-label="{{ __('themes_b2c.intempo.favorites') }}"><i data-lucide="heart"></i></a>
                <a href="{{ auth('customer')->check() ? route('storefront.account.index', $contextParams) : route('storefront.login', $contextParams) }}" class="intempo-b2c-icon-btn" aria-label="{{ __('themes_b2c.intempo.account') }}"><i data-lucide="user-round"></i></a>
                <button type="button" class="intempo-b2c-icon-btn" data-bs-toggle="offcanvas" data-bs-target="#storefrontMinicart" aria-controls="storefrontMinicart" data-minicart-trigger aria-label="{{ __('themes_b2c.intempo.cart') }}"><i data-lucide="shopping-bag"></i><span class="intempo-b2c-count d-none" data-minicart-count-badge>0</span></button>
            </div>
        </div>

        <div class="intempo-b2c-search-panel" data-intempo-search-panel hidden>
            <form
                action="{{ route('storefront.search.index', $contextParams) }}"
                method="GET"
                class="intempo-b2c-shell intempo-b2c-search-form storefront-search-form"
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
                            id="intempo-b2c-header-search"
                            class="storefront-search-input"
                            value="{{ $searchQuery }}"
                            placeholder="{{ __('themes_b2c.intempo.search') }}"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            aria-autocomplete="list"
                            aria-expanded="false"
                            aria-controls="intempo-b2c-search-suggestions"
                            data-storefront-search-input
                            data-search-input
                        >
                        <button type="button" class="intempo-b2c-icon-btn storefront-search-clear {{ $searchQuery !== '' ? '' : 'd-none' }}" data-storefront-search-clear data-search-clear aria-label="{{ __('themes_b2c.intempo.clear_search') }}"><i data-lucide="x"></i></button>
                        <button type="submit" class="intempo-b2c-icon-btn storefront-search-submit" aria-label="{{ __('themes_b2c.intempo.search') }}"><i data-lucide="arrow-right"></i></button>
                    </div>
                    <div id="intempo-b2c-search-suggestions" class="storefront-search-suggestions d-none" role="listbox" aria-label="{{ __('Suggerimenti ricerca') }}" data-storefront-search-suggestions data-search-suggestions>
                        <div class="storefront-search-suggestions-inner" data-storefront-search-suggestions-inner data-search-suggestions-inner></div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="offcanvas offcanvas-start intempo-b2c-mobile-menu" tabindex="-1" id="intempoB2cMobileMenu">
        <div class="offcanvas-header">
            <a href="{{ route('storefront.home', $contextParams) }}" class="intempo-b2c-brand" aria-label="{{ $storeName ?? 'INTEMPO' }}">
                @if(!empty($storeLogo))<img src="{{ $storeLogo }}" alt="{{ $storeName ?? 'INTEMPO' }}">@else<span>INTEMPO</span>@endif
            </a>
            <button type="button" class="intempo-b2c-icon-btn" data-bs-dismiss="offcanvas" aria-label="{{ __('themes_b2c.intempo.close') }}"><i data-lucide="x"></i></button>
        </div>
        <div class="offcanvas-body">
            <form action="{{ route('storefront.search.index', $contextParams) }}" method="GET" class="intempo-b2c-mobile-search">
                <i data-lucide="search" aria-hidden="true"></i>
                <input type="search" name="q" value="{{ $searchQuery }}" placeholder="{{ __('themes_b2c.intempo.search_shop') }}">
            </form>

            <nav class="intempo-b2c-mobile-links" aria-label="{{ __('themes_b2c.intempo.mobile_menu') }}">
                <a class="intempo-b2c-mobile-shop" href="{{ route('storefront.catalog.index', $contextParams) }}">
                    <span>{{ __('themes_b2c.intempo.all_catalog') }}</span>
                    <i data-lucide="arrow-right"></i>
                </a>

                @foreach($navigationItems as $category)
                    @php
                        $categoryChildren = collect($category['children'] ?? [])->filter(fn ($child) => filled($child['label'] ?? null) && filled($child['slug'] ?? null))->values();
                        $categoryUrl = route('storefront.category.show', array_merge(['slug' => $category['slug']], $contextParams));
                        $mobileId = 'intempo-mobile-category-' . md5((string) $category['slug']);
                        $mobileIcon = $menuIconFor($category);
                    @endphp
                    <div class="intempo-b2c-mobile-category">
                        <div class="intempo-b2c-mobile-category-head">
                            <a href="{{ $categoryUrl }}" class="{{ $mobileIcon ? 'has-icon' : '' }}">
                                @if($mobileIcon)<img src="{{ $mobileIcon }}" alt="" loading="lazy" decoding="async">@endif
                                <span>{{ $category['label'] }}</span>
                            </a>
                            @if($categoryChildren->isNotEmpty())
                                <button type="button" data-bs-toggle="collapse" data-bs-target="#{{ $mobileId }}" aria-controls="{{ $mobileId }}" aria-expanded="false" aria-label="{{ __('Apri sottocategorie') }}">
                                    <i data-lucide="chevron-down"></i>
                                </button>
                            @endif
                        </div>
                        @if($categoryChildren->isNotEmpty())
                            <div class="collapse intempo-b2c-mobile-children" id="{{ $mobileId }}">
                                @foreach($categoryChildren as $child)
                                    <a href="{{ route('storefront.category.show', array_merge(['slug' => $child['slug']], $contextParams)) }}">
                                        <span>{{ $child['label'] ?? $child['code'] }}</span>
                                        <i data-lucide="arrow-up-right"></i>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </nav>

            <div class="intempo-b2c-mobile-utilities">
                <a href="{{ auth('customer')->check() ? route('storefront.account.index', $contextParams) : route('storefront.login', $contextParams) }}"><i data-lucide="user-round"></i>{{ auth('customer')->check() ? __('Area personale') : __('Accedi') }}</a>
                <a href="{{ auth('customer')->check() ? route('storefront.wishlist.index', $contextParams) : route('storefront.login', $contextParams) }}"><i data-lucide="heart"></i>{{ __('Preferiti') }}</a>
                <a href="{{ route('storefront.store-locator.index', $contextParams) }}"><i data-lucide="map-pin"></i>{{ __('Negozi') }}</a>
            </div>
        </div>
    </div>
</header>
