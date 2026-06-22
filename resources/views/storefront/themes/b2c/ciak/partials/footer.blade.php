@php
    use App\Repositories\Storefront\CatalogRepository;
    use Illuminate\Support\Facades\Cache;

    $store = $store ?? (app()->bound('currentStore') ? app('currentStore') : null);
    $locale = $locale ?? app()->getLocale();
    $storeName = $store->name ?? 'CIAK';
    $storeLogo = $store?->logo_url ? media_url($store->logo_url) : null;
    $contextParams = $contextParams ?? [];
    $footerNavigation = collect($navigationTree ?? []);

    if ($footerNavigation->isEmpty() && $store) {
        try {
            $footerNavigation = collect(Cache::remember(
                'ciak-b2c-footer-navigation:' . $store->id . ':' . $locale,
                now()->addMinutes(30),
                fn () => app(CatalogRepository::class)->getNavigationTree($store, $locale)->all()
            ));
        } catch (Throwable) {
            $footerNavigation = collect();
        }
    }

    $catalogUrl = Route::has('storefront.catalog.index')
        ? route('storefront.catalog.index', $contextParams)
        : route('storefront.home', $contextParams);
@endphp

<footer class="ciak-footer">
    <div class="ciak-footer-main">
        <div class="ciak-footer-brand">
            <a href="{{ $catalogUrl }}" aria-label="{{ $storeName }}">
                @if($storeLogo)
                    <img src="{{ $storeLogo }}" alt="{{ $storeName }}" loading="lazy" decoding="async">
                @else
                    <strong>CIAK</strong>
                @endif
            </a>
            <p>{{ __('Agende e taccuini italiani dal 1977, pensati per accompagnare ogni giorno.') }}</p>
        </div>

        <nav class="ciak-footer-column" aria-label="{{ __('Collezioni') }}">
            <strong>{{ __('Collezioni') }}</strong>
            <a href="{{ $catalogUrl }}">{{ __('Tutto lo shop') }}</a>
            @foreach($footerNavigation->filter(fn ($item) => !empty($item['slug']))->take(5) as $category)
                <a href="{{ route('storefront.category.show', array_merge(['slug' => $category['slug']], $contextParams)) }}">
                    {{ $category['label'] ?? $category['code'] ?? __('Collezione') }}
                </a>
            @endforeach
        </nav>

        <nav class="ciak-footer-column" aria-label="{{ __('Servizio clienti') }}">
            <strong>{{ __('Servizio clienti') }}</strong>
            @auth('customer')
                <a href="{{ route('storefront.account.index', $contextParams) }}">{{ __('Il mio account') }}</a>
            @else
                <a href="{{ route('storefront.login', $contextParams) }}">{{ __('Accedi o registrati') }}</a>
            @endauth
            @if(Route::has('storefront.wishlist.index'))
                <a href="{{ route('storefront.wishlist.index', $contextParams) }}">{{ __('Preferiti') }}</a>
            @endif
            @if(Route::has('storefront.cart.index'))
                <a href="{{ route('storefront.cart.index', $contextParams) }}">{{ __('Carrello') }}</a>
            @endif
        </nav>

        <div class="ciak-footer-note">
            <strong>{{ __('CIAK Firenze') }}</strong>
            <p>{{ __('Fatto in Italia. Spedizione gratuita in Italia da € 60 e in Europa da € 120.') }}</p>
            <a href="{{ $catalogUrl }}">{{ __('Scopri la collezione') }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>
    </div>

    <div class="ciak-footer-bottom">
        <span>© {{ date('Y') }} {{ $storeName }}. {{ __('Tutti i diritti riservati.') }}</span>
        <div>
            <span>{{ __('Made in Italy') }}</span>
            <span>{{ __('Store B2C') }}</span>
        </div>
    </div>
</footer>
