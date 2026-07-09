@extends($storefrontLayout)

@section('title', (($selectedTranslation?->name ?? $selectedProduct->sku ?? __('themes_b2c.product.product')) . ' - ' . ($store->name ?? __('Store'))))

@section('content')
@php
    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];
    $b2cThemeCodes = ['ciak', 'intemposhop', 'tekniko', 'ready'];
    $storeTheme = trim((string) ($store?->theme ?? ''));
    $siteType = (int) ($selectedProduct?->site_type ?? $store?->erp_site_code ?? 0);
    $isB2cProduct = !((bool) ($store?->is_b2b ?? false))
        || $siteType !== 1
        || in_array($storeTheme, $b2cThemeCodes, true);
    $priceDecimals = $isB2cProduct ? 2 : 3;
    $availabilityStockQty = $stockQty ?? null;
    $isBackorderOrderable = $availabilityStockQty !== null
        && (float) $availabilityStockQty <= 0
        && (bool) ($canAddToCart ?? false)
        && !(bool) ($purchaseBlocked ?? false);
    $availabilityLabel = $isBackorderOrderable
        ? ($isB2cProduct ? __('themes_b2c.product.in_stock') : __('themes_b2c.product.orderable'))
        : $stockLabel;
    $availabilityClass = $isBackorderOrderable
        ? ($isB2cProduct ? 'text-success' : 'text-warning')
        : ($stockClass ?? (((float) ($availabilityStockQty ?? 0) > 0) ? 'text-success' : ($availabilityStockQty === null ? 'text-muted' : 'text-danger')));
    $availabilityHint = $isBackorderOrderable && !$isB2cProduct
        ? __('themes_b2c.product.backorder_soon_hint')
        : ($stockHint ?? null);
