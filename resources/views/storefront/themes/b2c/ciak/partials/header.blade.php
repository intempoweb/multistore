{{-- resources/views/storefront/themes/b2c/ciak/partials/header.blade.php --}}
@php
    use App\Repositories\Storefront\CatalogRepository;
    use Illuminate\Support\Facades\Cache;
    use Illuminate\Support\Str;

    $store = $store ?? (app()->bound('currentStore') ? app('currentStore') : null);
    $locale = $locale ?? app()->getLocale();
    $storeName = $store->name ?? 'CIAK';
    $storeLogo = $store?->logo_url ? media_url($store->logo_url) : null;

    $cartCount = (float) ($cartCount ?? 0);
    $searchQuery = trim((string) request()->query('q', ''));
    $agentContextId = $agentContextId ?? (string) request('agent_context', '');
    $contextParams = $contextParams ?? ($agentContextId !== '' ? ['agent_context' => $agentContextId] : []);

    $navigationTree = collect($navigationTree ?? []);

    if ($navigationTree->isEmpty() && $store) {
        $navigationCacheKey = implode(':', [
            'ciak-navigation-tree',
            (int) ($store->id ?? 0),
            (int) ($store->ditta_cg18 ?? 0),
            (int) ($store->erp_site_code ?? 0),
            $locale,
        ]);

        try {
            $navigationTree = Cache::remember($navigationCacheKey, now()->addMinutes(30), function () use ($store, $locale) {
                return app(CatalogRepository::class)->getNavigationTree($store, $locale)->all();
            });
        } catch (\Throwable $exception) {
            $navigationTree = [];
        }

        $navigationTree = collect($navigationTree);
    }

    $catalogUrl = Route::has('storefront.catalog.index')
        ? route('storefront.catalog.index', $contextParams)
        : route('storefront.home', $contextParams);
@endphp

<header class="ciak-header storefront-header">
    <nav class="navbar navbar-expand-xl ciak-navbar" aria-label="Navigazione principale">
        <div class="container-fluid ciak-navbar-container">
            <a class="navbar-brand ciak-brand" href="{{ route('storefront.home', $contextParams) }}" aria-label="{{ $storeName }}">
                @if($storeLogo)
                    <img src="{{ $storeLogo }}" alt="{{ $storeName }}" class="ciak-brand-logo" loading="eager" decoding="async">
                @else
                    <span>CIAK</span>
                @endif
            </a>

            <button
                class="navbar-toggler ciak-navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#ciakMainNavbar"
                aria-controls="ciakMainNavbar"
                aria-expanded="false"
                aria-label="Apri navigazione"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse ciak-navbar-collapse" id="ciakMainNavbar">
                <ul class="navbar-nav ciak-main-nav mx-auto mb-3 mb-xl-0">
                    <li class="nav-item">
                        <a class="nav-link ciak-nav-link {{ request()->routeIs('storefront.catalog.index') ? 'active' : '' }}" href="{{ $catalogUrl }}">
                            Shop
                        </a>
                    </li>

                    @foreach($navigationTree->take(6) as $firstLevel)
                        @php
                            $firstLabel = $firstLevel['label'] ?? $firstLevel['code'] ?? 'Categoria';
                            $firstSlug = $firstLevel['slug'] ?? null;
                            $firstChildren = collect($firstLevel['children'] ?? []);
                        @endphp

                        @if($firstSlug)
                            <li class="nav-item dropdown">
                                <a
                                    class="nav-link dropdown-toggle ciak-nav-link {{ request()->is($firstSlug . '*') ? 'active' : '' }}"
                                    href="{{ route('storefront.category.show', array_merge(['slug' => $firstSlug], $contextParams)) }}"
                                    role="button"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside"
                                    aria-expanded="false"
                                >
                                    {{ Str::headline($firstLabel) }}
                                </a>

                                @if($firstChildren->isNotEmpty())
                                    <div class="dropdown-menu ciak-category-menu">
                                        <a
                                            href="{{ route('storefront.category.show', array_merge(['slug' => $firstSlug], $contextParams)) }}"
                                            class="ciak-category-menu-title"
                                        >
                                            Tutto {{ Str::lower($firstLabel) }}
                                        </a>

                                        <div class="ciak-category-menu-list">
                                            @foreach($firstChildren as $secondLevel)
                                                @php
                                                    $secondLabel = $secondLevel['label'] ?? $secondLevel['code'] ?? 'Categoria';
                                                    $secondSlug = $secondLevel['slug'] ?? null;
                                                @endphp

                                                @if($secondSlug)
                                                    <a href="{{ route('storefront.category.show', array_merge(['slug' => $secondSlug], $contextParams)) }}">
                                                        {{ Str::headline($secondLabel) }}
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ul>

                <div class="ciak-header-actions">
                    @if(Route::has('storefront.search.index'))
                        <button
                            type="button"
                            class="ciak-icon-link"
                            aria-label="Apri ricerca"
                            data-bs-toggle="collapse"
                            data-bs-target="#ciakSearchPanel"
                            aria-controls="ciakSearchPanel"
                            aria-expanded="{{ $searchQuery !== '' ? 'true' : 'false' }}"
                        >
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        </button>
                    @endif

                    @if(Route::has('storefront.wishlist.index'))
                        <a href="{{ route('storefront.wishlist.index', $contextParams) }}" class="ciak-icon-link" aria-label="Preferiti">
                            <i class="fa-regular fa-heart" aria-hidden="true"></i>
                        </a>
                    @endif

                    @auth('customer')
                        <a href="{{ route('storefront.account.index', $contextParams) }}" class="ciak-icon-link" aria-label="Account">
                            <i class="fa-regular fa-user" aria-hidden="true"></i>
                        </a>
                    @else
                        <a href="{{ route('storefront.login', $contextParams) }}" class="ciak-account-link">
                            Accedi
                        </a>
                    @endauth

                    <button
                        type="button"
                        class="ciak-cart-button"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#storefrontMinicart"
                        aria-controls="storefrontMinicart"
                        data-minicart-trigger
                    >
                        <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
                        <span>Carrello</span>

                        <span
                            class="ciak-cart-badge {{ $cartCount > 0 ? '' : 'd-none' }}"
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
        <div class="ciak-search-row collapse {{ $searchQuery !== '' ? 'show' : '' }}" id="ciakSearchPanel">
            <div class="container-fluid ciak-navbar-container">
                <form
                    method="GET"
                    action="{{ route('storefront.search.index', $contextParams) }}"
                    class="storefront-search-form ciak-search-form"
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

                    <label for="ciak-header-search" class="visually-hidden">Cerca prodotti</label>

                    <div class="storefront-search-shell ciak-search-shell" data-storefront-search-shell>
                        <div class="storefront-search-control ciak-search-control">
                            <i class="fa-solid fa-magnifying-glass storefront-search-icon" aria-hidden="true"></i>

                            <input
                                type="search"
                                name="q"
                                id="ciak-header-search"
                                class="form-control storefront-search-input"
                                value="{{ $searchQuery }}"
                                placeholder="Cerca agende, taccuini, colori..."
                                autocomplete="off"
                                autocapitalize="off"
                                spellcheck="false"
                                aria-label="Cerca prodotti"
                                aria-autocomplete="list"
                                aria-expanded="false"
                                aria-controls="ciak-search-suggestions"
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

                            <button type="submit" class="btn storefront-search-submit ciak-search-submit" aria-label="Cerca">
                                <span>Cerca</span>
                            </button>
                        </div>

                        <div
                            id="ciak-search-suggestions"
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
