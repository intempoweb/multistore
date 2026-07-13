<header class="storefront-header bg-white sticky-top">
    <nav class="navbar navbar-expand-xl navbar-light bg-white storefront-navbar">
        <div class="container-fluid storefront-navbar-container">
            <a class="navbar-brand storefront-brand d-flex align-items-center" href="{{ route('storefront.home', $contextParams) }}" aria-label="{{ $storeName }}">
                @if($storeLogo)
                    <img src="{{ $storeLogo }}" alt="{{ $storeName }}" class="storefront-brand-logo" loading="eager" decoding="async">
                @else
                    <span class="storefront-brand-mark d-inline-flex align-items-center justify-content-center rounded bg-dark text-white">
                        {{ mb_substr($storeName, 0, 1) }}
                    </span>
                    <span class="storefront-brand-name ms-2">{{ $storeName }}</span>
                @endif
            </a>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#storefrontMainNavbar"
                aria-controls="storefrontMainNavbar"
                aria-expanded="false"
                aria-label="Apri navigazione"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse storefront-navbar-collapse" id="storefrontMainNavbar">
                <ul class="navbar-nav storefront-main-nav mx-auto mb-2 mb-xl-0">
                    <li class="nav-item">
                        <a class="nav-link storefront-nav-link d-none {{ request()->routeIs('storefront.home') ? 'active' : '' }}" href="{{ route('storefront.home', $contextParams) }}">
                            Home
                        </a>
                    </li>

                    @if(Route::has('storefront.catalog.index'))
                        <li class="nav-item">
                            <a class="nav-link storefront-nav-link {{ request()->routeIs('storefront.home') ? 'active' : '' }}" href="{{ route('storefront.home', $contextParams) }}">
                                Tutto il catalogo
                            </a>
                        </li>
                    @endif

                    @foreach($navigationTree as $firstLevel)
                        @php
                            $firstChildren = collect($firstLevel['children'] ?? []);
                            $firstLabel = $firstLevel['label'] ?? $firstLevel['code'] ?? 'Categoria';
                            $firstSlug = $firstLevel['slug'] ?? null;
                        @endphp

                        @if($firstSlug)
                            <li class="nav-item dropdown storefront-nav-category-item">
                                <a
                                    class="nav-link dropdown-toggle storefront-nav-link {{ request()->is('category/' . $firstSlug . '*') ? 'active' : '' }}"
                                    href="{{ route('storefront.category.show', array_merge(['slug' => $firstSlug], $contextParams)) }}"
                                    role="button"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside"
                                    aria-expanded="false"
                                >
                                    {{ $firstLabel }}
                                </a>

                                @if($firstChildren->isNotEmpty())
                                    <div class="dropdown-menu storefront-category-menu storefront-category-megamenu">
                                        <div class="storefront-category-menu-header">
                                            <div class="storefront-category-menu-eyebrow">Categoria</div>
                                            <a href="{{ route('storefront.category.show', array_merge(['slug' => $firstSlug], $contextParams)) }}" class="storefront-category-menu-title text-decoration-none">
                                                {{ $firstLabel }}
                                            </a>
                                        </div>

                                        <div class="storefront-category-menu-grid">
                                            @foreach($firstChildren as $secondLevel)
                                                @php
                                                    $secondChildren = collect($secondLevel['children'] ?? []);
                                                    $secondLabel = $secondLevel['label'] ?? $secondLevel['code'] ?? 'Categoria';
                                                    $secondSlug = $secondLevel['slug'] ?? null;
                                                @endphp

                                                <div class="storefront-category-menu-section">
                                                    @if($secondSlug)
                                                        <a href="{{ route('storefront.category.show', array_merge(['slug' => $secondSlug], $contextParams)) }}" class="storefront-category-menu-second text-decoration-none">
                                                            {{ $secondLabel }}
                                                        </a>
                                                    @else
                                                        <div class="storefront-category-menu-second">
                                                            {{ $secondLabel }}
                                                        </div>
                                                    @endif

                                                    @if($secondChildren->isNotEmpty())
                                                        <div class="storefront-category-menu-third-list">
                                                            @foreach($secondChildren as $thirdLevel)
                                                                @php
                                                                    $thirdLabel = $thirdLevel['label'] ?? $thirdLevel['code'] ?? 'Categoria';
                                                                    $thirdSlug = $thirdLevel['slug'] ?? null;
                                                                @endphp

                                                                @if($thirdSlug)
                                                                    <a href="{{ route('storefront.category.show', array_merge(['slug' => $thirdSlug], $contextParams)) }}" class="storefront-category-menu-third text-decoration-none">
                                                                        {{ $thirdLabel }}
                                                                    </a>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="storefront-category-menu-footer">
                                            <a href="{{ route('storefront.category.show', array_merge(['slug' => $firstSlug], $contextParams)) }}" class="btn btn-sm btn-outline-secondary w-100">
                                                Vedi tutta la categoria
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ul>

                <div class="storefront-header-actions d-flex align-items-center gap-2 flex-wrap">
                    @if($availableLocales->count() > 1)
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary storefront-header-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                {{ strtoupper($locale) }}
                            </button>

                            <ul class="dropdown-menu dropdown-menu-end">
                                @foreach($availableLocales as $localeItem)
                                    @php
                                        $localeCode = (string) ($localeItem['code'] ?? '');
                                        $localeLabel = (string) ($localeItem['label'] ?? strtoupper($localeCode));
                                        $localeUrl = $localeItem['url'] ?? null;
                                    @endphp

                                    <li>
                                        <a
                                            class="dropdown-item {{ $localeCode === $locale ? 'active' : '' }}"
                                            href="{{ $localeUrl ?: request()->fullUrlWithQuery(array_merge(['locale' => $localeCode], $contextParams)) }}"
                                        >
                                            {{ $localeLabel }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(($store?->isB2B() ?? false) && auth('customer')->check() && Route::has('storefront.cart.import'))
                        <button
                            type="button"
                            class="btn btn-sm btn-success storefront-header-btn"
                            data-bs-toggle="offcanvas"
                            data-bs-target="#storefrontCartImport"
                            aria-controls="storefrontCartImport"
                            aria-label="Acquisto rapido"
                        >
                            <i class="fa-solid fa-bolt"></i>
                            <span>Acquisto rapido</span>
                        </button>
                    @endif

                    <button
                        type="button"
                        class="btn btn-sm btn-primary position-relative storefront-cart-btn"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#storefrontMinicart"
                        aria-controls="storefrontMinicart"
                        data-minicart-trigger
                    >
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span>Carrello</span>

                        <span
                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger storefront-cart-badge {{ $cartCount > 0 ? '' : 'd-none' }}"
                            data-minicart-count-badge
                        >
                            {{ number_format($cartCount, 0, ',', '.') }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    @if(Route::has('storefront.search.index'))
        <div class="storefront-search-bar-row">
            <div class="container-fluid storefront-navbar-container">
                <form
                    method="GET"
                    action="{{ route('storefront.search.index', $contextParams) }}"
                    class="storefront-search-form"
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
                    <label for="storefront-header-search" class="visually-hidden">
                        Cerca prodotti
                    </label>

                    <div class="storefront-search-shell" data-storefront-search-shell>
                        <div class="storefront-search-control">
                            <i class="fa-solid fa-magnifying-glass storefront-search-icon" aria-hidden="true"></i>

                            <input
                                type="search"
                                name="q"
                                id="storefront-header-search"
                                class="form-control storefront-search-input"
                                value="{{ $searchQuery }}"
                                placeholder="Cerca prodotti, SKU, categorie..."
                                autocomplete="off"
                                autocapitalize="off"
                                spellcheck="false"
                                aria-label="Cerca prodotti"
                                aria-autocomplete="list"
                                aria-expanded="false"
                                aria-controls="storefront-search-suggestions"
                                data-storefront-search-input
                                data-search-input
                            >

                            <button
                                type="button"
                                class="btn storefront-search-clear {{ $searchQuery !== '' ? '' : 'd-none' }}"
                                aria-label="Svuota ricerca"
                                data-storefront-search-clear
                                data-search-clear
                            >
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>

                            <button type="submit" class="btn storefront-search-submit" aria-label="Cerca">
                                <span>Cerca</span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>

                        <div
                            id="storefront-search-suggestions"
                            class="storefront-search-suggestions d-none"
                            role="listbox"
                            aria-label="Suggerimenti ricerca"
                            data-storefront-search-suggestions
                            data-search-suggestions
                        >
                            <div
                                class="storefront-search-suggestions-inner"
                                data-storefront-search-suggestions-inner
                                data-search-suggestions-inner
                            ></div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</header>
