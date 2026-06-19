@extends($storefrontLayout)

@section('title', ($store->name ?? 'Fipell') . ' - Ingrosso cartoleria, scuola e ufficio')

@section('content')
@php
    use App\Models\ProductCardViewModel;
    use App\Repositories\Storefront\CatalogRepository;

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

    $quickOrderEnabled = ($store?->is_b2b ?? false)
        && auth('customer')->check()
        && Route::has('storefront.cart.import');

    $customer = auth('customer')->user();
    $customerName = trim((string) (
        $customer?->ragsoanag_cg16
        ?? $customer?->ragsocor_cg16
        ?? collect([$customer?->nomeconnweb, $customer?->cognomeconnweb])->filter()->implode(' ')
    ));

    $productsTotal = $products?->total() ?? $productsCollection->count();

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
            ->values();
    } catch (\Throwable $exception) {
        $rootCategories = collect();
    }

    $categoryCards = $rootCategories
        ->take(6)
        ->map(function ($category) use ($contextParams, $catalogUrl) {
            $label = $category['label'] ?? $category['code'] ?? 'Categoria';
            $slug = $category['slug'] ?? null;
            $url = $slug && Route::has('storefront.category.show')
                ? route('storefront.category.show', array_merge(['slug' => $slug], $contextParams))
                : $catalogUrl;

            return [
                'label' => $label,
                'url' => $url,
            ];
        });

    $isOrderableProduct = static function ($product): bool {
        $stockQty = $product->stock_qty !== null ? (float) $product->stock_qty : null;

        return $stockQty === null || $stockQty > 0 || !((bool) ($product->no_backorder ?? false));
    };

    $priorityProducts = $productsCollection
        ->sortByDesc(function ($product) use ($isOrderableProduct) {
            return ((bool) ($product->flgofferta_webt01 ?? false) || (bool) ($product->flgpromo_webt01 ?? false) ? 100 : 0)
                + ((bool) ($product->flgnovita_webt01 ?? false) ? 40 : 0)
                + ($isOrderableProduct($product) ? 20 : 0)
                + (!empty($product->main_image_url) ? 10 : 0);
        })
        ->values();

    $heroProducts = $priorityProducts
        ->filter(fn ($product) => !empty($product->main_image_url))
        ->take(5)
        ->values();

    if ($heroProducts->count() < 5) {
        $heroProductSkus = $heroProducts->pluck('sku')->map(fn ($sku) => (string) $sku);

        $heroProducts = $heroProducts
            ->merge(
                $priorityProducts
                    ->reject(fn ($product) => $heroProductSkus->contains((string) $product->sku))
                    ->take(5 - $heroProducts->count())
            )
            ->values();
    }

    $featuredProducts = $priorityProducts->take(4)->values();

    $formattedPrice = static function (ProductCardViewModel $card): string {
        if ($card->price === null) {
            return 'Prezzo su richiesta';
        }

        return '€ ' . number_format((float) $card->price, 2, ',', '.');
    };

    $productAvailability = static function ($product, ProductCardViewModel $card): array {
        $variantStock = $card->selectedVariant['stock_qty'] ?? null;
        $stockQty = $variantStock !== null
            ? (float) $variantStock
            : ($product->stock_qty !== null ? (float) $product->stock_qty : null);
        $noBackorder = (bool) ($product->no_backorder ?? false);

        if ($stockQty === null) {
            return ['class' => 'is-available', 'label' => 'Ordinabile'];
        }

        if ($stockQty > 0) {
            return ['class' => 'is-available', 'label' => 'Disponibile'];
        }

        if (!$noBackorder) {
            return ['class' => 'is-orderable', 'label' => 'Ordinabile'];
        }

        return ['class' => 'is-unavailable', 'label' => 'Non disponibile'];
    };
@endphp

