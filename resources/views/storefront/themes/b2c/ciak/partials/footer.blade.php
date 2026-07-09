<footer class="ciak-footer">
    @php
        $serviceFallbackUrl = route('storefront.home', $contextParams ?? []);
        $serviceShippingUrl = Route::has('storefront.shipping-returns') ? route('storefront.shipping-returns') : $serviceFallbackUrl;
        $serviceCookieUrl = Route::has('storefront.cookies') ? route('storefront.cookies') : $serviceFallbackUrl;
        $serviceStoreLocatorUrl = Route::has('storefront.store-locator.index') ? route('storefront.store-locator.index') : $serviceFallbackUrl;
        $serviceAccountUrl = auth('customer')->check()
            ? route('storefront.account.index')
            : route('storefront.login');
    @endphp
    <div class="ciak-service-row ciak-shell">
        <a href="{{ $serviceShippingUrl }}"><i data-lucide="truck"></i><span><strong>{{ __('themes_b2c.ciak.free_shipping') }}</strong><small>{{ __('themes_b2c.ciak.shipping_short') }}</small></span></a>
        <a href="{{ $serviceCookieUrl }}"><i data-lucide="lock-keyhole"></i><span><strong>{{ __('themes_b2c.ciak.protected_purchases') }}</strong><small>{{ __('themes_b2c.ciak.secure_checkout') }}</small></span></a>
        <a href="{{ $serviceStoreLocatorUrl }}"><i data-lucide="map-pin"></i><span><strong>{{ __('CIAK Firenze') }}</strong><small>{{ __('themes_b2c.ciak.made_in_italy') }}</small></span></a>
        <a href="{{ $serviceAccountUrl }}"><i data-lucide="message-circle"></i><span><strong>{{ __('themes_b2c.ciak.personal_area') }}</strong><small>{{ __('themes_b2c.ciak.orders_and_favorites') }}</small></span></a>
    </div>
    <div class="ciak-footer-main ciak-shell">
        @php
            $legalProfile = collect($legalProfile ?? []);
            $company = $legalProfile->get('company') ?: ($store?->name ?? 'CIAK');
            $address = collect([
                $legalProfile->get('address'),
                $legalProfile->get('city'),
                $legalProfile->get('country'),
            ])->filter()->implode(', ');
            $vat = $legalProfile->get('vat');
            $taxCode = $legalProfile->get('tax_code');
        @endphp

        <div class="ciak-footer-brand">
            <a href="{{ route('storefront.home', $contextParams ?? []) }}" class="ciak-footer-brand-link" aria-label="{{ $store->name ?? 'CIAK' }}">
                @if(!empty($store?->logo_url))
                    <img src="{{ $store->logo_url }}" alt="{{ $store->name ?? 'CIAK' }}">
                @else
                    <h2>CIAK</h2>
                @endif
            </a>
            <div class="ciak-footer-legal">
                @if($address !== '')
                    <span>{{ $address }}</span>
                @endif
                @if($vat || $taxCode)
                    <span>
                        @if($vat)
                            P. IVA {{ $vat }}
                        @endif
                        @if($taxCode)
                            @if($vat)&nbsp;&nbsp;@endif
                            C.F. {{ $taxCode }}
                        @endif
                    </span>
                @endif
            </div>
        </div>
        <div><h3>{{ __('themes_b2c.ciak.products') }}</h3>@foreach($footerCategories as $category)<a href="{{ route('storefront.category.show', $category['slug']) }}">{{ $category['label'] }}</a>@endforeach</div>
        <div>
            <h3>{{ __('themes_b2c.ciak.information') }}</h3>
            <a href="{{ route('storefront.catalog.index') }}">{{ __('themes_b2c.ciak.catalog') }}</a>
            <a href="{{ route('storefront.search.index') }}">{{ __('themes_b2c.ciak.search_link') }}</a>
            @if(Route::has('storefront.privacy'))
                <a href="{{ route('storefront.privacy') }}">Privacy policy</a>
            @endif
            @if(Route::has('storefront.cookies'))
                <a href="{{ route('storefront.cookies') }}">Cookie policy</a>
            @endif
            @if(Route::has('storefront.shipping-returns'))
                <a href="{{ route('storefront.shipping-returns') }}">{{ __('legal.shipping_returns.title') }}</a>
            @endif
        </div>
        <div><h3>{{ __('themes_b2c.ciak.customer_service') }}</h3>@auth('customer')<a href="{{ route('storefront.account.index') }}">{{ __('themes_b2c.ciak.my_account') }}</a><a href="{{ route('storefront.wishlist.index') }}">{{ __('themes_b2c.ciak.favorites') }}</a>@else<a href="{{ route('storefront.login') }}">{{ __('themes_b2c.ciak.login') }}</a><a href="{{ route('storefront.register') }}">{{ __('themes_b2c.ciak.register') }}</a>@endauth<a href="{{ route('storefront.cart.index') }}">{{ __('themes_b2c.ciak.cart') }}</a></div>
    </div>

    <div class="ciak-footer-bottom ciak-shell">
        <span>© {{ $currentYear }} {{ $company }}</span>
        <span>{{ __('themes_b2c.ciak.made_in_italy') }}</span>
    </div>
</footer>
