@php
    $items = collect($items ?? [])->values();
    $cartCount = (float) ($cartCount ?? 0);
    $cartTotal = (float) ($cartTotal ?? 0);
    $cartDiscountTotal = (float) ($cartDiscountTotal ?? ($cart->discount_total ?? 0));
    $agentContextId = $agentContextId ?? (string) request('agent_context', '');
    $contextParams = $contextParams ?? ($agentContextId !== '' ? ['agent_context' => $agentContextId] : []);
    $contextUrl = static function (?string $url) use ($agentContextId): ?string {
        if (!$url || $agentContextId === '' || str_contains($url, 'agent_context=')) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query(['agent_context' => $agentContextId]);
    };
@endphp

<div
    class="minicart-content text-dark"
    data-minicart
    data-cart-count="{{ number_format($cartCount, 3, '.', '') }}"
    data-cart-total="{{ number_format($cartTotal, 3, '.', '') }}"
    data-cart-discount-total="{{ number_format($cartDiscountTotal, 3, '.', '') }}"
>
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h6 class="mb-0">Carrello</h6>
            <div class="small text-muted">
                @if($items->isEmpty())
                    Nessun prodotto nel carrello
                @else
                    {{ number_format($cartCount, 0, ',', '.') }} pz
                @endif
            </div>
        </div>

        @if($items->isNotEmpty())
            <span class="badge text-bg-light border">
                {{ number_format($cartCount, 0, ',', '.') }} pz
            </span>
        @endif
    </div>

    @if($items->isEmpty())
        <div class="rounded border bg-light-subtle p-3 text-center">
            <div class="text-muted mb-2">Il carrello è vuoto</div>

            <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="btn btn-sm btn-outline-primary">
                Vai al catalogo
            </a>
        </div>
    @else
        <div class="d-flex flex-column gap-3" data-minicart-items>
            @foreach($items as $item)
                @php
                    $productUrl = $contextUrl($item->product_url ?? route('storefront.product.show', array_merge(['sku' => $item->sku], $contextParams)));
                    $quantity = (float) ($item->quantity ?? 0);
                    $quantityMin = max(1, (int) ($item->quantity_min ?? 1));
                    $quantityStep = max(1, (int) ($item->quantity_step ?? $quantityMin));
                    $packMultiple = max(1, (int) ($item->pack_multiple ?? $quantityStep));
                    $showPackMultiple = (bool) ($item->show_pack_multiple ?? false);
                    $basePrice = $item->base_price !== null ? (float) $item->base_price : null;
                    $finalPrice = $item->final_price !== null ? (float) $item->final_price : (($item->price !== null) ? (float) $item->price : null);
                    $baseRowTotal = $item->base_row_total !== null ? (float) $item->base_row_total : null;
                    $webDiscountTotal = $item->web_discount_total !== null ? (float) $item->web_discount_total : 0.0;
                    $finalRowTotal = $item->final_row_total !== null ? (float) $item->final_row_total : (($item->row_total !== null) ? (float) $item->row_total : 0.0);
                    $hasWebDiscount = $webDiscountTotal > 0;
                    $thumbnailUrl = media_url($item->product_thumbnail_url);
                @endphp

                <div
                    class="border rounded p-2"
                    data-cart-item
                    data-cart-item-id="{{ $item->id }}"
                    data-cart-item-sku="{{ $item->sku }}"
                >
                    <div class="d-flex gap-2 align-items-start">
                        <div class="flex-shrink-0">
                            @if($thumbnailUrl)
                                <a href="{{ $productUrl }}">
                                    <img
                                        src="{{ $thumbnailUrl }}"
                                        alt="{{ $item->product_name ?? $item->sku }}"
                                        class="rounded border"
                                        style="width: 56px; height: 56px; object-fit: cover;"
                                    >
                                </a>
                            @else
                                <a
                                    href="{{ $productUrl }}"
                                    class="d-flex align-items-center justify-content-center rounded border bg-light text-muted small text-decoration-none"
                                    style="width: 56px; height: 56px;"
                                >
                                    N/A
                                </a>
                            @endif
                        </div>

                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold small mb-1">
                                <a href="{{ $productUrl }}" class="text-decoration-none text-body">
                                    {{ $item->product_name ?? $item->sku }}
                                </a>
                            </div>

                            <div class="text-muted small mb-1">
                                SKU: {{ $item->sku }}
                            </div>

                            @if(!empty($item->product_description))
                                <div class="text-muted mb-2" style="font-size: .75rem; line-height: 1.35;">
                                    {{ $item->product_description }}
                                </div>
                            @endif

                            <form
                                method="POST"
                                action="{{ route('storefront.cart.update', array_merge(['item' => $item], $contextParams)) }}"
                                class="mt-2"
                                data-minicart-update-form
                            >
                                @csrf
                                @if($agentContextId !== '')
                                    <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                                @endif

                                <div class="d-flex align-items-center gap-2">
                                    <input
                                        type="number"
                                        name="qty"
                                        value="{{ number_format($quantity, 0, '.', '') }}"
                                        min="{{ $quantityMin }}"
                                        step="{{ $quantityStep }}"
                                        inputmode="numeric"
                                        class="form-control form-control-sm minicart-qty-input"
                                        style="width: 88px;"
                                        data-qty-min="{{ $quantityMin }}"
                                        data-qty-step="{{ $quantityStep }}"
                                    >

                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        Aggiorna
                                    </button>
                                </div>

                                <div class="small text-muted mt-2">
                                    Quantità:
                                    <span data-cart-item-qty>{{ number_format($quantity, 0, ',', '.') }}</span>
                                    · Minimo {{ number_format($quantityMin, 0, ',', '.') }}
                                    @if($showPackMultiple)
                                        · Multipli di {{ number_format($packMultiple, 0, ',', '.') }}
                                    @endif
                                </div>
                            </form>

                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div class="ms-auto text-end">
                                    @if($hasWebDiscount && $baseRowTotal !== null)
                                        <div class="text-muted text-decoration-line-through" style="font-size: .75rem;">
                                            € {{ number_format($baseRowTotal, 3, ',', '.') }}
                                        </div>
                                    @endif

                                    <div class="fw-semibold small" data-cart-item-total>
                                        € {{ number_format($finalRowTotal, 3, ',', '.') }}
                                    </div>

                                    @if($finalPrice !== null)
                                        <div class="text-muted" style="font-size: .75rem;">
                                            € {{ number_format($finalPrice, 3, ',', '.') }} cad.
                                        </div>
                                    @endif

                                    @if($hasWebDiscount)
                                        <div class="text-success" style="font-size: .75rem;">
                                            Sconto web: -€ {{ number_format($webDiscountTotal, 3, ',', '.') }}
                                        </div>
                                    @endif

                                    @if($hasWebDiscount && $basePrice !== null)
                                        <div class="text-muted text-decoration-line-through" style="font-size: .75rem;">
                                            Base ERP: € {{ number_format($basePrice, 3, ',', '.') }} cad.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-2">
                        <button
                            type="button"
                            class="btn btn-sm btn-link text-danger p-0"
                            data-cart-remove
                            data-remove-url="{{ route('storefront.cart.remove', array_merge(['item' => $item], $contextParams)) }}"
                            data-method="DELETE"
                            data-item-id="{{ $item->id }}"
                        >
                            Rimuovi
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="border-top mt-3 pt-3">
            <div class="d-flex justify-content-between align-items-center small mb-2">
                <span>Subtotale finale</span>
                <span data-minicart-total>
                    € {{ number_format($cartTotal, 3, ',', '.') }}
                </span>
            </div>

            @if($cartDiscountTotal > 0)
                <div class="d-flex justify-content-between align-items-center small mb-3 text-success">
                    <span>Sconti web</span>
                    <span>-€ {{ number_format($cartDiscountTotal, 3, ',', '.') }}</span>
                </div>
            @else
                <div class="mb-3"></div>
            @endif

            <div class="d-grid gap-2">
                <a href="{{ route('storefront.cart.index', $contextParams) }}" class="btn btn-sm btn-outline-secondary">
                    Vai al carrello
                </a>

                <a href="{{ route('storefront.checkout.show', $contextParams) }}" class="btn btn-sm btn-primary">
                    Checkout
                </a>
            </div>
        </div>
    @endif
</div>