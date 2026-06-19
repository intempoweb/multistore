@extends($storefrontLayout)

@section('title', ($store->name ?? 'Fipell') . ' - Soluzioni elettriche per professionisti')

@section('content')
@php
    $listingCardsByProductSku = collect($listingCardsByProductSku ?? []);
    $productsCollection = collect($products?->items() ?? []);

    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];

    $homeUrl = route('storefront.home', $contextParams);
    $catalogUrl = Route::has('storefront.catalog.index')
        ? route('storefront.catalog.index', $contextParams)
        : $homeUrl;

    $quickOrderEnabled = ($store?->is_b2b ?? false) && auth('customer')->check() && Route::has('storefront.cart.import');

    $documentsUrl = Route::has('storefront.account.documents.index')
        ? route('storefront.account.documents.index', $contextParams)
        : url('/account/documents');

    $accountUrl = Route::has('storefront.account.index')
        ? route('storefront.account.index', $contextParams)
        : $homeUrl;

    $rootCategories = collect();

    try {
        $rootCategories = app(\App\Repositories\Storefront\CatalogRepository::class)
            ->getRootCategories($store, $locale ?? app()->getLocale())
            ->take(6)
            ->values();
    } catch (\Throwable $exception) {
        $rootCategories = collect();
    }

    $categoryIconMap = [
        'materiale' => 'fa-regular fa-plug',
        'installazione' => 'fa-regular fa-plug',
        'quadri' => 'fa-regular fa-tablet-button',
        'centralini' => 'fa-regular fa-tablet-button',
        'illuminazione' => 'fa-regular fa-lightbulb',
        'lampade' => 'fa-regular fa-lightbulb',
        'cavi' => 'fa-solid fa-cable-car',
        'cablaggio' => 'fa-solid fa-cable-car',
        'domotica' => 'fa-solid fa-shield-halved',
        'sicurezza' => 'fa-solid fa-shield-halved',
        'strumentazione' => 'fa-solid fa-calculator',
        'misura' => 'fa-solid fa-calculator',
        'interruttori' => 'fa-solid fa-toggle-on',
        'prese' => 'fa-regular fa-square',
    ];

    $iconForCategory = static function (string $label) use ($categoryIconMap): string {
        $normalized = \Illuminate\Support\Str::lower($label);

        foreach ($categoryIconMap as $needle => $icon) {
            if (str_contains($normalized, $needle)) {
                return $icon;
            }
        }

        return 'fa-solid fa-bolt';
    };

    $featuredProducts = $productsCollection->take(4)->values();
    $heroProducts = $productsCollection->filter(fn ($product) => !empty($product->main_image_url))->take(4)->values();
@endphp

<div class="fipell-home fipell-home-mockup">

    <section class="fipell-home-hero">
        <div class="fipell-home-hero-copy">
            <span class="fipell-home-pill">B2B</span>

            <h1>Soluzioni elettriche<br>per professionisti</h1>

            <p>
                Ampio catalogo, disponibilità reale e prezzi dedicati per la tua attività.
            </p>

            <a href="{{ $catalogUrl }}" class="btn fipell-home-primary-btn">
                Scopri il catalogo
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="fipell-home-hero-visual" aria-hidden="true">
            <span class="fipell-home-dotted-line"></span>

            @forelse($heroProducts as $product)
                <div class="fipell-home-hero-product fipell-home-hero-product-{{ $loop->iteration }}">
                    <img src="{{ $product->main_image_url }}" alt="">
                </div>
            @empty
                <div class="fipell-home-electric-placeholder">
                    <i class="fa-solid fa-toggle-on"></i>
                    <i class="fa-regular fa-square"></i>
                    <i class="fa-solid fa-bolt"></i>
                    <i class="fa-solid fa-cable-car"></i>
                </div>
            @endforelse
        </div>
    </section>

    <section class="fipell-home-benefits" aria-label="Vantaggi">
        <div class="fipell-home-benefit">
            <i class="fa-solid fa-truck-fast"></i>
            <div>
                <strong>Spedizioni rapide</strong>
                <span>Consegne puntuali in tutta Italia</span>
            </div>
        </div>

        <a href="{{ $documentsUrl }}" class="fipell-home-benefit">
            <i class="fa-regular fa-file-lines"></i>
            <div>
                <strong>Documenti sempre disponibili</strong>
                <span>Fatture, DDT e statistiche ordini</span>
            </div>
        </a>

        <div class="fipell-home-benefit">
            <i class="fa-solid fa-tags"></i>
            <div>
                <strong>Prezzi dedicati</strong>
                <span>Listini personalizzati per cliente</span>
            </div>
        </div>

        <div class="fipell-home-benefit">
            <i class="fa-solid fa-headset"></i>
            <div>
                <strong>Supporto dedicato</strong>
                <span>Assistenza rapida e qualificata</span>
            </div>
        </div>
    </section>

    @if($rootCategories->isNotEmpty())
        <section class="fipell-home-section fipell-home-categories">
            <div class="fipell-home-section-head">
                <h2>Categorie principali</h2>

                <a href="{{ $catalogUrl }}" class="fipell-home-link">
                    Vedi tutte le categorie
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <div class="fipell-home-category-grid">
                @foreach($rootCategories as $category)
                    @php
                        $categorySlug = $category['slug'] ?? null;
                        $categoryLabel = $category['label'] ?? $category['code'] ?? 'Categoria';
                    @endphp

                    @if($categorySlug)
                        <a
                            href="{{ route('storefront.category.show', array_merge(['slug' => $categorySlug], $contextParams)) }}"
                            class="fipell-home-category-card"
                        >
                            <i class="{{ $iconForCategory($categoryLabel) }}"></i>
                            <span>{{ $categoryLabel }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    <section class="fipell-home-section fipell-home-featured">
        <div class="fipell-home-section-head">
            <h2>Prodotti in evidenza</h2>

            <a href="{{ $catalogUrl }}" class="fipell-home-link">
                Vedi tutti i prodotti
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        @if($featuredProducts->isEmpty())
            <div class="fipell-home-empty">
                Nessun prodotto disponibile al momento.
            </div>
        @else
            <div class="fipell-home-product-grid">
                @foreach($featuredProducts as $product)
                    @php
                        $listingCard = collect($listingCardsByProductSku->get((string) $product->sku, []));
                    @endphp

                    <div class="fipell-home-product-card">
                        @include('storefront.base.partials.product-card', [
                            'product' => $product,
                            'listingCard' => $listingCard,
                        ])
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="fipell-home-cta">
        <div class="fipell-home-cta-icon">
            <i class="fa-solid fa-percent"></i>
        </div>

        <div class="fipell-home-cta-copy">
            <h2>Listini personalizzati e sconti a te dedicati</h2>
            <p>Accedi per vedere i tuoi prezzi e le promozioni attive.</p>
        </div>

        <a href="{{ $accountUrl }}" class="btn fipell-home-primary-btn">
            Accedi all’area cliente
            <i class="fa-solid fa-arrow-right"></i>
        </a>
    </section>

</div>
@endsection
