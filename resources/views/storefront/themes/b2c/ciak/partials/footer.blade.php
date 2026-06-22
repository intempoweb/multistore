@php
    $footerCategories = collect();
    try {
        $footerCategories = app(\App\Repositories\Storefront\CatalogRepository::class)
            ->getRootCategories($store, app()->getLocale())->take(4);
    } catch (\Throwable) {
        $footerCategories = collect();
    }
@endphp
<footer class="ciak-footer">
    <div class="ciak-service-row ciak-shell">
        <div><i data-lucide="truck"></i><span><strong>{{ __('Spedizione gratuita') }}</strong><small>{{ __('Italia da € 60 · Europa da € 120') }}</small></span></div>
        <div><i data-lucide="lock-keyhole"></i><span><strong>{{ __('Acquisti protetti') }}</strong><small>{{ __('Checkout sicuro') }}</small></span></div>
        <div><i data-lucide="map-pin"></i><span><strong>{{ __('CIAK Firenze') }}</strong><small>{{ __('Made in Italy') }}</small></span></div>
        <div><i data-lucide="message-circle"></i><span><strong>{{ __('Area personale') }}</strong><small>{{ __('Ordini e preferiti') }}</small></span></div>
    </div>
    <div class="ciak-footer-main ciak-shell">
        <div class="ciak-footer-brand">
            @if(!empty($store?->logo_url))<img src="{{ $store->logo_url }}" alt="{{ $store->name ?? 'CIAK' }}">@else<h2>CIAK</h2>@endif
            <p>{{ $store?->name }}</p>
        </div>
        <div><h3>{{ __('Prodotti') }}</h3>@foreach($footerCategories as $category)<a href="{{ route('storefront.category.show', $category['slug']) }}">{{ $category['label'] }}</a>@endforeach</div>
        <div><h3>{{ __('Informazioni') }}</h3><a href="{{ route('storefront.catalog.index') }}">{{ __('Catalogo') }}</a><a href="{{ route('storefront.search.index') }}">{{ __('Ricerca') }}</a></div>
        <div><h3>{{ __('Servizio clienti') }}</h3>@auth('customer')<a href="{{ route('storefront.account.index') }}">{{ __('Il mio account') }}</a><a href="{{ route('storefront.wishlist.index') }}">{{ __('Preferiti') }}</a>@else<a href="{{ route('storefront.login') }}">{{ __('Accedi') }}</a><a href="{{ route('storefront.register') }}">{{ __('Registrati') }}</a>@endauth<a href="{{ route('storefront.cart.index') }}">{{ __('Carrello') }}</a></div>
    </div>
    <div class="ciak-footer-bottom ciak-shell"><span>© {{ date('Y') }} {{ $store?->name ?? 'CIAK' }}</span><span>{{ __('Made in Italy') }}</span></div>
</footer>
