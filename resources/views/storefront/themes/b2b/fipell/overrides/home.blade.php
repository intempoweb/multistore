@extends($storefrontLayout)

@section('title', ($store->name ?? 'Fipell') . ' - Ingrosso cartoleria, scuola e ufficio')

@section('content')
@php
    use App\Models\ProductCardViewModel;
    use App\Repositories\Storefront\CatalogRepository;
    use Illuminate\Support\Str;

    $listingCardsByProductSku = collect($listingCardsByProductSku ?? []);
    $productsCollection = collect($products?->items() ?? []);

    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];

    $homeUrl = route('storefront.home', $contextParams);
    $catalogUrl = Route::has('storefront.catalog.index')
        ? route('storefront.catalog.index', $contextParams)
        : $homeUrl;

    $documentsUrl = Route::has('storefront.account.documents.index')
        ? route('storefront.account.documents.index', $contextParams)
        : url('/account/documents');

    $accountUrl = Route::has('storefront.account.index')
        ? route('storefront.account.index', $contextParams)
        : $documentsUrl;

    $contextUrl = static function (?string $url) use ($agentContextId): ?string {
        if (!$url || $agentContextId === '') {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query(['agent_context' => $agentContextId]);
    };

    $rootCategories = collect();

    try {
        $rootCategories = app(CatalogRepository::class)
            ->getRootCategories($store, $locale ?? app()->getLocale())
            ->take(6)
            ->values();
    } catch (\Throwable $exception) {
        $rootCategories = collect();
    }

    $categoryIconMap = [
        'cartoleria' => 'fa-solid fa-pencil',
        'scrittura' => 'fa-solid fa-pen-nib',
        'penne' => 'fa-solid fa-pen',
        'matite' => 'fa-solid fa-pencil',
        'scuola' => 'fa-solid fa-graduation-cap',
        'didattica' => 'fa-solid fa-book-open',
        'ufficio' => 'fa-solid fa-briefcase',
        'archiviazione' => 'fa-solid fa-box-archive',
        'organizzazione' => 'fa-solid fa-folder-tree',
        'carta' => 'fa-regular fa-file-lines',
        'blocchi' => 'fa-regular fa-note-sticky',
        'informatica' => 'fa-solid fa-laptop',
        'consumabili' => 'fa-solid fa-print',
        'stampa' => 'fa-solid fa-print',
        'arredo' => 'fa-solid fa-chair',
        'modulistica' => 'fa-solid fa-clipboard-list',
        'registri' => 'fa-solid fa-book',
        'pelletteria' => 'fa-solid fa-bag-shopping',
        'calendari' => 'fa-regular fa-calendar-days',
        'agende' => 'fa-regular fa-calendar-check',
        'packaging' => 'fa-solid fa-box',
        'etichette' => 'fa-solid fa-tags',
        'colla' => 'fa-solid fa-droplet',
    ];

    $iconForCategory = static function (string $label) use ($categoryIconMap): string {
        $normalized = Str::lower($label);

        foreach ($categoryIconMap as $needle => $icon) {
            if (str_contains($normalized, $needle)) {
                return $icon;
            }
        }

        return 'fa-solid fa-layer-group';
    };

    $categoryCards = $rootCategories->map(function ($category) use ($contextParams, $catalogUrl, $iconForCategory) {
        $label = $category['label'] ?? $category['code'] ?? 'Categoria';
        $slug = $category['slug'] ?? null;
        $url = $slug && Route::has('storefront.category.show')
            ? route('storefront.category.show', array_merge(['slug' => $slug], $contextParams))
            : $catalogUrl;

        return [
            'label' => $label,
            'url' => $url,
            'icon' => $iconForCategory($label),
        ];
    });

    $productsWithImages = $productsCollection
        ->filter(fn ($product) => !empty($product->main_image_url))
        ->values();

    $heroProducts = $productsWithImages->take(4);

    if ($heroProducts->count() < 4) {
        $heroProductSkus = $heroProducts->pluck('sku')->map(fn ($sku) => (string) $sku);

        $heroProducts = $heroProducts
            ->merge(
                $productsCollection
                    ->reject(fn ($product) => $heroProductSkus->contains((string) $product->sku))
                    ->take(4 - $heroProducts->count())
            )
            ->values();
    }

    $featuredProducts = $productsWithImages->take(4);

    if ($featuredProducts->count() < 4) {
        $featuredProductSkus = $featuredProducts->pluck('sku')->map(fn ($sku) => (string) $sku);

        $featuredProducts = $featuredProducts
            ->merge(
                $productsCollection
                    ->reject(fn ($product) => $featuredProductSkus->contains((string) $product->sku))
                    ->take(4 - $featuredProducts->count())
            )
            ->values();
    }

    $formattedPrice = static function (ProductCardViewModel $card): string {
        if ($card->price === null) {
            return 'Prezzo su richiesta';
        }

        return '€ ' . number_format((float) $card->price, 2, ',', '.');
    };
@endphp

<div class="fipell-home fipell-home-mockup">

    <section class="fipell-home-hero" aria-labelledby="fipell-home-title">
        <div class="fipell-home-hero-copy">
            <span class="fipell-home-pill">B2B</span>

            <h1 id="fipell-home-title">Ingrosso cartoleria, scuola e ufficio</h1>

            <p>
                Ampio catalogo, disponibilita reale e listini dedicati per la tua attivita.
            </p>

            <a href="{{ $catalogUrl }}" class="btn fipell-home-primary-btn">
                <span>Scopri il catalogo</span>
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <div class="fipell-home-hero-visual" aria-hidden="true">
            <span class="fipell-home-dotted-line"></span>

            @forelse($heroProducts as $product)
                <span class="fipell-home-hero-product fipell-home-hero-product-{{ $loop->iteration }}">
                    @if(!empty($product->main_image_url))
                        <img src="{{ $product->main_image_url }}" alt="" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                    @else
                        <i class="{{ ['fa-solid fa-pencil', 'fa-solid fa-book-open', 'fa-solid fa-box-archive', 'fa-solid fa-print'][$loop->index] ?? 'fa-solid fa-layer-group' }}"></i>
                    @endif
                </span>
            @empty
                <div class="fipell-home-catalog-placeholder">
                    <i class="fa-solid fa-pencil"></i>
                    <i class="fa-solid fa-book-open"></i>
                    <i class="fa-solid fa-box-archive"></i>
                    <i class="fa-solid fa-print"></i>
                </div>
            @endforelse
        </div>
    </section>

    <section class="fipell-home-benefits" aria-label="Vantaggi Fipell">
        <a href="{{ $catalogUrl }}" class="fipell-home-benefit">
            <i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
            <span>
                <strong>Spedizioni rapide</strong>
                <small>Consegne puntuali in tutta Italia</small>
            </span>
        </a>

        <a href="{{ $documentsUrl }}" class="fipell-home-benefit">
            <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
            <span>
                <strong>Documenti sempre disponibili</strong>
                <small>Fatture, DDT e statistiche ordini</small>
            </span>
        </a>

        <a href="{{ $accountUrl }}" class="fipell-home-benefit">
            <i class="fa-solid fa-tag" aria-hidden="true"></i>
            <span>
                <strong>Prezzi dedicati</strong>
                <small>Listini personalizzati per cliente</small>
            </span>
        </a>

        <a href="{{ $accountUrl }}" class="fipell-home-benefit">
            <i class="fa-solid fa-headset" aria-hidden="true"></i>
            <span>
                <strong>Supporto dedicato</strong>
                <small>Assistenza rapida e qualificata</small>
            </span>
        </a>
    </section>

    <section class="fipell-home-section" aria-labelledby="fipell-categories-title">
        <div class="fipell-home-section-head">
            <h2 id="fipell-categories-title">Categorie principali</h2>

            <a href="{{ $catalogUrl }}" class="fipell-home-link">
                Vedi tutte le categorie
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        @if($categoryCards->isEmpty())
            <div class="fipell-home-empty">
                Nessuna categoria disponibile per questo account.
            </div>
        @else
            <div class="fipell-home-category-grid">
                @foreach($categoryCards as $category)
                    <a href="{{ $category['url'] }}" class="fipell-home-category-card">
                        <i class="{{ $category['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $category['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section class="fipell-home-section" aria-labelledby="fipell-products-title">
        <div class="fipell-home-section-head">
            <h2 id="fipell-products-title">Prodotti in evidenza</h2>

            <a href="{{ $catalogUrl }}" class="fipell-home-link">
                Vedi tutti i prodotti
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        @if($featuredProducts->isEmpty())
            <div class="fipell-home-empty">Nessun prodotto disponibile per questo account.</div>
        @else
            <div class="fipell-home-product-grid">
                @foreach($featuredProducts as $product)
                    @php
                        $listingCard = collect($listingCardsByProductSku->get((string) $product->sku, []));
                        $card = ProductCardViewModel::make($product, $listingCard);
                        $stockQty = $product->stock_qty !== null ? (float) $product->stock_qty : null;
                        $isAvailable = $stockQty === null || $stockQty > 0;
                    @endphp

                    <a href="{{ $contextUrl($card->productUrl) }}" class="fipell-home-product-card">
                        <span class="fipell-home-product-image">
                            @if($card->image)
                                <img src="{{ $card->image }}" alt="{{ $card->name }}" loading="lazy">
                            @else
                                <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                            @endif
                        </span>

                        <span class="fipell-home-product-copy">
                            <strong>{{ $card->name }}</strong>
                            <small>SKU {{ $card->targetSku }}</small>
                            <b>{{ $formattedPrice($card) }}</b>
                            <em class="{{ $isAvailable ? 'is-available' : 'is-unavailable' }}">
                                {{ $isAvailable ? 'Disponibile' : 'Non disponibile' }}
                            </em>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section class="fipell-home-cta" aria-labelledby="fipell-listini-title">
        <span class="fipell-home-cta-icon">
            <i class="fa-solid fa-percent" aria-hidden="true"></i>
        </span>

        <div>
            <h2 id="fipell-listini-title">Listini personalizzati e sconti a te dedicati</h2>
            <p>Accedi per vedere i tuoi prezzi e le promozioni attive.</p>
        </div>

        <a href="{{ $accountUrl }}" class="btn fipell-home-primary-btn">
            <span>Accedi all'area cliente</span>
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </a>
    </section>

</div>
@endsection
