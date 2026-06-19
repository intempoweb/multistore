@extends($storefrontLayout)

@section('title', ($store->name ?? 'Fipell') . ' - Ingrosso Cartoleria, Scuola e Ufficio')

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

    $rootCategories = collect();

    try {
        $rootCategories = app(\App\Repositories\Storefront\CatalogRepository::class)
            ->getRootCategories($store, $locale ?? app()->getLocale())
            ->take(11)
            ->values();
    } catch (\Throwable $exception) {
        $rootCategories = collect();
    }

    $categoryIconMap = [
        'cartoleria' => 'fa-solid fa-pencil',
        'scrittura' => 'fa-solid fa-pen-nib',
        'scuola' => 'fa-solid fa-graduation-cap',
        'didattica' => 'fa-solid fa-book-open',
        'archiviazione' => 'fa-solid fa-box-archive',
        'organizzazione' => 'fa-solid fa-folder-tree',
        'informatica' => 'fa-solid fa-laptop',
        'accessori' => 'fa-solid fa-keyboard',
        'consumabili' => 'fa-solid fa-print',
        'stampa' => 'fa-solid fa-print',
        'arredo' => 'fa-solid fa-chair',
        'ufficio' => 'fa-solid fa-briefcase',
        'modulistica' => 'fa-solid fa-file-lines',
        'registri' => 'fa-solid fa-clipboard-list',
        'pelletteria' => 'fa-solid fa-bag-shopping',
        'calendari' => 'fa-regular fa-calendar-days',
        'agende' => 'fa-regular fa-calendar-check',
        'ready' => 'fa-solid fa-cube',
        'packaging' => 'fa-solid fa-box',
    ];

    $iconForCategory = static function (string $label) use ($categoryIconMap): string {
        $normalized = \Illuminate\Support\Str::lower($label);

        foreach ($categoryIconMap as $needle => $icon) {
            if (str_contains($normalized, $needle)) {
                return $icon;
            }
        }

        return 'fa-solid fa-layer-group';
    };

    $productsTotal = $products?->total() ?? $productsCollection->count();

    $offerProducts = $productsCollection
        ->filter(fn ($product) => (bool) ($product->flgofferta_webt01 ?? false) || (bool) ($product->flgpromo_webt01 ?? false))
        ->take(5)
        ->values();

    if ($offerProducts->isEmpty()) {
        $offerProducts = $productsCollection->skip(8)->take(5)->values();
    }

    $newProducts = $productsCollection
        ->filter(fn ($product) => (bool) ($product->flgnovita_webt01 ?? false))
        ->take(6)
        ->values();

    $frequentProducts = $productsCollection->take(6)->values();
    $heroProducts = $productsCollection->filter(fn ($product) => !empty($product->main_image_url))->take(5)->values();

    $brandNames = $productsCollection
        ->pluck('marca_mg64')
        ->map(fn ($brand) => trim((string) $brand))
        ->filter()
        ->unique()
        ->take(10)
        ->values();

    $formatPrice = static function ($price): string {
        if ($price === null || $price === '' || !is_numeric($price)) {
            return '—';
        }

        return '€ ' . number_format((float) $price, 3, ',', '.');
    };
@endphp

