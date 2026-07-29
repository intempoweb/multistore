@php
    $readyLogo = trim((string) ($storeLogo ?? ''));

    if ($readyLogo === '') {
        $readyLogo = media_url(config('mail.storefront.stores.ready.logo'));
    }
    $footerEmail = trim((string) ($storeEmail ?? $companyEmail ?? 'info@ready-to.it'));
    $footerPhone = trim((string) ($companyPhone ?? $storePhone ?? ''));
    $footerAddress = trim((string) ($companyAddress ?? ''));
    $contacts = trim(collect([$footerPhone, $footerEmail])->filter()->implode(' · '));

    $normalizeReadyFooterItem = static function ($item) use ($contextParams) {
        $data = is_array($item) ? $item : (array) $item;
        $label = trim((string) ($data['title'] ?? $data['label'] ?? $data['name'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        $url = trim((string) ($data['url'] ?? ''));

        if ($url === '' && $slug !== '') {
            $url = route('storefront.category.show', array_merge(['slug' => $slug], $contextParams));
        }

        if ($label === '' || $url === '') {
            return null;
        }

        return [
            'label' => $label,
            'slug' => $slug,
            'url' => $url,
        ];
    };

    $footerCategories = collect($footerCategories ?? [])
        ->merge($navigationTree ?? [])
        ->merge($leftCategories ?? [])
        ->merge($rightCategories ?? [])
        ->merge($rootCategories ?? [])
        ->map($normalizeReadyFooterItem)
        ->filter()
        ->unique(fn ($category) => $category['slug'] !== '' ? $category['slug'] : $category['url'])
        ->take(8)
        ->values();

    $footerCollections = collect($intempoAreas ?? [])
        ->map($normalizeReadyFooterItem)
        ->filter()
        ->unique(fn ($category) => $category['label'].'|'.($category['slug'] !== '' ? $category['slug'] : $category['url']))
        ->values();

    if ($footerCollections->isEmpty()) {
        $footerCollections = $footerCategories->filter(function ($category) {
        $text = mb_strtolower(trim((string) (($category['label'] ?? '').' '.($category['slug'] ?? ''))));

        return str_contains($text, 'ready') || str_contains($text, 'collez');
        })->values();
    }

    $currentYear = $currentYear ?? now()->year;
@endphp

<footer class="intempo-b2c-footer ready-footer">
    <div class="intempo-b2c-service-row intempo-b2c-shell">
        <a href="{{ route('storefront.catalog.index', $contextParams) }}"><i data-lucide="shopping-bag"></i><span><strong>Shop online</strong><small>Prodotti Ready per outdoor e tempo libero</small></span></a>
        <a href="{{ route('storefront.store-locator.index', $contextParams) }}"><i data-lucide="map-pin"></i><span><strong>Punti vendita</strong><small>Trova il rivenditore piu vicino</small></span></a>
        <a href="{{ auth('customer')->check() ? route('storefront.account.index', $contextParams) : route('storefront.login', $contextParams) }}"><i data-lucide="user-round"></i><span><strong>Area personale</strong><small>Ordini, account e preferiti</small></span></a>
        @if($footerEmail !== '')
            <a href="mailto:{{ $footerEmail }}"><i data-lucide="mail"></i><span><strong>Contatti</strong><small>{{ $footerEmail }}</small></span></a>
        @endif
    </div>

    <div class="intempo-b2c-footer-main intempo-b2c-shell">
        <div class="intempo-b2c-footer-brand">
            <img src="{{ $readyLogo }}" alt="Ready">
            <p>Be smart, be ready. Accessori pratici per sport, outdoor, viaggio e tempo libero.</p>
            @if($contacts !== '')
                <small>{{ $contacts }}</small>
            @endif
            @if($footerAddress !== '')
                <small>{{ $footerAddress }}</small>
            @endif
        </div>
        <div>
            <h3>Prodotti</h3>
            @foreach($footerCategories as $category)
                <a href="{{ $category['url'] }}">{{ $category['label'] }}</a>
            @endforeach
        </div>
        <div>
            <h3>Collezioni</h3>
            @forelse($footerCollections as $category)
                <a href="{{ $category['url'] }}">{{ $category['label'] }}</a>
            @empty
                <a href="{{ route('storefront.catalog.index', $contextParams) }}">Tutte le collezioni</a>
            @endforelse
            <a href="{{ route('storefront.store-locator.index', $contextParams) }}">Punti vendita</a>
        </div>
        <div>
            <h3>Informazioni</h3>
            <a href="{{ route('storefront.catalog.index', $contextParams) }}">Catalogo</a>
            <a href="{{ route('storefront.search.index', $contextParams) }}">Cerca</a>
            @if(Route::has('storefront.privacy'))
                <a href="{{ route('storefront.privacy', $contextParams) }}">Privacy policy</a>
            @endif
            @if(Route::has('storefront.cookies'))
                <a href="{{ route('storefront.cookies', $contextParams) }}">Cookie policy</a>
            @endif
        </div>
    </div>
    <div class="intempo-b2c-footer-bottom intempo-b2c-shell">
        <span>© {{ $currentYear }} Ready</span>
        <nav class="intempo-b2c-footer-legal-links" aria-label="Link legali">
            @if(Route::has('storefront.privacy'))
                <a href="{{ route('storefront.privacy', $contextParams) }}">Privacy policy</a>
            @endif
            @if(Route::has('storefront.cookies'))
                <a href="{{ route('storefront.cookies', $contextParams) }}">Cookie policy</a>
            @endif
            @if(Route::has('storefront.shipping-returns'))
                <a href="{{ route('storefront.shipping-returns', $contextParams) }}">{{ __('legal.shipping_returns.title') }}</a>
            @endif
        </nav>
    </div>
</footer>
