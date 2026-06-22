@extends($storefrontLayout)

@section('title', (($selectedTranslation?->name ?? $baseTranslation?->name ?? $selectedProduct->sku ?? __('Prodotto')) . ' - ' . ($store->name ?? 'CIAK')))

@section('content')
@php
    $productName = $selectedTranslation?->name
        ?? $baseTranslation?->name
        ?? $selectedProduct->sku
        ?? __('Prodotto');
    $productDescription = $selectedTranslation?->description
        ?? $baseTranslation?->description
        ?? null;
    $gallery = collect($galleryImages ?? [])
        ->filter(fn ($item) => is_array($item) && !empty($item['url']))
        ->values();
    $displayImage = $mainImage ?? $image ?? data_get($gallery->first(), 'url');
@endphp

<div
    class="product-page product-page-corporate ciak-product-page"
    data-product-page
    data-product-card
    data-product-sku="{{ $selectedProduct->sku }}"
>
    <nav aria-label="{{ __('Percorso di navigazione') }}" class="mb-4">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('storefront.home') }}" class="text-decoration-none">{{ __('Home') }}</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('storefront.catalog.index') }}" class="text-decoration-none">{{ __('Catalogo') }}</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">{{ $productName }}</li>
        </ol>
    </nav>

    <section class="row gx-4 gx-xl-5 gy-5 align-items-start product-hero product-hero-balanced">
        <div class="col-12 col-lg-6 product-gallery-column">
            <div class="d-flex flex-nowrap align-items-start gap-3 w-100 product-gallery-layout">
                @if($gallery->count() > 1)
                    <div class="flex-shrink-0 product-gallery-sidebar" data-product-gallery-thumbs>
                        @foreach($gallery as $galleryImage)
                            <button
                                type="button"
                                class="btn product-gallery-thumb d-block p-0 mb-3 {{ $loop->first ? 'active' : '' }}"
                                data-product-gallery-thumb
                                data-image-url="{{ $galleryImage['url'] }}"
                                aria-label="{{ __('Mostra immagine :number', ['number' => $loop->iteration]) }}"
                            >
                                <img
                                    src="{{ $galleryImage['url'] }}"
                                    alt="{{ $galleryImage['alt'] ?? $productName }}"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="flex-grow-1 min-w-0 product-gallery-main">
                    @if($displayImage)
                        <div
                            class="product-main-image-clean"
                            data-product-image-stage
                            data-zoom-image="{{ $displayImage }}"
                        >
                            <img
                                id="product-main-image"
                                src="{{ $displayImage }}"
                                class="product-main-image"
                                data-product-main-image
                                alt="{{ $productName }}"
                                fetchpriority="high"
                                decoding="async"
                            >
                            <div class="product-image-lens" data-product-image-lens aria-hidden="true"></div>
                        </div>
                    @else
                        <div class="ciak-product-image-empty">
                            <i class="fa-regular fa-image" aria-hidden="true"></i>
                            <span>{{ __('Immagine non disponibile') }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6 product-info-column">
            <div class="product-info-panel">
                <div class="product-code-label mb-2">
                    {{ __('Codice articolo') }}: {{ $selectedProduct->sku }}
                </div>

                <div class="product-title-price-row mb-4">
                    <h1>{{ $productName }}</h1>
                    <div class="product-title-price">
                        <span>{{ __('Prezzo') }}</span>
                        <strong
                            id="product-price-display"
                            data-base-price="{{ $effectivePrice !== null ? number_format((float) $effectivePrice, 3, '.', '') : '' }}"
                        >
                            @if($effectivePrice !== null)
                                € {{ number_format((float) $effectivePrice, 2, ',', '.') }}
                            @else
                                &mdash;
                            @endif
                        </strong>
                    </div>
                </div>

                @if($productDescription)
                    <p class="product-description-lead">{{ $productDescription }}</p>
                @endif

                <div class="ciak-product-availability {{ ($stockQty ?? 0) > 0 ? 'is-available' : 'is-unavailable' }}">
                    <span aria-hidden="true"></span>
                    {{ ($stockQty ?? 0) > 0 ? __('Disponibile') : __('Non disponibile') }}
                </div>

                @if($colorOptions->isNotEmpty() || $formatOptions->isNotEmpty())
                    <div class="ciak-product-options">
                        @if($colorOptions->isNotEmpty())
                            <div class="ciak-product-option-group">
                                <div class="ciak-product-option-label">{{ __('Colore') }}</div>
                                <div class="d-flex flex-wrap gap-3 align-items-center">
                                    @foreach($colorOptions as $option)
                                        <a
                                            href="{{ route('storefront.product.show', ['sku' => $option['sku']]) }}"
                                            class="product-color-dot {{ ($selectedColorValue === $option['value']) ? 'is-active' : '' }}"
                                            title="{{ $option['value'] }}"
                                            aria-label="{{ __('Colore :color', ['color' => $option['value']]) }}"
                                            @if($selectedColorValue === $option['value']) aria-current="true" @endif
                                            data-product-variant-link
                                        >
                                            @if(!empty($option['swatch_url']))
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
                            <div class="ciak-product-option-group">
                                <div class="ciak-product-option-label">{{ __('Formato') }}</div>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($formatOptions as $option)
                                        <a
                                            href="{{ route('storefront.product.show', ['sku' => $option['sku']]) }}"
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

                <form
                    id="product-add-to-cart-form"
                    method="POST"
                    action="{{ route('storefront.cart.add') }}"
                    data-cart-add-form
                    class="ciak-product-buy"
                >
                    @csrf
                    <input type="hidden" name="sku" value="{{ $selectedProduct->sku }}">
                    <input
                        type="hidden"
                        id="product-quantity-input"
                        name="qty"
                        value="{{ $quantityInputValue }}"
                        min="{{ $quantityMin }}"
                        step="{{ $quantityStep }}"
                        data-qty-min="{{ $quantityMin }}"
                        data-qty-step="{{ $quantityStep }}"
                        data-price-breaks='@json($selectedVariantPriceBreaks->values())'
                    >

                    <button
                        class="btn btn-primary ciak-product-add-button"
                        id="product-add-to-cart-button"
                        type="submit"
                        @if($purchaseBlocked) disabled @endif
                    >
                        <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
                        <span>{{ __('Aggiungi al carrello') }}</span>
                    </button>

                    @auth('customer')
                        <button
                            type="button"
                            class="ciak-product-wishlist {{ ($selectedProduct->is_wishlisted ?? false) ? 'is-active' : '' }}"
                            data-product-card-wishlist-toggle
                            data-wishlist-url="{{ route('storefront.wishlist.toggle') }}"
                            data-wishlist-sku="{{ $selectedProduct->sku }}"
                            aria-label="{{ ($selectedProduct->is_wishlisted ?? false) ? __('Rimuovi dai preferiti') : __('Aggiungi ai preferiti') }}"
                            aria-pressed="{{ ($selectedProduct->is_wishlisted ?? false) ? 'true' : 'false' }}"
                        >
                            <i class="{{ ($selectedProduct->is_wishlisted ?? false) ? 'fa-solid' : 'fa-regular' }} fa-heart" data-product-card-wishlist-icon aria-hidden="true"></i>
                        </button>
                    @else
                        <a
                            href="{{ route('storefront.login') }}"
                            class="ciak-product-wishlist"
                            aria-label="{{ __('Accedi per aggiungere ai preferiti') }}"
                        >
                            <i class="fa-regular fa-heart" aria-hidden="true"></i>
                        </a>
                    @endauth

                    <div class="small d-none mt-2" id="product-add-to-cart-feedback"></div>
                </form>

                <div class="ciak-product-details">
                    <h2>{{ __('Dettagli prodotto') }}</h2>
                    <div class="table-responsive">
                        <table class="table product-spec-table mb-0">
                            <tbody>
                                <tr>
                                    <th>{{ __('Codice articolo') }}</th>
                                    <td>{{ $selectedProduct->sku }}</td>
                                </tr>
                                @if(trim((string) ($selectedProduct->barcode ?? '')) !== '')
                                    <tr>
                                        <th>{{ __('Barcode') }}</th>
                                        <td>{{ $selectedProduct->barcode }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <th>{{ __('Categoria') }}</th>
                                    <td>{{ $selectedProduct->category_path_description ?? __('Non specificata') }}</td>
                                </tr>
                                @foreach($technicalRows as $item)
                                    @continue(($item['label'] ?? null) === 'SKU')
                                    <tr>
                                        <th>{{ $item['label'] }}</th>
                                        <td>{{ $item['value'] }}</td>
                                    </tr>
                                @endforeach
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
    document.querySelectorAll('[data-product-variant-link]').forEach(function (link) {
        link.addEventListener('click', function () {
            if (link.getAttribute('aria-current') !== 'true') {
                link.classList.add('disabled');
                link.setAttribute('aria-disabled', 'true');
            }
        });
    });
</script>
@endpush
@endsection
