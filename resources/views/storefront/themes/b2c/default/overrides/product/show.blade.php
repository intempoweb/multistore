@extends($storefrontLayout)

@section('title', (($selectedTranslation?->name ?? $baseTranslation?->name ?? $selectedProduct->sku ?? 'Prodotto') . ' - ' . ($store->name ?? 'Store')))

@section('content')
@php
    $productName = $selectedTranslation?->name
        ?? $baseTranslation?->name
        ?? $selectedProduct->sku
        ?? 'Prodotto';

    $productDescription = $selectedTranslation?->description
        ?? $baseTranslation?->description
        ?? null;

    $galleryImagesCollection = collect($galleryImages ?? [])
        ->filter(fn ($item) => is_array($item) && !empty($item['url']))
        ->values();

    $mainProductImage = $mainImage ?? $image ?? ($galleryImagesCollection->first()['url'] ?? null);

    $hasGallery = $galleryImagesCollection->isNotEmpty();
    $hasMultipleGalleryImages = $galleryImagesCollection->count() > 1;

    $stockQuantity = $stockQty ?? null;
    $isOutOfStock = $stockQuantity !== null && (float) $stockQuantity <= 0;

    $stockClass = $stockQuantity === null
        ? 'text-muted'
        : ((float) $stockQuantity > 0 ? 'text-success' : 'text-danger');

    $priceDecimals = 2;
@endphp

<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="text-muted small mb-1">
                    Scheda prodotto
                </div>

                <h1 class="h3 fw-bold mb-2">
                    {{ $productName }}
                </h1>

                <div class="text-muted small">
                    SKU: {{ $selectedProduct->sku }}
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
                            class="border rounded-3 bg-light d-flex align-items-center justify-content-center p-3"
                            style="min-height: 420px;"
                        >
                            <img
                                id="product-main-image"
                                src="{{ $mainProductImage }}"
                                class="img-fluid"
                                style="max-height: 380px; object-fit: contain;"
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
                                    aria-label="Mostra immagine {{ $loop->iteration }}"
                                >
                                    <img
                                        src="{{ $galleryImage['url'] }}"
                                        alt="{{ $galleryImage['alt'] ?? $productName }}"
                                        class="rounded-2"
                                        style="width:72px; height:72px; object-fit:cover;"
                                    >
                                </button>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div
                        class="d-flex align-items-center justify-content-center h-100 text-muted small text-center"
                        style="min-height: 420px;"
                    >
                        Nessuna immagine prodotto
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
                        <h2 class="h5 fw-semibold mb-3">Varianti</h2>

                        <div class="d-flex flex-column gap-3">
                            @if($colorOptions->isNotEmpty())
                                <div>
                                    <div class="text-muted small mb-2">Colore</div>

                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($colorOptions as $option)
                                            <a
                                                href="{{ route('storefront.product.show', $option['sku']) }}"
                                                class="btn {{ ($selectedColorValue === $option['value']) ? 'btn-dark' : 'btn-outline-secondary' }} d-inline-flex align-items-center gap-2"
                                            >
                                                @if(!empty($option['swatch_url']))
                                                    <span
                                                        class="border rounded-circle overflow-hidden d-inline-flex align-items-center justify-content-center bg-white"
                                                        style="width: 28px; height: 28px;"
                                                    >
                                                        <img
                                                            src="{{ $option['swatch_url'] }}"
                                                            alt="{{ $option['value'] }}"
                                                            style="width: 100%; height: 100%; object-fit: cover;"
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
                                    <div class="text-muted small mb-2">Formato</div>

                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($formatOptions as $option)
                                            <a
                                                href="{{ route('storefront.product.show', $option['sku']) }}"
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

                <h2 class="h5 fw-semibold mb-3">Dettagli prodotto</h2>

                <div class="row g-3">
                    <div class="col-6">
                        <div class="text-muted small">SKU</div>
                        <div class="fw-semibold">{{ $selectedProduct->sku }}</div>
                    </div>

                    <div class="col-6">
                        <div class="text-muted small">Disponibilità</div>
                        <div class="fw-semibold {{ $stockClass }}">
                            {{ $stockLabel }}

                            @if($stockDisplay !== null)
                                <span class="text-body-secondary fw-normal">({{ $stockDisplay }} pz)</span>
                            @endif
                        </div>
                    </div>

                    @if($selectedColorValue)
                        <div class="col-6">
                            <div class="text-muted small">Colore</div>
                            <div class="fw-semibold">{{ $selectedColorValue }}</div>
                        </div>
                    @endif

                    @if($selectedFormatValue)
                        <div class="col-6">
                            <div class="text-muted small">Formato</div>
                            <div class="fw-semibold">{{ $selectedFormatValue }}</div>
                        </div>
                    @endif

                    <div class="col-6">
                        <div class="text-muted small">Prezzo</div>
                        <div class="fw-semibold">
                            @if($effectivePrice !== null)
                                € {{ number_format((float) $effectivePrice, $priceDecimals, ',', '.') }}
                            @else
                                —
                            @endif
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="text-muted small">Categoria</div>
                        <div class="fw-semibold">
                            {{ $selectedProduct->category_path_description ?? '—' }}
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="text-muted small">Quantità minima</div>
                        <div class="fw-semibold">{{ $quantityMin }}</div>
                    </div>

                    <div class="col-6">
                        <div class="text-muted small">Unità</div>
                        <div class="fw-semibold">{{ $selectedProduct->unit ?? '-' }}</div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="mb-4">
                    <h3 class="h6 fw-semibold mb-3">Scheda tecnica</h3>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                <tr>
                                    <th class="text-muted fw-normal" style="width: 40%;">SKU</th>
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
                                            Nessun altro attributo tecnico collegato a questo prodotto.
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

                    <div class="col-12">
                        <div class="small text-muted mb-2">
                            Quantità minima ordinabile:
                            <strong>{{ number_format($quantityMin, 0, ',', '.') }}</strong>

                            @if($showPackMultiple)
                                — multipli di <strong>{{ number_format($packMultiple, 0, ',', '.') }}</strong>
                            @endif

                            @if($stockQuantity !== null)
                                — disponibilità massima: <strong>{{ $stockDisplay }} pz</strong>
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
                            @if($isOutOfStock) disabled @endif
                        >
                    </div>

                    <div class="col-8 d-grid">
                        <button
                            class="btn btn-primary"
                            id="product-add-to-cart-button"
                            type="submit"
                            @if($isOutOfStock) disabled @endif
                        >
                            <i class="fa-solid fa-cart-shopping me-2"></i>
                            Aggiungi al carrello
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mainImage = document.getElementById('product-main-image');

        if (!mainImage) {
            return;
        }

        document.querySelectorAll('.product-gallery-thumb').forEach(function (button) {
            button.addEventListener('click', function () {
                const imageUrl = button.getAttribute('data-image-url');

                if (!imageUrl) {
                    return;
                }

                mainImage.src = imageUrl;
            });
        });
    });
</script>
@endpush
