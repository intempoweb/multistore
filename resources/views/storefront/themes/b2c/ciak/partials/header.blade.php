@php
    use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

    $locale = $locale ?? app()->getLocale();
    $contextParams = request()->filled('agent_context') ? ['agent_context' => request('agent_context')] : [];
    $rootCategories = collect();
    try {
        $rootCategories = app(\App\Repositories\Storefront\CatalogRepository::class)
            ->getRootCategories($store, $locale)
            ->filter(fn ($category) => filled($category['label'] ?? null) && filled($category['slug'] ?? null))
            ->values();
    } catch (\Throwable) {
        $rootCategories = collect();
    }
    $splitAt = (int) ceil($rootCategories->count() / 2);
    $leftCategories = $rootCategories->take($splitAt);
    $rightCategories = $rootCategories->slice($splitAt);
    $supportedLocales = collect($store?->supported_locales ?: [$store?->default_locale ?: $locale])->filter()->unique()->values();
    $currentUrl = url()->current();
@endphp

<header class="ciak-header">
    <div class="ciak-topbar">
        <div class="ciak-shell ciak-topbar-inner">
            <span><i data-lucide="truck" aria-hidden="true"></i>{{ __('Spedizione gratuita in Italia per ordini superiori a € 60 · Spedizione gratuita in Europa per ordini superiori a € 120') }}</span>
        </div>
    </div>

    <div class="ciak-nav-shell">
        <div class="ciak-nav-mobile">
            <button type="button" class="ciak-icon-button" data-bs-toggle="offcanvas" data-bs-target="#ciakMobileMenu" aria-label="{{ __('Apri menu') }}"><i data-lucide="menu"></i></button>
            <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="ciak-brand" aria-label="CIAK catalogo">
                @if(!empty($store?->logo_url))<img src="{{ $store->logo_url }}" alt="{{ $store->name ?? 'CIAK' }}">@else<span>CIAK</span>@endif
            </a>
            <a href="{{ route('storefront.cart.index', $contextParams) }}" class="ciak-icon-button" aria-label="{{ __('Carrello') }}"><i data-lucide="shopping-bag"></i><span class="ciak-count" data-cart-count-badge style="display:none">0</span></a>
        </div>

        <div class="ciak-nav-desktop ciak-shell">
            <nav class="ciak-nav-side ciak-nav-side-left" aria-label="{{ __('Categorie principali') }}">
                @foreach($leftCategories as $category)
                    @include('storefront.themes.b2c.ciak.partials.header-category', ['category' => $category])
                @endforeach
            </nav>

            <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="ciak-brand" aria-label="CIAK catalogo">
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
                                <li><a class="dropdown-item" href="{{ LaravelLocalization::getLocalizedURL($supportedLocale, $currentUrl) }}">{{ strtoupper($supportedLocale) }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <button type="button" class="ciak-icon-button" data-ciak-search-toggle aria-label="{{ __('Cerca') }}"><i data-lucide="search"></i></button>
                <a href="{{ auth('customer')->check() ? route('storefront.wishlist.index', $contextParams) : route('storefront.login', $contextParams) }}" class="ciak-icon-button" aria-label="{{ __('Preferiti') }}"><i data-lucide="heart"></i></a>
                <a href="{{ auth('customer')->check() ? route('storefront.account.index', $contextParams) : route('storefront.login', $contextParams) }}" class="ciak-icon-button" aria-label="{{ __('Account') }}"><i data-lucide="user-round"></i></a>
                <div class="minicart-wrapper">
                    <a href="{{ route('storefront.cart.index', $contextParams) }}" class="ciak-icon-button" aria-label="{{ __('Carrello') }}"><i data-lucide="shopping-bag"></i><span class="ciak-count" data-cart-count-badge style="display:none">0</span></a>
                    <div id="minicart-container" class="minicart-dropdown"><div class="small text-muted">{{ __('Caricamento...') }}</div></div>
                </div>
            </div>
        </div>

        <div class="ciak-search-panel" data-ciak-search-panel hidden>
            <form action="{{ route('storefront.search.index', $contextParams) }}" method="GET" class="ciak-shell ciak-search-form" role="search">
                <i data-lucide="search" aria-hidden="true"></i>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Cerca nel catalogo') }}" autocomplete="off" data-storefront-search-input>
                <button type="button" class="ciak-icon-button" data-ciak-search-close aria-label="{{ __('Chiudi ricerca') }}"><i data-lucide="x"></i></button>
            </form>
        </div>
    </div>

    <div class="offcanvas offcanvas-start ciak-mobile-menu" tabindex="-1" id="ciakMobileMenu">
        <div class="offcanvas-header"><span class="ciak-mobile-title">{{ __('Menu') }}</span><button type="button" class="ciak-icon-button" data-bs-dismiss="offcanvas" aria-label="{{ __('Chiudi') }}"><i data-lucide="x"></i></button></div>
        <div class="offcanvas-body">
            <nav class="ciak-mobile-links">
                <a href="{{ route('storefront.catalog.index', $contextParams) }}">{{ __('Tutto lo shop') }}</a>
                @foreach($rootCategories as $category)
                    @include('storefront.themes.b2c.ciak.partials.header-category', ['category' => $category])
                @endforeach
            </nav>
        </div>
    </div>
</header>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const panel = document.querySelector('[data-ciak-search-panel]');
    document.querySelector('[data-ciak-search-toggle]')?.addEventListener('click', function () {
        panel.hidden = false;
        panel.querySelector('input')?.focus();
    });
    document.querySelector('[data-ciak-search-close]')?.addEventListener('click', function () { panel.hidden = true; });
});
</script>
@endpush
