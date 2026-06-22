@php
    use App\Repositories\Storefront\CatalogRepository;
    use Illuminate\Support\Facades\Cache;

    $store = $store ?? (app()->bound('currentStore') ? app('currentStore') : null);
    $locale = $locale ?? app()->getLocale();
    $storeName = $store->name ?? 'CIAK';
    $storeLogo = $store?->logo_url ? media_url($store->logo_url) : null;
    $contextParams = $contextParams ?? [];
    $companyName = $store->company_name ?? $store->ragione_sociale ?? $storeName;
    $companyEmail = $store->email ?? $store->company_email ?? null;
    $companyPhone = $store->phone ?? $store->company_phone ?? null;

    $footerNavigation = collect();
    if ($store) {
        try {
            $footerNavigation = collect(Cache::remember(
                'ciak-footer-navigation:' . $store->id . ':' . $locale,
                now()->addMinutes(30),
                fn () => app(CatalogRepository::class)->getNavigationTree($store, $locale)->all()
            ));
        } catch (Throwable) {
            $footerNavigation = collect();
        }
    }
@endphp

<footer class="ciak-footer">
    <div class="ciak-footer-main">
        <div class="ciak-footer-brand">
            <a href="{{ route('storefront.home', $contextParams) }}" aria-label="{{ $storeName }}">
                @if($storeLogo)
                    <img src="{{ $storeLogo }}" alt="{{ $storeName }}" loading="lazy" decoding="async">
                @else
                    <span>CIAK</span>
                @endif
            </a>
            <p>{{ __('Agende e taccuini dal design minimal, pensati per organizzare idee e progetti ogni giorno.') }}</p>
            <div class="ciak-footer-social" aria-label="{{ __('Social') }}">
                <a href="#" aria-label="Instagram"><i data-lucide="instagram" aria-hidden="true"></i></a>
                <a href="#" aria-label="Facebook"><i data-lucide="facebook" aria-hidden="true"></i></a>
                <a href="#" aria-label="Pinterest"><i data-lucide="circle" aria-hidden="true"></i></a>
            </div>
        </div>

        <nav class="ciak-footer-nav" aria-label="{{ __('Prodotti') }}">
            <strong>{{ __('Prodotti') }}</strong>
            @foreach($footerNavigation->filter(fn ($item) => !empty($item['slug']))->take(4) as $category)
                <a href="{{ route('storefront.category.show', array_merge(['slug' => $category['slug']], $contextParams)) }}">
                    {{ $category['label'] ?? $category['code'] }}
                </a>
            @endforeach
            <a href="{{ route('storefront.catalog.index', $contextParams) }}">{{ __('Tutto il catalogo') }}</a>
        </nav>

        <nav class="ciak-footer-nav" aria-label="{{ __('Informazioni') }}">
            <strong>{{ __('Informazioni') }}</strong>
            <a href="{{ route('storefront.home', $contextParams) }}">{{ __('Chi siamo') }}</a>
            <a href="{{ route('storefront.catalog.index', $contextParams) }}">{{ __('Spedizioni') }}</a>
            <a href="{{ route('storefront.catalog.index', $contextParams) }}">{{ __('Resi e rimborsi') }}</a>
            <a href="{{ route('storefront.catalog.index', $contextParams) }}">{{ __('FAQ') }}</a>
            @if($companyEmail)<a href="mailto:{{ $companyEmail }}">{{ __('Contatti') }}</a>@endif
        </nav>

        <nav class="ciak-footer-nav" aria-label="{{ __('Servizio clienti') }}">
            <strong>{{ __('Servizio clienti') }}</strong>
            @auth('customer')
                <a href="{{ route('storefront.account.index', $contextParams) }}">{{ __('Il mio account') }}</a>
            @else
                <a href="{{ route('storefront.login', $contextParams) }}">{{ __('Il mio account') }}</a>
            @endauth
            <a href="{{ route('storefront.cart.index', $contextParams) }}">{{ __('Ordini') }}</a>
            @if(Route::has('storefront.wishlist.index'))
                <a href="{{ route('storefront.wishlist.index', $contextParams) }}">{{ __('Wishlist') }}</a>
            @endif
            @if($companyPhone)<a href="tel:{{ preg_replace('/\s+/', '', (string) $companyPhone) }}">{{ $companyPhone }}</a>@endif
        </nav>

        <div class="ciak-footer-newsletter">
            <strong>{{ __('Newsletter') }}</strong>
            <p>{{ __('Iscriviti per ricevere novità e promozioni esclusive.') }}</p>
            <form action="#" method="POST" class="ciak-newsletter-form">
                <label for="ciak-newsletter-email" class="visually-hidden">{{ __('La tua email') }}</label>
                <input id="ciak-newsletter-email" type="email" placeholder="{{ __('La tua email') }}">
                <button type="submit" aria-label="{{ __('Iscriviti') }}">
                    <i data-lucide="arrow-right" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </div>

    <div class="ciak-footer-bottom">
        <span>© {{ date('Y') }} {{ $storeName }}. {{ __('Tutti i diritti riservati.') }}</span>
        <nav aria-label="{{ __('Link legali') }}">
            <a href="#">{{ __('Privacy Policy') }}</a>
            <a href="#">{{ __('Cookie Policy') }}</a>
            <a href="#">{{ __('Termini e condizioni') }}</a>
        </nav>
    </div>
</footer>