<div class="fipell-home fipell-home-v2">

    <section class="fipell-home-hero-v2">
        <div class="fipell-home-hero-content">
            <div class="fipell-home-eyebrow">Portale B2B</div>

            <h1>Il partner di fiducia per la tua attività.</h1>

            <p>
                Tutto per cartoleria, scuola, ufficio e professionisti. Listini dedicati,
                disponibilità aggiornata e strumenti rapidi per riordinare in pochi secondi.
            </p>

            <div class="fipell-home-benefits">
                <div>
                    <i class="fa-solid fa-truck-fast"></i>
                    <strong>Consegna rapida</strong>
                    <span>in tutta Italia</span>
                </div>

                <div>
                    <i class="fa-solid fa-tags"></i>
                    <strong>Prezzi dedicati</strong>
                    <span>listini personalizzati</span>
                </div>

                <div>
                    <i class="fa-solid fa-file-lines"></i>
                    <strong>Documenti sempre</strong>
                    <span>disponibili online</span>
                </div>

                <div>
                    <i class="fa-solid fa-headset"></i>
                    <strong>Supporto dedicato</strong>
                    <span>per clienti B2B</span>
                </div>
            </div>

            <div class="fipell-home-hero-actions">
                <a href="{{ $catalogUrl }}" class="btn fipell-home-primary-btn">
                    Esplora il catalogo
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

                @if($quickOrderEnabled)
                    <button
                        type="button"
                        class="btn fipell-home-secondary-btn"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#storefrontCartImport"
                    >
                        I tuoi riordini
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                @endif
            </div>
        </div>

        <div class="fipell-home-hero-visual" aria-hidden="true">
            <div class="fipell-home-hero-blob"></div>

            @forelse($heroProducts as $product)
                <div class="fipell-home-hero-product fipell-home-hero-product-{{ $loop->iteration }}">
                    <img src="{{ $product->main_image_url }}" alt="">
                </div>
            @empty
                <div class="fipell-home-hero-placeholder">
                    <i class="fa-solid fa-pencil"></i>
                    <i class="fa-solid fa-book"></i>
                    <i class="fa-solid fa-print"></i>
                    <i class="fa-solid fa-box-archive"></i>
                </div>
            @endforelse
        </div>
    </section>

    @if($rootCategories->isNotEmpty())
        <section class="fipell-home-section fipell-home-categories-section">
            <div class="fipell-home-section-head">
                <div>
                    <h2>Categorie principali</h2>
                </div>

                <a href="{{ $catalogUrl }}" class="fipell-home-link">
                    Vedi tutte
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <div class="fipell-home-category-icons">
                @foreach($rootCategories as $category)
                    @php
                        $categorySlug = $category['slug'] ?? null;
                        $categoryLabel = $category['label'] ?? $category['code'] ?? 'Categoria';
                    @endphp

                    @if($categorySlug)
                        <a
                            href="{{ route('storefront.category.show', array_merge(['slug' => $categorySlug], $contextParams)) }}"
                            class="fipell-home-category-icon-card"
                        >
                            <i class="{{ $iconForCategory($categoryLabel) }}"></i>
                            <span>{{ $categoryLabel }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    <section class="fipell-home-main-grid">
        <div class="fipell-home-products-area">
            <div class="fipell-home-section-head">
                <div>
                    <h2>I tuoi prodotti acquistati più spesso</h2>
                </div>

                <a href="{{ $catalogUrl }}" class="fipell-home-link">
                    Vai al catalogo
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            @if($frequentProducts->isEmpty())
                <div class="fipell-home-empty">Nessun prodotto disponibile per questo account.</div>
            @else
                <div class="row g-3 fipell-home-product-row">
                    @foreach($frequentProducts as $product)
                        @php
                            $listingCard = collect($listingCardsByProductSku->get((string) $product->sku, []));
                        @endphp

                        <div class="col-12 col-sm-6 col-xl-4">
                            @include('storefront.base.partials.product-card', [
                                'product' => $product,
                                'listingCard' => $listingCard,
                            ])
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="fipell-home-tool-strip">
                <a href="{{ $catalogUrl }}">
                    <i class="fa-solid fa-user-lock"></i>
                    <strong>Listini personalizzati</strong>
                    <span>Condizioni dedicate alla tua azienda</span>
                </a>

                @if($quickOrderEnabled)
                    <button type="button" data-bs-toggle="offcanvas" data-bs-target="#storefrontCartImport">
                        <i class="fa-solid fa-magnifying-glass-plus"></i>
                        <strong>Ordini rapidi da SKU</strong>
                        <span>Cerca, inserisci, ordina velocemente</span>
                    </button>
                @endif

                @if($quickOrderEnabled)
                    <button type="button" data-bs-toggle="offcanvas" data-bs-target="#storefrontCartImport">
                        <i class="fa-solid fa-file-excel"></i>
                        <strong>Importa da Excel</strong>
                        <span>Carica il tuo file e aggiungi al carrello</span>
                    </button>
                @endif

                <a href="{{ $documentsUrl }}">
                    <i class="fa-solid fa-file-invoice"></i>
                    <strong>Storico ordini e fatture</strong>
                    <span>Tutto sempre a portata di mano</span>
                </a>
            </div>
        </div>

        @if($offerProducts->isNotEmpty())
            <aside class="fipell-home-offers-card">
                <div class="fipell-home-section-head compact">
                    <div>
                        <h2>Offerte del mese</h2>
                    </div>

                    <a href="{{ $homeUrl }}?sort=price_asc" class="fipell-home-link">Vedi tutte</a>
                </div>

                <div class="fipell-home-offers-list">
                    @foreach($offerProducts as $product)
                        @php
                            $listingCard = collect($listingCardsByProductSku->get((string) $product->sku, []));
                            $card = \App\Models\ProductCardViewModel::make($product, $listingCard);
                        @endphp

                        <a href="{{ $card->productUrl }}" class="fipell-home-offer-item">
                            <span class="fipell-home-offer-image">
                                @if($card->image)
                                    <img src="{{ $card->image }}" alt="{{ $card->name }}" loading="lazy">
                                @else
                                    <i class="fa-solid fa-box"></i>
                                @endif
                            </span>

                            <span class="fipell-home-offer-copy">
                                <strong>{{ $card->name }}</strong>
                                <small>SKU {{ $card->targetSku }}</small>
                                <b>{{ $card->formattedPrice() }}</b>
                            </span>
                        </a>
                    @endforeach
                </div>

                <a href="{{ $catalogUrl }}" class="fipell-home-offers-footer">
                    Tutte le offerte
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </aside>
        @endif
    </section>

    @if($newProducts->isNotEmpty())
        <section class="fipell-home-section">
            <div class="fipell-home-section-head">
                <div>
                    <h2>Novità per scuola e ufficio</h2>
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

    @if($brandNames->isNotEmpty())
        <section class="fipell-home-section fipell-home-brands-section">
            <div class="fipell-home-section-head">
                <div>
                    <h2>I nostri marchi principali</h2>
                </div>

                <a href="{{ $catalogUrl }}" class="fipell-home-link">
                    Vedi tutti i marchi
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <div class="fipell-home-brand-grid">
                @foreach($brandNames as $brand)
                    <a href="{{ route('storefront.search.index', array_merge(['q' => $brand], $contextParams)) }}">
                        {{ $brand }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

</div>
@endsection