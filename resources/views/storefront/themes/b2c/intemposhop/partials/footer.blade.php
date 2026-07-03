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
        <a href="{{ route('storefront.catalog.index', $contextParams) }}"><i data-lucide="book-open"></i><span><strong>{{ __('themes_b2c.intempo.online_catalog') }}</strong><small>{{ __('themes_b2c.intempo.collections_and_news') }}</small></span></a>
        <a href="{{ route('storefront.store-locator.index', $contextParams) }}"><i data-lucide="map-pin"></i><span><strong>{{ __('themes_b2c.intempo.store_locator') }}</strong><small>{{ __('themes_b2c.intempo.find_retailer') }}</small></span></a>
        <a href="{{ auth('customer')->check() ? route('storefront.account.index', $contextParams) : route('storefront.login', $contextParams) }}"><i data-lucide="user-round"></i><span><strong>{{ __('themes_b2c.intempo.personal_area') }}</strong><small>{{ __('themes_b2c.intempo.orders_and_favorites') }}</small></span></a>
        @if($infoEmail !== '')
            <a href="mailto:{{ $infoEmail }}"><i data-lucide="mail"></i><span><strong>{{ __('themes_b2c.intempo.contacts') }}</strong><small>{{ $infoEmail }}</small></span></a>
        @else
            <span><i data-lucide="shield-check"></i><span><strong>{{ __('themes_b2c.intempo.protected_purchases') }}</strong><small>{{ __('themes_b2c.intempo.secure_checkout') }}</small></span></span>
        @endif
    </div>

    <div class="intempo-b2c-footer-main intempo-b2c-shell">
        <div class="intempo-b2c-footer-brand">
            @if(!empty($storeLogo))
                <img src="{{ $storeLogo }}" alt="{{ $storeName ?? 'INTEMPO' }}">
            @else
                <h2>INTEMPO</h2>
            @endif
            <p>{{ __('themes_b2c.intempo.story_intro') }}</p>
            @if($contacts !== '')
                <small>{{ $contacts }}</small>
            @endif
        </div>
        <div>
            <h3>{{ __('themes_b2c.intempo.products') }}</h3>
            @foreach($footerCategories as $category)
                <a href="{{ route('storefront.category.show', array_merge(['slug' => $category['slug']], $contextParams)) }}">{{ $category['label'] }}</a>
            @endforeach
        </div>
        <div>
            <h3>{{ __('themes_b2c.intempo.information') }}</h3>
            <a href="{{ route('storefront.catalog.index', $contextParams) }}">{{ __('themes_b2c.catalog.catalog') }}</a>
            <a href="{{ route('storefront.search.index', $contextParams) }}">{{ __('themes_b2c.intempo.search_link') }}</a>
            <a href="{{ route('storefront.store-locator.index', $contextParams) }}">{{ __('themes_b2c.intempo.points_of_sale') }}</a>
            @if(Route::has('storefront.privacy'))
                <a href="{{ route('storefront.privacy', $contextParams) }}">Privacy policy</a>
            @endif
            @if(Route::has('storefront.cookies'))
                <a href="{{ route('storefront.cookies', $contextParams) }}">Cookie policy</a>
            @endif
            @if(Route::has('storefront.shipping-returns'))
                <a href="{{ route('storefront.shipping-returns', $contextParams) }}">{{ __('legal.shipping_returns.title') }}</a>
            @endif
        </div>
        <div>
            <h3>{{ __('themes_b2c.intempo.customer_service') }}</h3>
            @auth('customer')
                <a href="{{ route('storefront.account.index', $contextParams) }}">{{ __('themes_b2c.intempo.my_account') }}</a>
                <a href="{{ route('storefront.wishlist.index', $contextParams) }}">{{ __('themes_b2c.intempo.favorites') }}</a>
            @else
                <a href="{{ route('storefront.login', $contextParams) }}">{{ __('themes_b2c.intempo.login') }}</a>
                <a href="{{ route('storefront.register', $contextParams) }}">{{ __('themes_b2c.intempo.register') }}</a>
            @endauth
            <a href="{{ route('storefront.cart.index', $contextParams) }}">{{ __('themes_b2c.intempo.cart') }}</a>
        </div>
    </div>
    <div class="intempo-b2c-footer-bottom intempo-b2c-shell">
        <span>© {{ $currentYear }} {{ $storeName ?? 'INTEMPO' }}</span>
        <span>{{ __('themes_b2c.intempo.made_in_italy') }}</span>
    </div>
</footer>
