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
        try {
            $navigationTree = collect(Cache::remember(
                'ciak-navigation:' . $store->id . ':' . $locale,
                now()->addMinutes(30),
                fn () => app(CatalogRepository::class)->getNavigationTree($store, $locale)->all()
            ));
        } catch (Throwable) {
            $navigationTree = collect();
        }
    }

    $navigation = $navigationTree
        ->filter(fn ($item) => !empty($item['slug']))
        ->take(6)
        ->values();

    $split = (int) ceil($navigation->count() / 2);
    $leftNavigation = $navigation->take($split)->values();
    $rightNavigation = $navigation->slice($split)->values();
    $activeCategorySlug = (string) request()->route('slug', '');

    $supportedLocales = collect($store?->supported_locales ?: ['it'])
        ->filter(fn ($item) => filled($item))
        ->values();

    $localizedUrl = fn (string $targetLocale) => LaravelLocalization::getLocalizedURL($targetLocale, null, request()->query(), true);
    $homeUrl = route('storefront.home', $contextParams);
@endphp

<header class="ciak-header">
    <div class="ciak-top-header" aria-label="{{ __('Informazioni servizio') }}">
        <div class="ciak-top-header-inner">
            <span class="ciak-top-header-item">
                <i data-lucide="truck" aria-hidden="true"></i>
                {{ __('Spedizione gratuita per ordini sopra i 50€') }}
            </span>
            <span class="ciak-top-header-item">
                <i data-lucide="rotate-ccw" aria-hidden="true"></i>
                {{ __('Reso facile entro 30 giorni') }}
            </span>
            <span class="ciak-top-header-item">
                <i data-lucide="lock-keyhole" aria-hidden="true"></i>
                {{ __('Pagamenti sicuri 100% protetti') }}
            </span>
        </div>
    </div>

    <div class="ciak-header-inner">
        <div class="ciak-header-mobile-start">
            <button
                type="button"
                class="ciak-menu-toggle"
                data-bs-toggle="collapse"
                data-bs-target="#ciakMobileMenu"
                aria-controls="ciakMobileMenu"
                aria-expanded="false"
                aria-label="{{ __('Apri navigazione') }}"
            >
                <span></span>
                <span></span>
            </button>
        </div>

        <nav class="ciak-nav ciak-nav-left" aria-label="{{ __('Categorie principali') }}">
            @foreach($leftNavigation as $item)
                @include('storefront.themes.b2c.ciak.partials.header-category', [
                    'item' => $item,
                    'activeCategorySlug' => $activeCategorySlug,
                    'contextParams' => $contextParams,
                ])
            @endforeach
        </nav>

        <a class="ciak-brand" href="{{ $homeUrl }}" aria-label="{{ $storeName }}">
            @if($storeLogo)
                <img src="{{ $storeLogo }}" alt="{{ $storeName }}" loading="eager" decoding="async">
            @else
                <span>CIAK</span>
            @endif
        </a>

        <nav class="ciak-nav ciak-nav-right" aria-label="{{ __('Categorie principali') }}">
            @foreach($rightNavigation as $item)
                @include('storefront.themes.b2c.ciak.partials.header-category', [
                    'item' => $item,
                    'activeCategorySlug' => $activeCategorySlug,
                    'contextParams' => $contextParams,
                ])
            @endforeach
        </nav>

        <div class="ciak-header-actions">
            @if($supportedLocales->count() > 1)
                <div class="dropdown ciak-locale-selector">
                    <button class="ciak-locale-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Cambia lingua') }}">
                        <span>{{ strtoupper($locale) }}</span>
                        <i data-lucide="chevron-down" aria-hidden="true"></i>
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

            @if(Route::has('storefront.search.index'))
                <button
                    type="button"
                    class="ciak-icon-button"
                    data-bs-toggle="collapse"
                    data-bs-target="#ciakSearch"
                    aria-controls="ciakSearch"
                    aria-expanded="{{ $searchQuery !== '' ? 'true' : 'false' }}"
                    aria-label="{{ __('Cerca') }}"
                >
                    <i data-lucide="search" aria-hidden="true"></i>
                </button>
            @endif

            @auth('customer')
                <a href="{{ route('storefront.account.index', $contextParams) }}" class="ciak-icon-button ciak-account-icon" aria-label="{{ __('Account') }}">
                    <i data-lucide="user" aria-hidden="true"></i>
                </a>
            @else
                <a href="{{ route('storefront.login', $contextParams) }}" class="ciak-icon-button ciak-account-icon" aria-label="{{ __('Accedi') }}">
                    <i data-lucide="user" aria-hidden="true"></i>
                </a>
            @endauth

            <button
                type="button"
                class="ciak-cart-trigger"
                data-bs-toggle="offcanvas"
                data-bs-target="#storefrontMinicart"
                aria-controls="storefrontMinicart"
                aria-label="{{ __('Apri carrello') }}"
                data-minicart-trigger
            >
                <i data-lucide="shopping-bag" aria-hidden="true"></i>
                <span class="ciak-cart-count {{ $cartCount > 0 ? '' : 'd-none' }}" data-minicart-count-badge>
                    {{ number_format($cartCount, 0, ',', '.') }}
                </span>
            </button>
        </div>
    </div>

    @if($navigation->isNotEmpty())
        <div class="collapse ciak-mobile-menu" id="ciakMobileMenu">
            <nav class="ciak-mobile-menu-inner" aria-label="{{ __('Menu mobile') }}">
                @foreach($navigation as $item)
                    @include('storefront.themes.b2c.ciak.partials.header-category', [
                        'item' => $item,
                        'activeCategorySlug' => $activeCategorySlug,
                        'contextParams' => $contextParams,
                        'compact' => true,
                    ])
                @endforeach
            </nav>
        </div>
    @endif

    @if(Route::has('storefront.search.index'))
        <div class="collapse {{ $searchQuery !== '' ? 'show' : '' }} ciak-search-row" id="ciakSearch">
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

                <label for="ciak-header-search" class="visually-hidden">{{ __('Cerca prodotti') }}</label>
                <div class="storefront-search-shell ciak-search-shell" data-storefront-search-shell>
                    <div class="storefront-search-control ciak-search-control">
                        <i data-lucide="search" class="storefront-search-icon" aria-hidden="true"></i>
                        <input
                            type="search"
                            name="q"
                            id="ciak-header-search"
                            class="form-control storefront-search-input"
                            value="{{ $searchQuery }}"
                            placeholder="{{ __('Cerca agende, taccuini, accessori...') }}"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            aria-label="{{ __('Cerca prodotti') }}"
                            aria-autocomplete="list"
                            aria-expanded="false"
                            aria-controls="ciak-search-suggestions"
                            data-storefront-search-input
                            data-search-input
                        >
                        <button
                            type="button"
                            class="btn storefront-search-clear {{ $searchQuery !== '' ? '' : 'd-none' }}"
                            aria-label="{{ __('Svuota ricerca') }}"
                            data-storefront-search-clear
                            data-search-clear
                        >
                            <i data-lucide="x" aria-hidden="true"></i>
                        </button>
                        <button type="submit" class="btn storefront-search-submit ciak-search-submit" aria-label="{{ __('Cerca') }}">
                            {{ __('Cerca') }}
                        </button>
                    </div>
                    <div
                        id="ciak-search-suggestions"
                        class="storefront-search-suggestions d-none"
                        role="listbox"
                        aria-label="{{ __('Suggerimenti ricerca') }}"
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
