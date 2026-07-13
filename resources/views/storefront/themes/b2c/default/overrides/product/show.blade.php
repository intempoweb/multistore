@extends($storefrontLayout)

@section('title', (($selectedTranslation?->name ?? $baseTranslation?->name ?? $selectedProduct->sku ?? __('themes_b2c.product.product')) . ' - ' . ($store->name ?? config('app.name', 'Store'))))

@section('content')
@php
    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];

    $productName = $selectedTranslation?->name
        ?? $baseTranslation?->name
        ?? $selectedProduct->sku
        ?? __('themes_b2c.product.product');

    $productDescription = $selectedTranslation?->description
        ?? $baseTranslation?->description
        ?? null;

    $galleryImagesCollection = collect($galleryImages ?? [])
        ->filter(fn ($item) => is_array($item) && !empty($item['url']))
        ->values();

    $mainProductImage = $mainImage ?? $image ?? ($galleryImagesCollection->first()['url'] ?? null);
    $hasMultipleGalleryImages = $galleryImagesCollection->count() > 1;

    $stockQuantity = $stockQty ?? null;
    $isUnavailable = (bool) ($purchaseBlocked ?? false);
    $isBackorderOrderable = $stockQuantity !== null
        && (float) $stockQuantity <= 0
        && (bool) ($canAddToCart ?? false)
        && !$isUnavailable;
    $stockClass = $isBackorderOrderable
        ? 'text-success'
        : ($stockClass
        ?? ($stockQuantity === null
            ? 'text-muted'
            : ((float) $stockQuantity > 0 ? 'text-success' : 'text-danger')));
    $availabilityLabel = $isBackorderOrderable
        ? __('themes_b2c.product.in_stock')
        : $stockLabel;
    $availabilityHint = $isBackorderOrderable ? null : ($stockHint ?? null);

    $priceDecimals = 2;
