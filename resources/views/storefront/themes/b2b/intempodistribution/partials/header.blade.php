@php
    $leftMenuItems = collect($leftCategories ?? [])->filter(fn ($category) => filled($category['label'] ?? null) && filled($category['slug'] ?? null))->values();
    $rightMenuItems = collect($rightCategories ?? [])->filter(fn ($category) => filled($category['label'] ?? null) && filled($category['slug'] ?? null))->values();
    $desktopMenuItems = $leftMenuItems->concat($rightMenuItems)->values();
@endphp

<header class="storefront-header intempo-b2b-header sticky-top" data-intempo-b2b-header>
    <div class="intempo-b2b-mobilebar">
        <button
            type="button"
            class="intempo-b2b-icon-btn"
            data-bs-toggle="offcanvas"
            data-bs-target="#intempoB2bMobileMenu"
            aria-controls="intempoB2bMobileMenu"
            aria-label="Apri menu"
        >
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>

        <a class="intempo-b2b-brand" href="{{ route('storefront.home', $contextParams) }}" aria-label="{{ $storeName }}">
            @if($storeLogo)
                <img src="{{ $storeLogo }}" alt="{{ $storeName }}" loading="eager" decoding="async">
            @else
                <span>{{ $storeName }}</span>
            @endif
        </a>

        <button
            type="button"
            class="intempo-b2b-icon-btn position-relative"
            data-bs-toggle="offcanvas"
            data-bs-target="#storefrontMinicart"
            aria-controls="storefrontMinicart"
            data-minicart-trigger
            aria-label="Carrello"
        >
            <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
            <span class="intempo-b2b-count {{ $cartCount > 0 ? '' : 'd-none' }}" data-minicart-count-badge>{{ number_format($cartCount, 0, ',', '.') }}</span>
        </button>
    </div>

    <div class="intempo-b2b-desktopbar intempo-b2b-shell">
        <a class="intempo-b2b-brand" href="{{ route('storefront.home', $contextParams) }}" aria-label="{{ $storeName }}">
            @if($storeLogo)
                <img src="{{ $storeLogo }}" alt="{{ $storeName }}" loading="eager" decoding="async">
            @else
                <span>{{ $storeName }}</span>
            @endif
        </a>

        <nav class="intempo-b2b-meganav" aria-label="Categorie principali">
            @if(Route::has('storefront.catalog.index'))
                <a class="intempo-b2b-nav-link" href="{{ route('storefront.home', $contextParams) }}">Tutto il catalogo</a>
            @endif

            @foreach($desktopMenuItems as $category)
                @include('storefront.themes.b2b.intempodistribution.partials.header-category', ['category' => $category, 'contextParams' => $contextParams])
            @endforeach
        </nav>

        <div class="intempo-b2b-actions">
            @if($availableLocales->count() > 1)
                <div class="dropdown">
                    <button class="intempo-b2b-language" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        {{ strtoupper($locale) }}
                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @foreach($availableLocales as $localeItem)
                            @php
                                $localeCode = (string) ($localeItem['code'] ?? '');
                                $localeLabel = (string) ($localeItem['label'] ?? strtoupper($localeCode));
                                $localeUrl = $localeItem['url'] ?? null;
                            @endphp
                            <li>
                                <a class="dropdown-item {{ $localeCode === $locale ? 'active' : '' }}" href="{{ $localeUrl ?: request()->fullUrlWithQuery(array_merge(['locale' => $localeCode], $contextParams)) }}">{{ $localeLabel }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <button type="button" class="intempo-b2b-icon-btn" data-intempo-b2b-search-toggle aria-label="Cerca">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            </button>

            @if(($store?->isB2B() ?? false) && auth('customer')->check() && Route::has('storefront.cart.import'))
                <button
                    type="button"
                    class="intempo-b2b-quick-order"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#storefrontCartImport"
                    aria-controls="storefrontCartImport"
                    aria-label="Acquisto rapido"
                    title="Acquisto rapido"
                >
                    <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                    <span>Acquisto rapido</span>
                </button>
            @endif

            <button
                type="button"
                class="intempo-b2b-cart-btn position-relative"
                data-bs-toggle="offcanvas"
                data-bs-target="#storefrontMinicart"
                aria-controls="storefrontMinicart"
                data-minicart-trigger
                aria-label="Carrello"
                title="Carrello"
            >
                <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
                <span>Carrello</span>
                <span class="intempo-b2b-count {{ $cartCount > 0 ? '' : 'd-none' }}" data-minicart-count-badge>{{ number_format($cartCount, 0, ',', '.') }}</span>
            </button>
        </div>
    </div>

    @if(Route::has('storefront.search.index'))
        <div class="intempo-b2b-search-panel" data-intempo-b2b-search-panel hidden>
            <form
                method="GET"
                action="{{ route('storefront.search.index', $contextParams) }}"
                class="intempo-b2b-shell intempo-b2b-search-form storefront-search-form"
                role="search"
                data-storefront-search-form
                data-search-url="{{ route('storefront.search.index', $contextParams) }}"
                data-search-min-chars="2"
                @if(Route::has('storefront.search.suggest'))
                    data-suggest-url="{{ route('storefront.search.suggest', $contextParams) }}"
                    data-search-suggest-url="{{ route('storefront.search.suggest', $contextParams) }}"
                @endif
                @if(Route::has('storefront.cart.add'))
                    data-cart-add-url="{{ route('storefront.cart.add', $contextParams) }}"
                @endif
            >
                @if($agentContextId !== '')
                    <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                @endif

                <div class="storefront-search-shell" data-storefront-search-shell>
                    <div class="storefront-search-control intempo-b2b-search-control">
                        <i class="fa-solid fa-magnifying-glass storefront-search-icon" aria-hidden="true"></i>
                        <input
                            type="search"
                            name="q"
                            id="intempo-b2b-header-search"
                            class="form-control storefront-search-input"
                            value="{{ $searchQuery }}"
                            placeholder="Cerca prodotti, SKU, categorie..."
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            aria-label="Cerca prodotti"
                            aria-autocomplete="list"
                            aria-expanded="false"
                            aria-controls="intempo-b2b-search-suggestions"
                            data-storefront-search-input
                            data-search-input
                        >
                        <button type="button" class="btn storefront-search-clear {{ $searchQuery !== '' ? '' : 'd-none' }}" aria-label="Svuota ricerca" data-storefront-search-clear data-search-clear>
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                        <button type="submit" class="btn storefront-search-submit intempo-b2b-search-submit" aria-label="Cerca">
                            <span>Cerca</span>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div id="intempo-b2b-search-suggestions" class="storefront-search-suggestions d-none" role="listbox" aria-label="Suggerimenti ricerca" data-storefront-search-suggestions data-search-suggestions>
                        <div class="storefront-search-suggestions-inner" data-storefront-search-suggestions-inner data-search-suggestions-inner></div>
                    </div>
                </div>
            </form>
        </div>
    @endif
</header>

<div class="offcanvas offcanvas-start intempo-b2b-mobile-menu" tabindex="-1" id="intempoB2bMobileMenu" aria-labelledby="intempoB2bMobileMenuLabel">
    <div class="offcanvas-header border-bottom">
        <div>
            <div class="small text-muted text-uppercase fw-bold">INTEMPO B2B</div>
            <h5 class="offcanvas-title mb-0" id="intempoB2bMobileMenuLabel">Menu</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Chiudi"></button>
    </div>

    <div class="offcanvas-body p-0">
        @if(Route::has('storefront.search.index'))
            <form method="GET" action="{{ route('storefront.search.index', $contextParams) }}" class="intempo-b2b-mobile-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" name="q" value="{{ $searchQuery }}" placeholder="Cerca prodotti o SKU">
            </form>
        @endif

        <div class="intempo-b2b-mobile-quick">
            @if(($store?->isB2B() ?? false) && auth('customer')->check() && Route::has('storefront.cart.import'))
                <button type="button" data-bs-toggle="offcanvas" data-bs-target="#storefrontCartImport" aria-controls="storefrontCartImport">
                    <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                    Acquisto rapido
                </button>
            @endif

            @auth('customer')
                @if($isB2b)
                    <a href="{{ $documentsUrl }}"><i class="fa-regular fa-file-lines" aria-hidden="true"></i> Documenti</a>
                @endif
                <a href="{{ Route::has('storefront.account.index') ? route('storefront.account.index', $contextParams) : route('storefront.home', $contextParams) }}"><i class="fa-regular fa-user" aria-hidden="true"></i> Area cliente</a>
            @endauth
        </div>

        <nav class="intempo-b2b-mobile-list" aria-label="Categorie mobile">
            @if(Route::has('storefront.catalog.index'))
                <a href="{{ route('storefront.home', $contextParams) }}" class="intempo-b2b-mobile-root">Tutto il catalogo <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            @endif

            @foreach($navigationTree as $firstLevel)
                @php
                    $firstChildren = collect($firstLevel['children'] ?? []);
                    $firstLabel = $firstLevel['label'] ?? $firstLevel['code'] ?? 'Categoria';
                    $firstSlug = $firstLevel['slug'] ?? null;
                    $collapseId = 'intempo-b2b-category-' . md5((string) $firstSlug . '-' . $loop->index);
                @endphp

                @if($firstSlug)
                    <div class="intempo-b2b-mobile-category">
                        <div class="intempo-b2b-mobile-category-head">
                            <a href="{{ route('storefront.category.show', array_merge(['slug' => $firstSlug], $contextParams)) }}">{{ $firstLabel }}</a>
                            @if($firstChildren->isNotEmpty())
                                <button type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                </button>
                            @endif
                        </div>

                        @if($firstChildren->isNotEmpty())
                            <div class="collapse intempo-b2b-mobile-children" id="{{ $collapseId }}">
                                @foreach($firstChildren as $secondLevel)
                                    @php
                                        $secondLabel = $secondLevel['label'] ?? $secondLevel['code'] ?? 'Categoria';
                                        $secondSlug = $secondLevel['slug'] ?? null;
                                    @endphp

                                    @if($secondSlug)
                                        <a href="{{ route('storefront.category.show', array_merge(['slug' => $secondSlug], $contextParams)) }}">{{ $secondLabel }}</a>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </nav>
    </div>
</div>