<div class="fipell-home">

    <section class="fipell-home-hero" aria-labelledby="fipell-home-title">
        <div class="fipell-home-hero-copy">
            <h1 id="fipell-home-title">Ingrosso cartoleria, scuola e ufficio</h1>

            <p>
                @if($customerName !== '')
                    {{ $customerName }}, il tuo catalogo B2B e pronto con disponibilita e listini dedicati.
                @else
                    Il tuo catalogo B2B e pronto con disponibilita e listini dedicati.
                @endif
            </p>

            <a href="{{ $catalogUrl }}" class="btn fipell-home-primary-btn">
                <span>Vai al catalogo</span>
            </a>
        </div>

        <div class="fipell-home-hero-panel">
            <div class="fipell-home-hero-products" aria-label="Prodotti in catalogo">
                @forelse($heroProducts as $product)
                    @php
                        $listingCard = collect($listingCardsByProductSku->get((string) $product->sku, []));
                        $card = ProductCardViewModel::make($product, $listingCard);
                    @endphp

                    <a href="{{ $contextUrl($card->productUrl) }}" class="fipell-home-hero-product">
                        @if($card->image)
                            <img src="{{ $card->image }}" alt="{{ $card->name }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                        @else
                            <i class="fa-regular fa-square" aria-hidden="true"></i>
                        @endif
                    </a>
                @empty
                    <span class="fipell-home-hero-product">
                        <i class="fa-regular fa-square" aria-hidden="true"></i>
                    </span>
                @endforelse
            </div>

            <div class="fipell-home-hero-stats" aria-label="Riepilogo catalogo">
                <span>
                    <strong>{{ number_format($productsTotal, 0, ',', '.') }}</strong>
                    <small>Prodotti</small>
                </span>

                <span>
                    <strong>{{ number_format($rootCategories->count(), 0, ',', '.') }}</strong>
                    <small>Categorie</small>
                </span>

                <span>
                    <strong>{{ $featuredProducts->count() }}</strong>
                    <small>In evidenza</small>
                </span>
            </div>
        </div>
    </section>

    <section class="fipell-home-actions" aria-label="Azioni rapide">
        <a href="{{ $catalogUrl }}" class="fipell-home-action">
            <i class="fa-regular fa-rectangle-list" aria-hidden="true"></i>
            <span>
                <strong>Catalogo</strong>
                <small>Prodotti, disponibilita e prezzi cliente</small>
            </span>
        </a>

        <a href="{{ $documentsUrl }}" class="fipell-home-action">
            <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
            <span>
                <strong>Documenti</strong>
                <small>Fatture, DDT e statistiche ordini</small>
            </span>
        </a>

        @if($quickOrderEnabled)
            <button
                type="button"
                class="fipell-home-action"
                data-bs-toggle="offcanvas"
                data-bs-target="#storefrontCartImport"
                aria-controls="storefrontCartImport"
            >
                <i class="fa-regular fa-keyboard" aria-hidden="true"></i>
                <span>
                    <strong>Acquisto rapido</strong>
                    <small>Importa righe ordine da file</small>
                </span>
            </button>
        @endif

        <a href="{{ $accountUrl }}" class="fipell-home-action">
            <i class="fa-regular fa-user" aria-hidden="true"></i>
            <span>
                <strong>Account</strong>
                <small>Dati cliente e storico attivita</small>
            </span>
        </a>
    </section>

    <section class="fipell-home-section" aria-labelledby="fipell-categories-title">
        <div class="fipell-home-section-head">
            <h2 id="fipell-categories-title">Categorie principali</h2>

            <a href="{{ $catalogUrl }}" class="fipell-home-link">
                {{ $rootCategories->count() > 6 ? 'Vedi tutte le categorie' : 'Vai al catalogo' }}
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
                        <i class="fa-regular fa-folder" aria-hidden="true"></i>
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
                {{ $productsTotal > 0 ? 'Vedi tutti i prodotti' : 'Vai al catalogo' }}
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
                        $availability = $productAvailability($product, $card);
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
                            <em class="{{ $availability['class'] }}">
                                {{ $availability['label'] }}
                            </em>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section class="fipell-home-cta" aria-labelledby="fipell-listini-title">
        <div>
            <h2 id="fipell-listini-title">Continua a lavorare sul tuo catalogo riservato</h2>
            <p>
                {{ number_format($productsTotal, 0, ',', '.') }} prodotti disponibili
                @if($rootCategories->isNotEmpty())
                    in {{ number_format($rootCategories->count(), 0, ',', '.') }} categorie
                @endif
                con i tuoi prezzi cliente.
            </p>
        </div>

        <div class="fipell-home-cta-actions">
            @if($quickOrderEnabled)
                <button
                    type="button"
                    class="btn fipell-home-secondary-btn"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#storefrontCartImport"
                    aria-controls="storefrontCartImport"
                >
                    <i class="fa-regular fa-keyboard" aria-hidden="true"></i>
                    <span>Acquisto rapido</span>
                </button>
            @endif

            <a href="{{ $catalogUrl }}" class="btn fipell-home-primary-btn">
                <i class="fa-regular fa-rectangle-list" aria-hidden="true"></i>
                <span>Vai al catalogo</span>
            </a>
        </div>
    </section>

</div>
@endsection
