{{-- resources/views/storefront/themes/b2c/ciak/partials/header.blade.php --}}
@php
    use App\Repositories\Storefront\CatalogRepository;
    use Illuminate\Support\Facades\Cache;
    use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

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
            'ciak-b2c-navigation',
            (int) ($store->id ?? 0),
            (int) ($store->ditta_cg18 ?? 0),
            (int) ($store->erp_site_code ?? 0),
            $locale,
        ]);

        try {
            $navigationTree = collect(Cache::remember($navigationCacheKey, now()->addMinutes(30), function () use ($store, $locale) {
                return app(CatalogRepository::class)->getNavigationTree($store, $locale)->all();
            }));
        } catch (Throwable $exception) {
            $navigationTree = collect();
        }
    }

    $supportedLocales = collect($store?->supported_locales ?: ['it'])
        ->filter(fn ($item) => filled($item))
        ->values();

    $localizedUrl = function (string $targetLocale) {
        return LaravelLocalization::getLocalizedURL($targetLocale, null, request()->query(), true);
    };

    $activeCategorySlug = (string) request()->route('slug', '');
@endphp

<header class="ciak-header">
    <div class="ciak-header-inner">
        <a class="ciak-brand" href="{{ route('storefront.home', $contextParams) }}" aria-label="{{ $storeName }}">
            @if($storeLogo)
                <img src="{{ $storeLogo }}" alt="{{ $storeName }}" class="ciak-brand-logo" loading="eager" decoding="async">
            @else
                <span>CIAK</span>
            @endif
        </a>

        <nav class="ciak-nav" aria-label="Navigazione principale">
            @if(Route::has('storefront.catalog.index'))
                <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="ciak-nav-link {{ request()->routeIs('storefront.catalog.index') ? 'is-active' : '' }}">
                    Shop
                </a>
            @endif

            @foreach($navigationTree->take(6) as $item)
                @php
                    $label = $item['label'] ?? $item['code'] ?? 'Categoria';
                    $slug = $item['slug'] ?? null;
                    $children = collect($item['children'] ?? []);
                    $isActive = $slug && ($activeCategorySlug === $slug || str_starts_with($activeCategorySlug, $slug . '/'));
                @endphp

                @if($slug)
                    <div class="ciak-nav-item">
                        <a href="{{ route('storefront.category.show', array_merge(['slug' => $slug], $contextParams)) }}" class="ciak-nav-link {{ $isActive ? 'is-active' : '' }}">
                            {{ $label }}
                        </a>

                        @if($children->isNotEmpty())
                            <div class="ciak-nav-panel">
                                <a href="{{ route('storefront.category.show', array_merge(['slug' => $slug], $contextParams)) }}" class="ciak-nav-panel-title">
                                    Tutto {{ $label }}
                                </a>

                                <div class="ciak-nav-panel-grid">
                                    @foreach($children as $child)
                                        @php
                                            $childLabel = $child['label'] ?? $child['code'] ?? 'Categoria';
                                            $childSlug = $child['slug'] ?? null;
                                            $grandChildren = collect($child['children'] ?? []);
                                        @endphp

                                        @if($childSlug)
                                            <div>
                                                <a href="{{ route('storefront.category.show', array_merge(['slug' => $childSlug], $contextParams)) }}" class="ciak-nav-panel-link">
                                                    {{ $childLabel }}
                                                </a>

                                                @if($grandChildren->isNotEmpty())
                                                    <div class="ciak-nav-panel-sublinks">
                                                        @foreach($grandChildren->take(4) as $grandChild)
                                                            @php
                                                                $grandChildLabel = $grandChild['label'] ?? $grandChild['code'] ?? null;
                                                                $grandChildSlug = $grandChild['slug'] ?? null;
                                                            @endphp

                                                            @if($grandChildSlug && $grandChildLabel)
                                                                <a href="{{ route('storefront.category.show', array_merge(['slug' => $grandChildSlug], $contextParams)) }}">
                                                                    {{ $grandChildLabel }}
                                                                </a>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </nav>

        <div class="ciak-header-actions">
            @if(Route::has('storefront.search.index'))
                <button
                    type="button"
                    class="ciak-icon-button"
                    data-bs-toggle="collapse"
                    data-bs-target="#ciakSearch"
                    aria-controls="ciakSearch"
                    aria-expanded="{{ $searchQuery !== '' ? 'true' : 'false' }}"
                    aria-label="Apri ricerca"
                >
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                </button>
            @endif

            @if($supportedLocales->count() > 1)
                <div class="dropdown">
                    <button class="ciak-locale-button" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        {{ strtoupper($locale) }}
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end ciak-locale-menu">
                        @foreach($supportedLocales as $supportedLocale)
                            <li>
                                <a class="dropdown-item {{ $supportedLocale === $locale ? 'active' : '' }}" href="{{ $localizedUrl((string) $supportedLocale) }}">
                                    {{ strtoupper((string) $supportedLocale) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(Route::has('storefront.wishlist.index'))
                <a href="{{ route('storefront.wishlist.index', $contextParams) }}" class="ciak-icon-button" aria-label="Preferiti">
                    <i class="fa-regular fa-heart" aria-hidden="true"></i>
                </a>
            @endif

            @auth('customer')
                <a href="{{ route('storefront.account.index', $contextParams) }}" class="ciak-account-link">
                    Account
                </a>
            @else
                <a href="{{ route('storefront.login', $contextParams) }}" class="ciak-account-link">
                    Accedi
                </a>
            @endauth

            <button
                type="button"
                class="ciak-cart-trigger"
                data-bs-toggle="offcanvas"
                data-bs-target="#storefrontMinicart"
                aria-controls="storefrontMinicart"
                data-minicart-trigger
            >
                <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
                <span>Carrello</span>
                <span class="ciak-cart-count {{ $cartCount > 0 ? '' : 'd-none' }}" data-minicart-count-badge>
                    {{ number_format($cartCount, 0, ',', '.') }}
                </span>
            </button>
        </div>
    </div>

    @if(Route::has('storefront.search.index'))
        <div class="collapse {{ $searchQuery !== '' ? 'show' : '' }}" id="ciakSearch">
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
                            Cerca
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
                        <div class="storefront-search-suggestions-inner" data-storefront-search-suggestions-inner data-search-suggestions-inner></div>
                    </div>
                </div>
            </form>
        </div>
    @endif
</header>
