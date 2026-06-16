{{-- resources/views/storefront/base/partials/product-card.blade.php --}}
@php
    $card = \App\Models\ProductCardViewModel::make($product ?? null, $listingCard ?? []);
    $agentContextId = $agentContextId ?? (string) request('agent_context', '');
    $contextParams = $contextParams ?? ($agentContextId !== '' ? ['agent_context' => $agentContextId] : []);
    $contextUrl = static function (?string $url) use ($agentContextId): ?string {
        if (!$url || $agentContextId === '') {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query(['agent_context' => $agentContextId]);
    };
@endphp

<div
    class="card h-100 border-0 shadow-sm product-listing-card"
    data-product-card
    data-product-sku="{{ $card->targetSku }}"
>
    @if($card->image)
        <div class="product-listing-image-link d-block position-relative overflow-hidden">
            <a href="{{ $contextUrl($card->productUrl) }}" class="d-block" data-product-card-link>
                <img
                    src="{{ $card->image }}"
                    class="card-img-top product-listing-image-primary"
                    alt="{{ $card->name }}"
                    loading="lazy"
                    data-product-card-image
                >

                @if($card->hoverImage && $card->hoverImage !== $card->image)
                    <img
                        src="{{ $card->hoverImage }}"
                        class="card-img-top product-listing-image-hover position-absolute top-0 start-0 w-100 h-100"
                        alt="{{ $card->name }}"
                        loading="lazy"
                        data-product-card-hover-image
                    >
                @endif
            </a>

            <div class="product-listing-wishlist-layer" aria-live="polite">
                @auth('customer')
                    <button
                        type="button"
                        class="product-listing-wishlist-btn {{ $card->isWishlisted ? 'is-active' : '' }}"
                        data-product-card-wishlist-toggle
                        data-wishlist-url="{{ route('storefront.wishlist.toggle', $contextParams) }}"
                        data-wishlist-sku="{{ $card->targetSku }}"
                        aria-label="{{ $card->isWishlisted ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti' }}"
                        aria-pressed="{{ $card->isWishlisted ? 'true' : 'false' }}"
                    >
                        <i
                            class="{{ $card->isWishlisted ? 'fa-solid' : 'fa-regular' }} fa-heart"
                            data-product-card-wishlist-icon
                            aria-hidden="true"
                        ></i>
                        <span class="visually-hidden" data-product-card-wishlist-label>
                            {{ $card->isWishlisted ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti' }}
                        </span>
                    </button>
                @else
                    <a
                        href="{{ route('storefront.login', $contextParams) }}"
                        class="product-listing-wishlist-btn"
                        aria-label="Accedi per aggiungere ai preferiti"
                    >
                        <i class="fa-regular fa-heart" aria-hidden="true"></i>
                        <span class="visually-hidden">Accedi per aggiungere ai preferiti</span>
                    </a>
                @endauth
            </div>
        </div>
    @else
        <a
            href="{{ $contextUrl($card->productUrl) }}"
            class="product-listing-image-empty d-flex align-items-center justify-content-center bg-light text-muted small text-decoration-none"
            data-product-card-link
        >
            Nessuna immagine
        </a>
    @endif

    <div class="card-body d-flex flex-column">
        <div class="small text-muted mb-1">
            SKU <span data-product-card-sku-label>{{ $card->targetSku }}</span>
        </div>

        <div class="fw-semibold mb-2">
            <a
                href="{{ $contextUrl($card->productUrl) }}"
                class="text-decoration-none text-body"
                data-product-card-link
                data-product-card-title
            >
                {{ $card->name }}
            </a>
        </div>

        @if($card->variants > 1)
            <div class="small text-muted mb-2">
                {{ $card->variants }} varianti disponibili
            </div>
        @endif

        @if($card->colorOptions->isNotEmpty())
            <div class="mb-2">
                <div class="small text-muted mb-1">Colore</div>

                <div class="d-flex flex-wrap gap-2">
                    @foreach($card->colorOptions as $option)
                        @php($payload = $card->colorOptionPayload($option))

                        <button
                            type="button"
                            class="border-0 bg-transparent p-0 {{ $payload['is_selected'] ? 'is-active is-selected' : '' }}"
                            data-product-card-variant
                            data-variant-type="color"
                            data-variant-sku="{{ $payload['sku'] }}"
                            data-variant-url="{{ $contextUrl($payload['url'] ?? null) }}"
                            data-variant-image="{{ $payload['image'] }}"
                            data-variant-hover-image="{{ $payload['hover_image'] }}"
                            data-variant-price="{{ $payload['price'] }}"
                            data-variant-qty-min="{{ $payload['quantity_min'] }}"
                            data-variant-qty-step="{{ $payload['quantity_step'] }}"
                            data-variant-pack-multiple="{{ $payload['pack_multiple'] }}"
                            title="{{ $payload['value'] ?? '' }}"
                            aria-label="Colore {{ $payload['value'] ?? '-' }}"
                            aria-pressed="{{ $payload['is_selected'] ? 'true' : 'false' }}"
                        >
                            @if(!empty($payload['swatch_url']))
                                <span class="product-listing-option-swatch d-inline-flex border rounded-circle overflow-hidden {{ $payload['is_selected'] ? 'is-active is-selected' : '' }}">
                                    <img src="{{ $payload['swatch_url'] }}" alt="{{ $payload['value'] ?? '' }}">
                                </span>
                            @else
                                <span class="product-listing-option-swatch badge text-bg-light border {{ $payload['is_selected'] ? 'is-active is-selected' : '' }}">
                                    {{ $payload['value'] ?? '-' }}
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        @if($card->formatOptions->isNotEmpty())
            <div class="mb-2">
                <div class="small text-muted mb-1">Formato</div>

                <div class="d-flex flex-wrap gap-2">
                    @foreach($card->formatOptions as $option)
                        @php($payload = $card->formatOptionPayload($option))

                        <button
                            type="button"
                            class="badge rounded-pill text-bg-light border product-listing-option-pill {{ $payload['is_selected'] ? 'is-active is-selected' : '' }}"
                            data-product-card-variant
                            data-variant-type="format"
                            data-variant-sku="{{ $payload['sku'] }}"
                            data-variant-url="{{ $contextUrl($payload['url'] ?? null) }}"
                            data-variant-image="{{ $payload['image'] }}"
                            data-variant-hover-image="{{ $payload['hover_image'] }}"
                            data-variant-price="{{ $payload['price'] }}"
                            data-variant-qty-min="{{ $payload['quantity_min'] }}"
                            data-variant-qty-step="{{ $payload['quantity_step'] }}"
                            data-variant-pack-multiple="{{ $payload['pack_multiple'] }}"
                            aria-pressed="{{ $payload['is_selected'] ? 'true' : 'false' }}"
                        >
                            {{ $payload['value'] ?? '-' }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mb-3 mt-2">
            <div class="fw-semibold" data-product-card-price>
                {{ $card->formattedPrice() }}
            </div>

            @if($card->hasVariablePrice)
                <div class="small text-muted">
                    Prezzo variabile in base alla quantità
                </div>
            @endif
        </div>

        <form
            method="POST"
            action="{{ route('storefront.cart.add', $contextParams) }}"
            class="mt-auto"
            data-product-card-add-to-cart-form
        >
            @csrf
            @if($agentContextId !== '')
                <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
            @endif

            <input type="hidden" name="sku" value="{{ $card->targetSku }}" data-product-card-sku>

            <div class="small text-muted mb-2" data-product-card-qty-note>
                Minimo ordine: <strong data-product-card-qty-min-label>{{ $card->formattedQuantityMin() }}</strong>
                <span class="{{ $card->showPackMultiple ? '' : 'd-none' }}" data-product-card-pack-note>
                    · Multipli di <strong data-product-card-pack-multiple-label>{{ $card->formattedPackMultiple() }}</strong>
                </span>
            </div>

            <div class="d-flex gap-2 align-items-end">
                <div class="flex-shrink-0" style="width: 96px;">
                    <label class="form-label small fw-semibold mb-1" for="{{ $card->quantityInputId() }}">Qtà</label>

                    <input
                        type="number"
                        id="{{ $card->quantityInputId() }}"
                        name="qty"
                        value="{{ $card->quantityMin }}"
                        min="{{ $card->quantityMin }}"
                        step="{{ $card->quantityStep }}"
                        inputmode="numeric"
                        class="form-control form-control-sm"
                        data-product-card-qty
                        data-qty-min="{{ $card->quantityMin }}"
                        data-qty-step="{{ $card->quantityStep }}"
                    >
                </div>

                <div class="flex-grow-1 d-grid">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fa-solid fa-cart-shopping me-1"></i>
                        Aggiungi
                    </button>
                </div>

                <a href="{{ $contextUrl($card->productUrl) }}" class="btn btn-sm btn-outline-primary flex-shrink-0" data-product-card-link>
                    Vedi
                </a>
            </div>
        </form>

        <div class="small mt-2 d-none" data-product-card-feedback></div>
    </div>
</div>