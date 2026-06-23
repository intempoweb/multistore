@extends($storefrontLayout)

@section('title', (($selectedTranslation?->name ?? $selectedProduct->sku ?? 'Prodotto') . ' - ' . ($store->name ?? 'Store')))

@section('content')
@php
    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];
    $isB2cProduct = !((bool) ($store?->is_b2b ?? false));
    $priceDecimals = $isB2cProduct ? 2 : 3;
@endphp
<div class="product-page product-page-corporate" data-product-page>
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('storefront.home', $contextParams) }}" class="text-decoration-none">Home</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="text-decoration-none">Catalogo</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">
                {{ $selectedProduct->sku }}
            </li>
        </ol>
    </nav>

    <section class="row gx-4 gx-xl-5 gy-5 align-items-start product-hero product-hero-balanced">
        <div class="col-12 col-lg-6 product-gallery-column">
            <div class="d-flex flex-nowrap align-items-start gap-3 w-100 product-gallery-layout">
                @if($galleryImages->count() > 1)
                    <div class="flex-shrink-0 product-gallery-sidebar" data-product-gallery-thumbs>
                        @foreach($galleryImages as $galleryImage)
                            <button
                                type="button"
                                class="btn product-gallery-thumb d-block p-0 mb-3"
                                data-product-gallery-thumb
                                data-image-url="{{ $galleryImage['url'] }}"
                                aria-label="Mostra immagine prodotto"
                            >
                                <img
                                    src="{{ $galleryImage['url'] }}"
                                    alt="{{ $galleryImage['alt'] ?? ($selectedTranslation?->name ?? $selectedProduct->sku) }}"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="flex-grow-1 min-w-0 product-gallery-main">
                    @if($mainImage)
                        <div
                            class="product-main-image-clean"
                            data-product-image-stage
                            data-zoom-image="{{ $mainImage }}"
                        >
                            <img
                                id="product-main-image"
                                src="{{ $mainImage }}"
                                class="product-main-image"
                                data-product-main-image
                                alt="{{ $selectedTranslation?->name ?? $baseTranslation?->name ?? $selectedProduct->sku }}"
                                fetchpriority="high"
                                decoding="async"
                            >

                            <div class="product-image-lens" data-product-image-lens aria-hidden="true"></div>
                        </div>
                    @else
                        <div class="bg-white border rounded-4 d-flex flex-column align-items-center justify-content-center text-muted py-5">
                            <i class="fa-solid fa-image fa-2x mb-3"></i>
                            <div class="small">Nessuna immagine prodotto</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6 product-info-column">
            <div class="product-info-panel">
                <div class="text-uppercase text-muted small fw-semibold mb-2 product-code-label">
                    Codice articolo: {{ $selectedProduct->sku }}
                </div>

                <div class="row g-3 align-items-start mb-4 product-title-price-row">
                    <div class="col-12 col-md">
                        <h1 class="display-6 fw-bold mb-0">
                            {{ $selectedTranslation?->name ?? $baseTranslation?->name ?? $selectedProduct->sku }}
                        </h1>
                    </div>

                    <div class="col-12 col-md-auto product-title-price text-md-end">
                        <div class="text-muted small mb-1">Prezzo unitario</div>
                        <div
                            class="h3 fw-bold mb-0"
                            id="product-price-display"
                            data-base-price="{{ $effectivePrice !== null ? number_format((float) $effectivePrice, $priceDecimals, '.', '') : '' }}"
                            data-price-breaks='@json($selectedVariantPriceBreaks->values())'
                        >
                            @if($effectivePrice !== null)
                                € {{ number_format((float) $effectivePrice, $priceDecimals, ',', '.') }}
                            @else
                                —
                            @endif
                        </div>

                        @if(!$isB2cProduct && $selectedVariantPriceBreaks->count() > 1)
                            <div class="small text-muted mt-1" id="product-price-note">
                                Prezzo calcolato per quantità: {{ number_format((float) $quantityInputValue, 0, ',', '.') }}
                            </div>
                        @endif
                    </div>
                </div>

                @if($selectedTranslation?->description || $baseTranslation?->description)
                    <p class="lead text-body mb-4 product-description-lead">
                        {{ $selectedTranslation?->description ?? $baseTranslation?->description }}
                    </p>
                @endif

                @if($colorOptions->isNotEmpty() || $formatOptions->isNotEmpty())
                    <div class="row g-4 mb-4">
                        @if($colorOptions->isNotEmpty())
                            <div class="col-12 col-md-7">
                                <div class="text-muted small fw-semibold mb-2">Colore</div>
                                <div class="d-flex flex-wrap gap-3 align-items-center">
                                    @foreach($colorOptions as $option)
                                        <a
                                            href="{{ route('storefront.product.show', array_merge(['sku' => $option['sku']], $contextParams)) }}"
                                            class="product-color-dot {{ ($selectedColorValue === $option['value']) ? 'is-active' : '' }}"
                                            title="{{ $option['value'] }}"
                                            aria-label="{{ $option['value'] }}"
                                            @if($selectedColorValue === $option['value']) aria-current="true" @endif
                                            data-product-variant-link
                                        >
                                            @if($option['swatch_url'])
                                                <img src="{{ $option['swatch_url'] }}" alt="{{ $option['value'] }}" loading="lazy" decoding="async">
                                            @else
                                                <span>{{ mb_substr($option['value'], 0, 1) }}</span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($formatOptions->isNotEmpty())
                            <div class="col-12 col-md-5 text-md-end">
                                <div class="text-muted small fw-semibold mb-2">Formato</div>
                                <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                                    @foreach($formatOptions as $option)
                                        <a
                                            href="{{ route('storefront.product.show', array_merge(['sku' => $option['sku']], $contextParams)) }}"
                                            class="btn btn-sm {{ ($selectedFormatValue === $option['value']) ? 'btn-dark' : 'btn-outline-secondary' }}"
                                            @if($selectedFormatValue === $option['value']) aria-current="true" @endif
                                            data-product-variant-link
                                        >
                                            {{ $option['value'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="product-availability-row mb-4">
                    <div class="text-muted small mb-1">Disponibilità</div>
                    <div class="fw-semibold {{ (($stockQty ?? 0) > 0) ? 'text-success' : (($stockQty ?? null) === null ? 'text-muted' : 'text-danger') }}">
                        {{ $stockLabel }}
                        @if(!$isB2cProduct && $stockDisplay !== null)
                            <span class="text-body-secondary fw-normal">({{ $stockDisplay }} pz)</span>
                        @endif
                    </div>
                </div>

                @if(!$isB2cProduct && $selectedVariantPriceBreaks->count() > 1)
                    <div class="product-tier-prices mb-4">
                        <div class="product-tier-prices-title">Prezzi per quantità</div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 product-tier-prices-table">
                                <tbody>
                                    @foreach($selectedVariantPriceBreaks as $tier)
                                        <tr>
                                            <td colspan="2">
                                                Da {{ number_format((float) ($tier['qty_from'] ?? 0), 0, ',', '.') }}
                                                @if(isset($tier['qty_to']) && $tier['qty_to'] !== null)
                                                    a {{ number_format((float) $tier['qty_to'], 0, ',', '.') }} pz
                                                @else
                                                    pz in su
                                                @endif
                                            </td>
                                            <td class="text-end fw-semibold">
                                                @if(($tier['price'] ?? null) !== null)
                                                    € {{ number_format((float) $tier['price'], $priceDecimals, ',', '.') }} cad.
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <form
                    id="product-add-to-cart-form"
                    method="POST"
                    action="{{ route('storefront.cart.add', $contextParams) }}"
                    data-cart-add-form
                    class="mb-4"
                >
                    @csrf
                    @if($agentContextId !== '')
                        <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                    @endif

                    <input type="hidden" name="sku" value="{{ $selectedProduct->sku }}">

                    @if(!$isB2cProduct)
                        <div class="small text-muted mb-2">
                            Minimo ordine: <strong>{{ number_format($quantityMin, 0, ',', '.') }}</strong>
                            @if($showPackMultiple)
                                · Multipli di <strong>{{ number_format($packMultiple, 0, ',', '.') }}</strong>
                            @endif
                            @if($quantityMax !== null)
                                · Max ordinabile <strong>{{ number_format($quantityMax, 0, ',', '.') }}</strong>
                            @endif
                        </div>
                    @endif

                    <div class="row g-2 align-items-end product-buy-row">
                        @if($isB2cProduct)
                            <input
                                type="hidden"
                                id="product-quantity-input"
                                name="qty"
                                min="{{ $quantityMin }}"
                                @if($quantityMax !== null) max="{{ $quantityMax }}" @endif
                                step="{{ $quantityStep }}"
                                value="{{ $quantityInputValue }}"
                                data-price-breaks='@json($selectedVariantPriceBreaks->values())'
                                @if($purchaseBlocked) disabled @endif
                            >
                        @else
                            <div class="col-5 col-sm-3">
                                <label for="product-quantity-input" class="form-label small fw-semibold mb-1">Quantità</label>
                                <input
                                    type="number"
                                    class="form-control"
                                    id="product-quantity-input"
                                    name="qty"
                                    min="{{ $quantityMin }}"
                                    @if($quantityMax !== null) max="{{ $quantityMax }}" @endif
                                    step="{{ $quantityStep }}"
                                    value="{{ $quantityInputValue }}"
                                    data-price-breaks='@json($selectedVariantPriceBreaks->values())'
                                    @if($purchaseBlocked) disabled @endif
                                >
                            </div>
                        @endif

                        <div class="{{ $isB2cProduct ? 'col-12 col-sm-9' : 'col-7 col-sm-6' }} d-grid">
                            <button
                                class="btn btn-primary"
                                id="product-add-to-cart-button"
                                type="submit"
                                @if($purchaseBlocked) disabled @endif
                            >
                                <i class="fa-solid fa-cart-shopping me-2"></i>
                                Aggiungi al carrello
                            </button>
                        </div>
                    </div>

                    <div class="small d-none mt-2" id="product-add-to-cart-feedback"></div>

                    @if($purchaseBlocked)
                        <div class="alert alert-warning mt-3 mb-0">
                            Questo prodotto non è ordinabile con la disponibilità attuale.
                        </div>
                    @endif
                </form>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a href="{{ route('storefront.cart.index', $contextParams) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-cart-shopping me-1"></i>
                        Vai al carrello
                    </a>

                    @if(Route::has('storefront.catalog.index'))
                        <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="btn btn-outline-secondary btn-sm">
                            Continua acquisti
                        </a>
                    @endif
                </div>

                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 fw-bold mb-0">Scheda prodotto</h2>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle product-spec-table mb-0">
                            <tbody>
                                <tr>
                                    <th>Codice articolo</th>
                                    <td class="text-end">{{ $selectedProduct->sku }}</td>
                                </tr>

                                @if(trim((string) ($selectedProduct->barcode ?? '')) !== '')
                                    <tr>
                                        <th>Barcode</th>
                                        <td class="text-end">{{ $selectedProduct->barcode }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <th>Categoria</th>
                                    <td class="text-end">{{ $selectedProduct->category_path_description ?? '—' }}</td>
                                </tr>

                                {{-- <tr>
                                    <th>Unità</th>
                                    <td class="text-end">{{ $selectedProduct->unit ?? '—' }}</td>
                                </tr> --}}

                                @forelse($technicalRows as $item)
                                    @continue(($item['label'] ?? null) === 'SKU')
                                    <tr>
                                        <th>{{ $item['label'] }}</th>
                                        <td class="text-end">{{ $item['value'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-muted small">
                                            Nessun attributo tecnico collegato a questo prodotto.
                                        </td>
                                    </tr>
                                @endforelse
                                @if($selectedProduct->comparisons?->isNotEmpty())
                                    <tr class="product-spec-accordion-row">
                                        <th>
                                            <button
                                                class="product-spec-accordion-toggle collapsed"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#product-comparisons-collapse"
                                                aria-expanded="false"
                                                aria-controls="product-comparisons-collapse"
                                            >
                                                <span>Articoli comparativi</span>
                                            </button>
                                        </th>
                                        <td>
                                            <button
                                                class="product-spec-accordion-action collapsed"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#product-comparisons-collapse"
                                                aria-expanded="false"
                                                aria-controls="product-comparisons-collapse"
                                            >
                                                <i class="fa-solid fa-chevron-down ms-auto" aria-hidden="true"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="collapse product-comparisons-collapse-row" id="product-comparisons-collapse">
                                        <td colspan="2">
                                            <div class="product-comparisons-panel">
                                                @foreach($selectedProduct->comparisons as $comparison)
                                                    <div class="product-comparison-item">
                                                        <div class="product-comparison-source">
                                                            {{ strtoupper((string) ($comparison->source ?? 'Comparativo')) }}
                                                        </div>
                                                        <div class="product-comparison-sku">
                                                            {{ $comparison->comparison_sku }}
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@push('scripts')
    <script>
        (function () {
            const variantLinks = document.querySelectorAll('[data-product-variant-link]');

            variantLinks.forEach(function (link) {
                link.addEventListener('click', function () {
                    if (link.getAttribute('aria-current') === 'true') {
                        return;
                    }

                    link.classList.add('disabled');
                    link.setAttribute('aria-disabled', 'true');
                });
            });
        })();
    </script>
@endpush
@endsection
