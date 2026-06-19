@extends($storefrontLayout)

@section('title', ($store->name ?? 'Fipell') . ' - Ingrosso Cartoleria, Scuola e Ufficio')

@section('content')
@php
    $listingCardsByProductSku = collect($listingCardsByProductSku ?? []);
    $filterFacets = collect($filterFacets ?? []);
    $activeFilters = collect($activeFilters ?? []);
    $childrenCategories = collect($childrenCategories ?? []);
    $productsCollection = collect($products?->items() ?? []);

    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];

    $homeUrl = route('storefront.home', $contextParams);
    $catalogUrl = Route::has('storefront.catalog.index')
        ? route('storefront.catalog.index', $contextParams)
        : $homeUrl;

    $newProducts = $productsCollection
        ->filter(fn ($product) => (bool) ($product->flgnovita_webt01 ?? false))
        ->take(8)
        ->values();

    $featuredProducts = $productsCollection->take(8)->values();

    $rootCategories = collect();

    try {
        $rootCategories = app(\App\Repositories\Storefront\CatalogRepository::class)
            ->getRootCategories($store, $locale ?? app()->getLocale())
            ->take(8)
            ->values();
    } catch (\Throwable $exception) {
        $rootCategories = collect();
    }

    $productsTotal = $products?->total() ?? $productsCollection->count();

    $quickLinks = collect([
        [
            'label' => 'Acquisto rapido',
            'text' => 'Carica file o inserisci articoli velocemente.',
            'icon' => 'fa-solid fa-bolt',
            'url' => '#storefrontCartImport',
            'offcanvas' => true,
            'enabled' => ($store?->is_b2b ?? false) && auth('customer')->check() && Route::has('storefront.cart.import'),
        ],
        [
            'label' => 'Documenti',
            'text' => 'Consulta documenti, ordini e area amministrativa.',
            'icon' => 'fa-solid fa-file-lines',
            'url' => Route::has('storefront.account.documents.index') ? route('storefront.account.documents.index', $contextParams) : url('/account/documents'),
            'enabled' => auth('customer')->check(),
        ],
        [
            'label' => 'Preferiti',
            'text' => 'Ritrova i prodotti che acquisti più spesso.',
            'icon' => 'fa-regular fa-heart',
            'url' => Route::has('storefront.wishlist.index') ? route('storefront.wishlist.index', $contextParams) : null,
            'enabled' => Route::has('storefront.wishlist.index'),
        ],
        [
            'label' => 'Carrello',
            'text' => 'Controlla quantità e procedi con l’ordine.',
            'icon' => 'fa-solid fa-cart-shopping',
            'url' => Route::has('storefront.cart.index') ? route('storefront.cart.index', $contextParams) : null,
            'enabled' => Route::has('storefront.cart.index'),
        ],
    ])->filter(fn ($item) => $item['enabled'] && !empty($item['url']))->values();
@endphp

<div class="fipell-home">

    <section class="fipell-home-hero">
        <div class="fipell-home-hero-copy">
            <div class="fipell-home-eyebrow">
                Portale B2B
            </div>

            <h1>
                Ingrosso Cartoleria, Scuola e Ufficio
            </h1>

            <p>
                Ordina prodotti per il tuo punto vendita con listini dedicati, disponibilità aggiornate e strumenti rapidi per il riordino.
            </p>

            <div class="fipell-home-hero-actions">
                <a href="{{ $catalogUrl }}" class="btn fipell-home-primary-btn">
                    Vai al catalogo
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

                @if(($store?->is_b2b ?? false) && auth('customer')->check() && Route::has('storefront.cart.import'))
                    <button
                        type="button"
                        class="btn fipell-home-secondary-btn"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#storefrontCartImport"
                    >
                        <i class="fa-solid fa-bolt"></i>
                        Acquisto rapido
                    </button>
                @endif
            </div>
        </div>

        <div class="fipell-home-hero-panel">
            <div class="fipell-home-stat">
                <span>{{ number_format($productsTotal, 0, ',', '.') }}</span>
                <small>prodotti disponibili</small>
            </div>

            <div class="fipell-home-hero-tags">
                <span>Cartoleria</span>
                <span>Scuola</span>
                <span>Ufficio</span>
                <span>B2B</span>
            </div>
        </div>
    </section>

    @if($quickLinks->isNotEmpty())
        <section class="fipell-home-quick-grid">
            @foreach($quickLinks as $link)
                @if(!empty($link['offcanvas']))
                    <button
                        type="button"
                        class="fipell-home-quick-card"
                        data-bs-toggle="offcanvas"
                        data-bs-target="{{ $link['url'] }}"
                    >
                        <i class="{{ $link['icon'] }}"></i>
                        <strong>{{ $link['label'] }}</strong>
                        <span>{{ $link['text'] }}</span>
                    </button>
                @else
                    <a href="{{ $link['url'] }}" class="fipell-home-quick-card">
                        <i class="{{ $link['icon'] }}"></i>
                        <strong>{{ $link['label'] }}</strong>
                        <span>{{ $link['text'] }}</span>
                    </a>
                @endif
            @endforeach
        </section>
    @endif

    @if($rootCategories->isNotEmpty())
        <section class="fipell-home-section">
            <div class="fipell-home-section-head">
                <div>
                    <div class="fipell-home-eyebrow">Categorie</div>
                    <h2>Esplora il catalogo</h2>
                </div>

                <a href="{{ $catalogUrl }}" class="fipell-home-link">
                    Tutto il catalogo
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
                            <span>{{ $categoryLabel }}</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    @if($newProducts->isNotEmpty())
        <section class="fipell-home-section">
            <div class="fipell-home-section-head">
                <div>
                    <div class="fipell-home-eyebrow">Novità</div>
                    <h2>Nuovi arrivi per scuola e ufficio</h2>
                </div>

                <a href="{{ $homeUrl }}?sort=newest" class="fipell-home-link">
                    Vedi novità
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <div class="row g-3">
                @foreach($newProducts as $product)
                    @php
                        $listingCard = collect($listingCardsByProductSku->get((string) $product->sku, []));
                    @endphp

                    <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                        @include('storefront.base.partials.product-card', [
                            'product' => $product,
                            'listingCard' => $listingCard,
                        ])
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="fipell-home-section">
        <div class="fipell-home-section-head">
            <div>
                <div class="fipell-home-eyebrow">Catalogo B2B</div>
                <h2>Prodotti disponibili</h2>
            </div>

            <a href="{{ $catalogUrl }}" class="fipell-home-link">
                Vai al catalogo
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        @if($featuredProducts->isEmpty())
            <div class="fipell-home-empty">
                Nessun prodotto disponibile per questo account.
            </div>
        @else
            <div class="row g-3">
                @foreach($featuredProducts as $product)
                    @php
                        $listingCard = collect($listingCardsByProductSku->get((string) $product->sku, []));
                    @endphp

                    <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                        @include('storefront.base.partials.product-card', [
                            'product' => $product,
                            'listingCard' => $listingCard,
                        ])
                    </div>
                @endforeach
            </div>
        @endif
    </section>

</div>
@endsection