@endphp

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="text-muted small mb-1">
                    {{ __('themes_b2c.product.sheet') }}
                </div>

                <h1 class="h3 fw-bold mb-2">
                    {{ $productName }}
                </h1>

                <div class="text-muted small">
                    {{ __('themes_b2c.product.sku') }}: {{ $selectedProduct->sku }}
                </div>

                @if($productDescription)
                    <p class="text-secondary mt-3 mb-0">
                        {{ $productDescription }}
                    </p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                @if($mainProductImage)
                    <div class="mb-3">
                        <div
                            class="storefront-product-media-frame border rounded-3 bg-light d-flex align-items-center justify-content-center p-3"
                        >
                            <img
                                id="product-main-image"
                                src="{{ $mainProductImage }}"
                                class="storefront-product-main-image img-fluid"
                                alt="{{ $productName }}"
                            >
                        </div>
                    </div>

                    @if($hasMultipleGalleryImages)
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($galleryImagesCollection as $galleryImage)
                                <button
                                    type="button"
                                    class="btn p-1 border rounded-3 bg-white product-gallery-thumb"
                                    data-image-url="{{ $galleryImage['url'] }}"
                                    aria-label="{{ __('themes_b2c.product.show_image') }} {{ $loop->iteration }}"
                                >
                                    <img
                                        src="{{ $galleryImage['url'] }}"
                                        alt="{{ $galleryImage['alt'] ?? $productName }}"
                                        class="storefront-product-gallery-thumb rounded-2"
                                    >
                                </button>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div
                        class="storefront-product-media-placeholder d-flex align-items-center justify-content-center h-100 text-muted small text-center"
                    >
                        {{ __('themes_b2c.product.no_image') }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                @if($colorOptions->isNotEmpty() || $formatOptions->isNotEmpty())
                    <div class="mb-4">
                        <h2 class="h5 fw-semibold mb-3">{{ __('themes_b2c.product.variants') }}</h2>

                        <div class="d-flex flex-column gap-3">
                            @if($colorOptions->isNotEmpty())
                                <div>
                                    <div class="text-muted small mb-2">{{ __('themes_b2c.product.color') }}</div>

                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($colorOptions as $option)
                                            <a
                                                href="{{ route('storefront.product.show', array_merge(['sku' => $option['sku']], $contextParams)) }}"
                                                class="btn {{ ($selectedColorValue === $option['value']) ? 'btn-dark' : 'btn-outline-secondary' }} d-inline-flex align-items-center gap-2"
                                            >
                                                @if(!empty($option['swatch_url']))
                                                    <span
                                                        class="storefront-product-swatch border rounded-circle overflow-hidden d-inline-flex align-items-center justify-content-center bg-white"
                                                    >
                                                        <img
                                                            src="{{ $option['swatch_url'] }}"
                                                            alt="{{ $option['value'] }}"
                                                        >
                                                    </span>
                                                @endif

                                                <span>{{ $option['value'] }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($formatOptions->isNotEmpty())
                                <div>
                                    <div class="text-muted small mb-2">{{ __('themes_b2c.product.format') }}</div>

                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($formatOptions as $option)
                                            <a
                                                href="{{ route('storefront.product.show', array_merge(['sku' => $option['sku']], $contextParams)) }}"
                                                class="btn {{ ($selectedFormatValue === $option['value']) ? 'btn-dark' : 'btn-outline-secondary' }}"
                                            >
                                                {{ $option['value'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <hr class="my-4">
                @endif

                <h2 class="h5 fw-semibold mb-3">{{ __('themes_b2c.product.details') }}</h2>

                <div class="row g-3">
                    <div class="col-6">
                        <div class="text-muted small">{{ __('themes_b2c.product.sku') }}</div>
                        <div class="fw-semibold">{{ $selectedProduct->sku }}</div>
                    </div>

                    <div class="col-6">
                        <div class="text-muted small">{{ __('themes_b2c.product.availability') }}</div>
                        <div class="fw-semibold {{ $stockClass }}">
                            {{ $availabilityLabel }}

                            @if($stockDisplay !== null)
                                <span class="text-body-secondary fw-normal">({{ $stockDisplay }} {{ __('themes_b2c.product.pieces_abbr') }})</span>
                            @endif
                        </div>
                        @if(!empty($availabilityHint))
                            <div class="small text-muted mt-1">{{ $availabilityHint }}</div>
                        @endif
                    </div>

                    @if($selectedColorValue)
                        <div class="col-6">
                            <div class="text-muted small">{{ __('themes_b2c.product.color') }}</div>
                            <div class="fw-semibold">{{ $selectedColorValue }}</div>
                        </div>
                    @endif

                    @if($selectedFormatValue)
                        <div class="col-6">
                            <div class="text-muted small">{{ __('themes_b2c.product.format') }}</div>
                            <div class="fw-semibold">{{ $selectedFormatValue }}</div>
                        </div>
                    @endif

                    <div class="col-6">
                        <div class="text-muted small">{{ __('themes_b2c.product.price') }}</div>
                        <div class="fw-semibold">
                            @if($effectivePrice !== null)
                                € {{ number_format((float) $effectivePrice, $priceDecimals, ',', '.') }}
                            @else
                                —
                            @endif
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="text-muted small">{{ __('themes_b2c.product.category') }}</div>
                        <div class="fw-semibold">
                            {{ $selectedProduct->category_path_description ?? '—' }}
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="text-muted small">{{ __('themes_b2c.product.minimum_quantity') }}</div>
                        <div class="fw-semibold">{{ $quantityMin }}</div>
                    </div>

                    <div class="col-6">
                        <div class="text-muted small">{{ __('themes_b2c.product.unit') }}</div>
                        <div class="fw-semibold">{{ $selectedProduct->unit ?? '-' }}</div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="mb-4">
                    <h3 class="h6 fw-semibold mb-3">{{ __('themes_b2c.product.technical_sheet') }}</h3>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                <tr>
                                    <th class="storefront-product-technical-label text-muted fw-normal">{{ __('themes_b2c.product.sku') }}</th>
                                    <td class="fw-semibold">{{ $selectedProduct->sku }}</td>
                                </tr>

                                @forelse($technicalRows as $item)
                                    <tr>
                                        <th class="text-muted fw-normal">{{ $item['label'] }}</th>
                                        <td class="fw-semibold">{{ $item['value'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-muted small">
                                            {{ __('themes_b2c.product.no_technical_attributes') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <hr class="my-4">

                <form
                    class="row g-2"
                    id="product-add-to-cart-form"
                    method="POST"
                    action="{{ route('storefront.cart.add') }}"
                    data-cart-add-form
                >
                    @csrf

                    <input type="hidden" name="sku" value="{{ $selectedProduct->sku }}">

                    @if($agentContextId !== '')
                        <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                    @endif

                    <div class="col-12">
                        <div class="small text-muted mb-2">
                            {{ __('themes_b2c.product.minimum_order_quantity') }}:
                            <strong>{{ number_format($quantityMin, 0, ',', '.') }}</strong>

                            @if($showPackMultiple)
                                — {{ __('themes_b2c.product.pack_multiple') }} <strong>{{ number_format($packMultiple, 0, ',', '.') }}</strong>
                            @endif

                            @if($stockQuantity !== null && ($selectedProduct->no_backorder ?? false))
                                — {{ __('themes_b2c.product.maximum_availability') }} <strong>{{ $stockDisplay }} {{ __('themes_b2c.product.pieces_abbr') }}</strong>
                            @endif
                        </div>

                        <div class="small d-none" id="product-add-to-cart-feedback"></div>
                    </div>

                    <div class="col-4">
                        <input
                            type="number"
                            class="form-control"
                            id="product-quantity-input"
                            name="qty"
                            min="{{ $quantityMin }}"
                            step="{{ $quantityStep }}"
                            value="{{ $quantityInputValue }}"
                            @if($stockQuantity !== null && ($selectedProduct->no_backorder ?? false))
                                max="{{ (int) floor((float) $stockQuantity) }}"
                            @endif
                            @if($isUnavailable) disabled @endif
                        >
                    </div>

                    <div class="col-8 d-grid">
                        <button
                            class="btn btn-primary"
                            id="product-add-to-cart-button"
                            type="submit"
                            @if($isUnavailable) disabled @endif
                        >
                            <i class="fa-solid fa-cart-shopping me-2"></i>
                            {{ __('themes_b2c.product.add_to_cart') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