@endphp
<div class="product-page product-page-corporate" data-product-page>
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('storefront.home', $contextParams) }}" class="text-decoration-none">{{ __('Home') }}</a>
            </li>
            <li class="breadcrumb-item">
                <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="text-decoration-none">{{ __('Catalogo') }}</a>
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
                    <div class="flex-shrink-0 product-gallery-sidebar-wrap {{ $galleryImages->count() > 4 ? 'has-gallery-controls' : '' }}" data-product-gallery-thumbs-wrap>
                        @if($galleryImages->count() > 4)
                            <button
                                type="button"
                                class="product-gallery-scroll-control is-prev"
                                data-product-gallery-scroll="prev"
                                aria-label="{{ __('themes_b2c.product.previous_image') }}"
                            >
                                <i class="fa-solid fa-chevron-up" aria-hidden="true"></i>
                            </button>
                        @endif

                        <div class="product-gallery-sidebar" data-product-gallery-thumbs>
                            @foreach($galleryImages as $galleryImage)
                                <button
                                    type="button"
                                    class="btn product-gallery-thumb d-block p-0 mb-3"
                                    data-product-gallery-thumb
                                    data-image-url="{{ $galleryImage['url'] }}"
                                    aria-label="{{ __('themes_b2c.product.show_product_image') }}"
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

                        @if($galleryImages->count() > 4)
                            <button
                                type="button"
                                class="product-gallery-scroll-control is-next"
                                data-product-gallery-scroll="next"
                                aria-label="{{ __('themes_b2c.product.next_image') }}"
                            >
                                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                            </button>
                        @endif
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
                            <div class="small">{{ __('themes_b2c.product.no_product_image') }}</div>
                        </div>
                    @endif
                </div>
            </div>

            @if(($relatedRows ?? collect())->isNotEmpty())
                <div class="ciak-related-products mt-4" data-related-carousel>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 fw-bold mb-0">{{ __('themes_b2c.product.you_may_also_like') }}</h2>

                        <div class="ciak-related-controls" aria-label="Controlli carosello prodotti correlati">
                            <button type="button" class="ciak-related-control" data-related-prev aria-label="Prodotti precedenti">
                                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="ciak-related-control" data-related-next aria-label="Prodotti successivi">
                                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="ciak-related-track" data-related-track>
                        @foreach($relatedRows as $row)
                            <div class="ciak-related-item">
                                @include('storefront.base.partials.product-card', [
                                    'product' => $row['product'],
                                    'listingCard' => $row['listingCard'],
                                    'contextParams' => $contextParams,
                                    'agentContextId' => $agentContextId,
                                ])
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="col-12 col-lg-6 product-info-column">
            <div class="product-info-panel">
                <div class="text-uppercase text-muted small fw-semibold mb-2 product-code-label">
                    {{ __('themes_b2c.product.item_code') }}: {{ $selectedProduct->sku }}
                </div>

                <div class="row g-3 align-items-start mb-4 product-title-price-row">
                    <div class="col-12 col-md">
                        <h1 class="display-6 fw-bold mb-0">
                            {{ $selectedTranslation?->name ?? $baseTranslation?->name ?? $selectedProduct->sku }}
                        </h1>
                    </div>

                    <div class="col-12 col-md-auto product-title-price text-md-end">
                        <div class="text-muted small mb-1">{{ __('themes_b2c.product.unit_price') }}</div>
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
                                {{ __('themes_b2c.product.price_calculated_for_quantity') }}: {{ number_format((float) $quantityInputValue, 0, ',', '.') }}
                            </div>
                        @endif
                    </div>
                </div>

                @if($selectedTranslation?->description || $baseTranslation?->description)
                    <p class="lead text-body mb-4 product-description-lead">
                        {!! $selectedTranslation?->description ?? $baseTranslation?->description !!}
                    </p>
                @endif

                @if($colorOptions->isNotEmpty() || $formatOptions->isNotEmpty())
                    <div class="row g-4 mb-4">
                        @if($colorOptions->isNotEmpty())
                            <div class="col-12 col-md-7">
                                <div class="text-muted small fw-semibold mb-2">{{ __('themes_b2c.product.color') }}</div>
                                <div class="d-flex flex-wrap gap-3 align-items-center">
                                    @foreach($colorOptions as $option)
                                        <a
                                            href="{{ route('storefront.product.show', array_merge(['sku' => $option['sku']], $contextParams)) }}"
                                            class="product-color-dot {{ ($selectedColorValue === $option['value']) ? 'is-active' : '' }} {{ !($option['available'] ?? true) ? 'is-unavailable' : '' }}"
                                            title="{{ $option['value'] }}{{ !($option['available'] ?? true) ? ' – ' . __('themes_b2c.product.out_of_stock') : '' }}"
                                            aria-label="{{ $option['value'] }}{{ !($option['available'] ?? true) ? ' – ' . __('themes_b2c.product.out_of_stock') : '' }}"
                                            @if($selectedColorValue === $option['value']) aria-current="true" @endif
                                            @if(!($option['available'] ?? true)) aria-disabled="true" @endif
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
                                <div class="text-muted small fw-semibold mb-2">{{ __('themes_b2c.product.format') }}</div>
                                <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                                    @foreach($formatOptions as $option)
                                        <a
                                            href="{{ route('storefront.product.show', array_merge(['sku' => $option['sku']], $contextParams)) }}"
                                            class="btn btn-sm product-format-pill {{ ($selectedFormatValue === $option['value']) ? 'btn-dark' : 'btn-outline-secondary' }} {{ !($option['available'] ?? true) ? 'is-unavailable' : '' }}"
                                            @if($selectedFormatValue === $option['value']) aria-current="true" @endif
                                            @if(!($option['available'] ?? true)) aria-disabled="true" @endif
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
                    <div class="text-muted small mb-1">{{ __('themes_b2c.product.availability') }}</div>
                    <div class="fw-semibold {{ $availabilityClass }}">
                        {{ $availabilityLabel }}
                        @if(!$isB2cProduct && $stockDisplay !== null)
                            <span class="text-body-secondary fw-normal">({{ $stockDisplay }} pz)</span>
                        @endif
                    </div>
                    @if(!empty($availabilityHint))
                        <div class="small text-muted mt-1">{{ $availabilityHint }}</div>
                    @endif
                </div>

                @if(!$isB2cProduct && $selectedVariantPriceBreaks->count() > 1)
                    <div class="product-tier-prices mb-4">
                        <div class="product-tier-prices-title">{{ __('themes_b2c.product.quantity_prices') }}</div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 product-tier-prices-table">
                                <tbody>
                                    @foreach($selectedVariantPriceBreaks as $tier)
                                        <tr>
                                            <td colspan="2">
                                                {{ __('themes_b2c.product.from_quantity') }} {{ number_format((float) ($tier['qty_from'] ?? 0), 0, ',', '.') }}
                                                @if(isset($tier['qty_to']) && $tier['qty_to'] !== null)
                                                    {{ __('themes_b2c.product.to_quantity') }} {{ number_format((float) $tier['qty_to'], 0, ',', '.') }} {{ __('themes_b2c.product.pieces_abbr') }}
                                                @else
                                                    {{ __('themes_b2c.product.pieces_and_up') }}
                                                @endif
                                            </td>
                                            <td class="text-end fw-semibold">
                                                @if(($tier['price'] ?? null) !== null)
                                                    € {{ number_format((float) $tier['price'], $priceDecimals, ',', '.') }} {{ __('themes_b2c.product.each_abbr') }}
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
                            {{ __('themes_b2c.product.minimum_order') }}: <strong>{{ number_format($quantityMin, 0, ',', '.') }}</strong>
                            @if($showPackMultiple)
                                · {{ __('themes_b2c.product.pack_multiple') }} <strong>{{ number_format($packMultiple, 0, ',', '.') }}</strong>
                            @endif
                            @if($quantityMax !== null)
                                · {{ __('themes_b2c.product.maximum_orderable') }} <strong>{{ number_format($quantityMax, 0, ',', '.') }}</strong>
                            @endif
                        </div>
                    @endif

                    <div class="row g-2 align-items-end product-buy-row">
                        @if($isB2cProduct)
                            <div class="col-auto">
                                <div class="ciak-qty-stepper" data-ciak-qty-stepper>
                                    <button
                                        type="button"
                                        class="ciak-qty-btn"
                                        data-ciak-qty-dec
                                        aria-label="Diminuisci quantità"
                                        @if($purchaseBlocked) disabled @endif
                                    >−</button>
                                    <input
                                        type="number"
                                        id="product-quantity-input"
                                        name="qty"
                                        class="ciak-qty-input"
                                        min="{{ $quantityMin }}"
                                        @if($quantityMax !== null) max="{{ $quantityMax }}" @endif
                                        step="{{ $quantityStep }}"
                                        value="{{ $quantityInputValue }}"
                                        data-price-breaks='@json($selectedVariantPriceBreaks->values())'
                                        @if($purchaseBlocked) disabled @endif
                                        aria-label="{{ __('themes_b2c.product.quantity') }}"
                                    >
                                    <button
                                        type="button"
                                        class="ciak-qty-btn"
                                        data-ciak-qty-inc
                                        aria-label="Aumenta quantità"
                                        @if($purchaseBlocked) disabled @endif
                                    >+</button>
                                </div>
                            </div>
                        @else
                            <div class="col-5 col-sm-3">
                                <label for="product-quantity-input" class="form-label small fw-semibold mb-1">{{ __('themes_b2c.product.quantity') }}</label>
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

                        <div class="{{ $isB2cProduct ? 'col d-grid' : 'col-7 col-sm-6 d-grid' }}">
                            <button
                                class="btn btn-primary"
                                id="product-add-to-cart-button"
                                type="submit"
                                @if($purchaseBlocked) disabled @endif
                            >
                                <i class="fa-solid fa-cart-shopping me-2"></i>
                                {{ __('themes_b2c.product.add_to_cart') }}
                            </button>
                        </div>
                    </div>

                    <div class="small d-none mt-2" id="product-add-to-cart-feedback"></div>

                    @if($purchaseBlocked)
                        <div class="alert alert-warning mt-3 mb-0">
                            {{ __('themes_b2c.product.not_orderable_current_availability') }}
                        </div>
                    @endif
                </form>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a href="{{ route('storefront.cart.index', $contextParams) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-cart-shopping me-1"></i>
                        {{ __('themes_b2c.product.go_to_cart') }}
                    </a>

                    @if(Route::has('storefront.catalog.index'))
                        <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="btn btn-outline-secondary btn-sm d-none">
                            {{ __('themes_b2c.product.continue_shopping') }}
                        </a>
                    @endif

                    @if($isB2cProduct && Route::has('storefront.store-locator.index'))
                        <a
                            href="{{ route('storefront.store-locator.index', array_merge(['sku' => $selectedProduct->sku], $contextParams)) }}"
                            class="btn btn-outline-secondary btn-sm"
                        >
                            <i class="fa-solid fa-location-dot me-1"></i>
                            {{ __('themes_b2c.product.find_store_near_you') }}
                        </a>
                    @endif
                </div>

                <div class="mt-4">
                    <ul class="nav nav-tabs ciak-product-tabs" id="product-details-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button
                                class="nav-link active"
                                id="product-sheet-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#product-sheet-pane"
                                type="button"
                                role="tab"
                                aria-controls="product-sheet-pane"
                                aria-selected="true"
                            >
                                {{ __('themes_b2c.product.product_sheet') }}
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button
                                class="nav-link"
                                id="product-shipping-logic-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#product-shipping-logic-pane"
                                type="button"
                                role="tab"
                                aria-controls="product-shipping-logic-pane"
                                aria-selected="false"
                            >
                                {{ __('themes_b2c.product.shipping_logic') }}
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content ciak-product-tab-content border border-top-0 p-3" id="product-details-tabs-content">
                        <div
                            class="tab-pane fade show active"
                            id="product-sheet-pane"
                            role="tabpanel"
                            aria-labelledby="product-sheet-tab"
                            tabindex="0"
                        >
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle product-spec-table mb-0">
                                    <tbody>
                                        <tr>
                                            <th>{{ __('themes_b2c.product.item_code') }}</th>
                                            <td class="text-end">{{ $selectedProduct->sku }}</td>
                                        </tr>

                                        @if(trim((string) ($selectedProduct->barcode ?? '')) !== '')
                                            <tr>
                                                <th>Barcode</th>
                                                <td class="text-end">{{ $selectedProduct->barcode }}</td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <th>{{ __('themes_b2c.product.category') }}</th>
                                            <td class="text-end">{{ $selectedProduct->category_path_description ?? '—' }}</td>
                                        </tr>

                                        @forelse($technicalRows as $item)
                                            @continue(($item['label'] ?? null) === 'SKU')
                                            <tr>
                                                <th>{{ $item['label'] }}</th>
                                                <td class="text-end">{{ $item['value'] }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="2" class="text-muted small">
                                                    {{ __('themes_b2c.product.no_technical_attributes') }}
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
                                                        <span>{{ __('themes_b2c.product.comparative_items') }}</span>
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
                                                                    {{ strtoupper((string) ($comparison->source ?? __('themes_b2c.product.comparative'))) }}
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

                        <div
                            class="tab-pane fade"
                            id="product-shipping-logic-pane"
                            role="tabpanel"
                            aria-labelledby="product-shipping-logic-tab"
                            tabindex="0"
                        >
                            <div class="small text-muted mb-3">
                                {{ __('themes_b2c.product.shipping_logic_intro') }}
                            </div>

                            <div class="mb-3">
                                <h3 class="h6 mb-2">{{ __('themes_b2c.product.built_in_shipping_rules') }}</h3>
                                <ul class="mb-0 ps-3">
                                    @foreach(($shippingLogicSummary['built_in_free_rules'] ?? collect()) as $ruleLabel)
                                        <li>{{ $ruleLabel }}</li>
                                    @endforeach
                                </ul>
                            </div>

                            @if(($shippingLogicSummary['free_rules'] ?? collect())->isNotEmpty())
                                <div class="mb-3">
                                    <h3 class="h6 mb-2">{{ __('themes_b2c.product.configured_free_shipping_rules') }}</h3>
                                    <ul class="mb-0 ps-3">
                                        @foreach(($shippingLogicSummary['free_rules'] ?? collect()) as $rule)
                                            <li><strong>{{ $rule['location'] ?? '-' }}</strong>: {{ $rule['label'] ?? '-' }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if(($shippingLogicSummary['table_rules'] ?? collect())->isNotEmpty())
                                <div>
                                    <h3 class="h6 mb-2">{{ __('themes_b2c.product.configured_table_shipping_rules') }}</h3>
                                    <ul class="mb-0 ps-3">
                                        @foreach(($shippingLogicSummary['table_rules'] ?? collect()) as $rule)
                                            <li><strong>{{ $rule['location'] ?? '-' }}</strong>: {{ $rule['label'] ?? '-' }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                        </div>
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

            document.querySelectorAll('[data-related-carousel]').forEach(function (carousel) {
                var track = carousel.querySelector('[data-related-track]');
                var prev = carousel.querySelector('[data-related-prev]');
                var next = carousel.querySelector('[data-related-next]');

                if (!track || !prev || !next) {
                    return;
                }

                var measureStep = function () {
                    var firstItem = track.querySelector('.ciak-related-item');

                    if (!firstItem) {
                        return track.clientWidth;
                    }

                    var styles = window.getComputedStyle(track);
                    var gap = parseFloat(styles.columnGap || styles.gap || '0') || 0;

                    return firstItem.getBoundingClientRect().width + gap;
                };

                var updateControls = function () {
                    var maxScroll = Math.max(0, track.scrollWidth - track.clientWidth - 2);
                    var atStart = track.scrollLeft <= 2;
                    var atEnd = track.scrollLeft >= maxScroll;

                    prev.disabled = atStart;
                    next.disabled = atEnd;
                    carousel.classList.toggle('is-static', maxScroll <= 0);
                };

                prev.addEventListener('click', function () {
                    track.scrollBy({ left: -measureStep(), behavior: 'smooth' });
                });

                next.addEventListener('click', function () {
                    track.scrollBy({ left: measureStep(), behavior: 'smooth' });
                });

                track.addEventListener('scroll', updateControls, { passive: true });
                window.addEventListener('resize', updateControls, { passive: true });
                updateControls();
            });
        })();
    </script>
@endpush
@endsection
