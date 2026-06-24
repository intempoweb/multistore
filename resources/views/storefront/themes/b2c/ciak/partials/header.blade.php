<header class="ciak-header">
    <div class="ciak-topbar">
        <div class="ciak-shell ciak-topbar-inner">
            <span><i data-lucide="truck" aria-hidden="true"></i>{{ __('Spedizione gratuita in Italia per ordini superiori a € 60 · Spedizione gratuita in Europa per ordini superiori a € 120') }}</span>
        </div>
    </div>

    <div class="ciak-nav-shell">
        <div class="ciak-nav-mobile">
            <button type="button" class="ciak-icon-button" data-bs-toggle="offcanvas" data-bs-target="#ciakMobileMenu" aria-label="{{ __('Apri menu') }}"><i data-lucide="menu"></i></button>
            <a href="{{ route('storefront.home', $contextParams) }}" class="ciak-brand" aria-label="CIAK catalogo">
                @if(!empty($store?->logo_url))<img src="{{ $store->logo_url }}" alt="{{ $store->name ?? 'CIAK' }}">@else<span>CIAK</span>@endif
            </a>
            <div class="ciak-mobile-actions">
                <button type="button" class="ciak-icon-button" data-ciak-search-toggle aria-label="{{ __('Cerca') }}"><i data-lucide="search"></i></button>
                <a href="{{ route('storefront.cart.index', $contextParams) }}" class="ciak-icon-button" aria-label="{{ __('Carrello') }}"><i data-lucide="shopping-bag"></i><span class="ciak-count" data-cart-count-badge style="display:none">0</span></a>
            </div>
        </div>

        <div class="ciak-nav-desktop ciak-shell">
            <nav class="ciak-nav-side ciak-nav-side-left" aria-label="{{ __('Categorie principali') }}">
                @foreach($leftCategories as $category)
                    @include('storefront.themes.b2c.ciak.partials.header-category', ['category' => $category])
                @endforeach
            </nav>

            <a href="{{ route('storefront.home', $contextParams) }}" class="ciak-brand" aria-label="CIAK catalogo">
                @if(!empty($store?->logo_url))<img src="{{ $store->logo_url }}" alt="{{ $store->name ?? 'CIAK' }}">@else<span>CIAK</span>@endif
            </a>

            <nav class="ciak-nav-side ciak-nav-side-right" aria-label="{{ __('Altre categorie') }}">
                @foreach($rightCategories as $category)
                    @include('storefront.themes.b2c.ciak.partials.header-category', ['category' => $category])
                @endforeach
            </nav>

            <div class="ciak-actions">
                @if($supportedLocales->count() > 1)
                    <div class="dropdown">
                        <button class="ciak-language" type="button" data-bs-toggle="dropdown" aria-expanded="false">{{ strtoupper($locale) }}<i data-lucide="chevron-down"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @foreach($supportedLocales as $supportedLocale)
                                <li><a class="dropdown-item" href="{{ $localizedLocaleUrls[$supportedLocale] ?? $currentUrl }}">{{ strtoupper($supportedLocale) }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <button type="button" class="ciak-icon-button" data-ciak-search-toggle aria-label="{{ __('Cerca') }}"><i data-lucide="search"></i></button>
                <a href="{{ auth('customer')->check() ? route('storefront.wishlist.index', $contextParams) : route('storefront.login', $contextParams) }}" class="ciak-icon-button" aria-label="{{ __('Preferiti') }}"><i data-lucide="heart"></i></a>
                <a href="{{ auth('customer')->check() ? route('storefront.account.index', $contextParams) : route('storefront.login', $contextParams) }}" class="ciak-icon-button" aria-label="{{ __('Account') }}"><i data-lucide="user-round"></i></a>
                <button type="button" class="ciak-icon-button" data-bs-toggle="offcanvas" data-bs-target="#storefrontMinicart" aria-controls="storefrontMinicart" data-minicart-trigger aria-label="{{ __('Carrello') }}"><i data-lucide="shopping-bag"></i><span class="ciak-count d-none" data-minicart-count-badge>0</span></button>
            </div>
        </div>

        <div class="ciak-search-panel" data-ciak-search-panel hidden>
            <form
                action="{{ route('storefront.search.index', $contextParams) }}"
                method="GET"
                class="ciak-shell ciak-search-form storefront-search-form"
                role="search"
                data-storefront-search-form
                data-search-url="{{ route('storefront.search.index', $contextParams) }}"
                data-search-min-chars="2"
                data-suggest-url="{{ route('storefront.search.suggest', $contextParams) }}"
                data-search-suggest-url="{{ route('storefront.search.suggest', $contextParams) }}"
                data-cart-add-url="{{ route('storefront.cart.add', $contextParams) }}"
            >
                <div class="storefront-search-shell" data-storefront-search-shell>
                    <div class="ciak-search-control storefront-search-control">
                        <i data-lucide="search" class="storefront-search-icon" aria-hidden="true"></i>
                        <input
                            type="search"
                            name="q"
                            id="ciak-header-search"
                            class="storefront-search-input"
                            value="{{ $searchQuery }}"
                            placeholder="{{ __('Cerca prodotti, SKU o categorie') }}"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            aria-autocomplete="list"
                            aria-expanded="false"
                            aria-controls="ciak-search-suggestions"
                            data-storefront-search-input
                            data-search-input
                        >
                        <button type="button" class="ciak-icon-button storefront-search-clear {{ $searchQuery !== '' ? '' : 'd-none' }}" data-storefront-search-clear data-search-clear aria-label="{{ __('Svuota ricerca') }}"><i data-lucide="x"></i></button>
                        <button type="submit" class="ciak-icon-button storefront-search-submit" aria-label="{{ __('Cerca') }}"><i data-lucide="arrow-right"></i></button>
                    </div>
                    <div id="ciak-search-suggestions" class="storefront-search-suggestions d-none" role="listbox" aria-label="{{ __('Suggerimenti ricerca') }}" data-storefront-search-suggestions data-search-suggestions>
                        <div class="storefront-search-suggestions-inner" data-storefront-search-suggestions-inner data-search-suggestions-inner></div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="offcanvas offcanvas-start ciak-mobile-menu" tabindex="-1" id="ciakMobileMenu">
        <div class="offcanvas-header">
            <a href="{{ route('storefront.home', $contextParams) }}" class="ciak-brand" aria-label="CIAK home">
                @if(!empty($store?->logo_url))<img src="{{ $store->logo_url }}" alt="{{ $store->name ?? 'CIAK' }}">@else<span>CIAK</span>@endif
            </a>
            <button type="button" class="ciak-icon-button" data-bs-dismiss="offcanvas" aria-label="{{ __('Chiudi') }}"><i data-lucide="x"></i></button>
        </div>
        <div class="offcanvas-body">
            <nav class="ciak-mobile-links">
                <a class="ciak-mobile-shop-link" href="{{ route('storefront.catalog.index', $contextParams) }}"><span>{{ __('Tutto lo shop') }}</span><i data-lucide="arrow-right"></i></a>
                @foreach($navigationTree as $category)
                    @include('storefront.themes.b2c.ciak.partials.header-category', ['category' => $category, 'mobile' => true])
                @endforeach
            </nav>
            <div class="ciak-mobile-utilities">
                <a href="{{ auth('customer')->check() ? route('storefront.account.index', $contextParams) : route('storefront.login', $contextParams) }}"><i data-lucide="user-round"></i><span>{{ auth('customer')->check() ? __('Area personale') : __('Accedi') }}</span></a>
                <a href="{{ auth('customer')->check() ? route('storefront.wishlist.index', $contextParams) : route('storefront.login', $contextParams) }}"><i data-lucide="heart"></i><span>{{ __('Preferiti') }}</span></a>
            </div>
        </div>
    </div>
</header>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const panel = document.querySelector('[data-ciak-search-panel]');
    document.querySelectorAll('[data-ciak-search-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            panel.hidden = !panel.hidden;
            if (!panel.hidden) panel.querySelector('input')?.focus();
        });
    });
});
</script>
@endpush
