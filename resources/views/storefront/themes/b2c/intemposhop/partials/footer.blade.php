@php
    $intempoMailConfig = array_merge(
        config('mail.storefront.stores.intempodistribution', []),
        config('mail.storefront.stores.intemposhop', [])
    );
    $infoEmail = trim((string) ($intempoMailConfig['info'] ?? $storeEmail ?? $companyEmail ?? 'info@intempo.it'));
    $contacts = trim((string) ($intempoMailConfig['contacts'] ?? ''));
@endphp

<footer class="intempo-b2c-footer">
    <div class="intempo-b2c-service-row intempo-b2c-shell">
        <a href="{{ route('storefront.catalog.index', $contextParams) }}"><i data-lucide="book-open"></i><span><strong>{{ __('Catalogo online') }}</strong><small>{{ __('Collezioni e novità') }}</small></span></a>
        <a href="{{ route('storefront.store-locator.index', $contextParams) }}"><i data-lucide="map-pin"></i><span><strong>{{ __('Store locator') }}</strong><small>{{ __('Trova un rivenditore') }}</small></span></a>
        <a href="{{ auth('customer')->check() ? route('storefront.account.index', $contextParams) : route('storefront.login', $contextParams) }}"><i data-lucide="user-round"></i><span><strong>{{ __('Area personale') }}</strong><small>{{ __('Ordini e preferiti') }}</small></span></a>
        @if($infoEmail !== '')
            <a href="mailto:{{ $infoEmail }}"><i data-lucide="mail"></i><span><strong>{{ __('Contatti') }}</strong><small>{{ $infoEmail }}</small></span></a>
        @else
            <span><i data-lucide="shield-check"></i><span><strong>{{ __('Acquisti protetti') }}</strong><small>{{ __('Checkout sicuro') }}</small></span></span>
        @endif
    </div>

    <div class="intempo-b2c-footer-main intempo-b2c-shell">
        <div class="intempo-b2c-footer-brand">
            @if(!empty($storeLogo))
                <img src="{{ $storeLogo }}" alt="{{ $storeName ?? 'INTEMPO' }}">
            @else
                <h2>INTEMPO</h2>
            @endif
            <p>{{ __('Oggetti quotidiani per scrivere, lavorare, organizzare e abitare il tempo con semplicità.') }}</p>
            @if($contacts !== '')
                <small>{{ $contacts }}</small>
            @endif
        </div>
        <div>
            <h3>{{ __('Prodotti') }}</h3>
            @foreach($footerCategories as $category)
                <a href="{{ route('storefront.category.show', array_merge(['slug' => $category['slug']], $contextParams)) }}">{{ $category['label'] }}</a>
            @endforeach
        </div>
        <div>
            <h3>{{ __('Informazioni') }}</h3>
            <a href="{{ route('storefront.catalog.index', $contextParams) }}">{{ __('Catalogo') }}</a>
            <a href="{{ route('storefront.search.index', $contextParams) }}">{{ __('Ricerca') }}</a>
            <a href="{{ route('storefront.store-locator.index', $contextParams) }}">{{ __('Punti vendita') }}</a>
        </div>
        <div>
            <h3>{{ __('Servizio clienti') }}</h3>
            @auth('customer')
                <a href="{{ route('storefront.account.index', $contextParams) }}">{{ __('Il mio account') }}</a>
                <a href="{{ route('storefront.wishlist.index', $contextParams) }}">{{ __('Preferiti') }}</a>
            @else
                <a href="{{ route('storefront.login', $contextParams) }}">{{ __('Accedi') }}</a>
                <a href="{{ route('storefront.register', $contextParams) }}">{{ __('Registrati') }}</a>
            @endauth
            <a href="{{ route('storefront.cart.index', $contextParams) }}">{{ __('Carrello') }}</a>
        </div>
    </div>
    <div class="intempo-b2c-footer-bottom intempo-b2c-shell">
        <span>© {{ $currentYear }} {{ $storeName ?? 'INTEMPO' }}</span>
        <span>{{ __('Made in Italy') }}</span>
    </div>
</footer>
