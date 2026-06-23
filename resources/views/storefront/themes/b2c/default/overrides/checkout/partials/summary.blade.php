@php($priceDecimals = 2)

<div class="card border-0 shadow-sm sticky-top" style="top: 1.5rem;">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
            <h5 class="mb-0">Riepilogo ordine</h5>
            <span id="checkout-summary-spinner" class="spinner-border spinner-border-sm text-muted d-none" role="status" aria-hidden="true"></span>
        </div>

        <div class="mb-3 d-flex flex-column gap-3">
            @foreach($items as $item)
                @php($thumbnailUrl = media_url($item->product_thumbnail_url))
                <div class="d-flex align-items-start gap-3 pb-3 border-bottom">
                    <div class="flex-shrink-0">
                        @if($thumbnailUrl)
                            <img src="{{ $thumbnailUrl }}" alt="{{ $item->product_name ?? $item->sku }}" class="rounded border" style="width: 64px; height: 64px; object-fit: cover;">
                        @else
                            <div class="rounded border d-flex align-items-center justify-content-center bg-light text-muted" style="width: 64px; height: 64px;">
                                <i class="fa-solid fa-image"></i>
                            </div>
                        @endif
                    </div>

                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold small">{{ $item->product_name ?? $item->sku }}</div>
                        <div class="text-muted small mb-2">SKU: {{ $item->sku }}</div>

                        <form method="POST" action="{{ route('storefront.cart.update', $item) }}" class="checkout-cart-update-form">
                            @csrf

                            <div class="d-flex align-items-center gap-2 mb-2">
                                <input type="number" name="qty" value="{{ number_format((float) $item->quantity, 0, '.', '') }}" min="{{ $item->quantity_min }}" step="{{ $item->quantity_step }}" inputmode="numeric" class="form-control form-control-sm cart-qty-input" style="width: 90px;" data-qty-min="{{ $item->quantity_min }}" data-qty-step="{{ $item->quantity_step }}">

                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    Aggiorna
                                </button>
                            </div>

                            <div class="text-muted small">
                                Quantità: {{ number_format((float) $item->quantity, 0, ',', '.') }}
                                · Minimo {{ number_format((float) $item->quantity_min, 0, ',', '.') }}

                                @if($item->show_pack_multiple)
                                    · Multipli di {{ number_format((float) $item->pack_multiple, 0, ',', '.') }}
                                @endif
                            </div>
                        </form>

                        <div class="small mt-2 d-flex flex-column gap-1">
                            @if($item->base_price !== null && $item->final_price !== null && (float) $item->base_price !== (float) $item->final_price)
                                <div class="text-muted">Prezzo base: € {{ number_format((float) $item->base_price, $priceDecimals, ',', '.') }}</div>
                                <div class="fw-semibold">Prezzo finale: € {{ number_format((float) $item->final_price, $priceDecimals, ',', '.') }}</div>
                            @elseif($item->final_price !== null)
                                <div class="fw-semibold">Prezzo unitario: € {{ number_format((float) $item->final_price, $priceDecimals, ',', '.') }}</div>
                            @endif

                            @if((float) ($item->web_discount_total ?? 0) > 0)
                                <div class="text-success">Sconto web: - € {{ number_format((float) $item->web_discount_total, $priceDecimals, ',', '.') }}</div>
                            @endif

                            <div class="fw-semibold mt-1">
                                Totale riga: € {{ number_format((float) ($item->final_row_total ?? 0), $priceDecimals, ',', '.') }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mb-4">
            @if($displayCouponCode)
                <div class="border rounded-3 p-3 bg-light-subtle">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="small text-muted">Coupon applicato</div>
                            <div class="fw-semibold">{{ $displayCouponCode }}</div>
                        </div>

                        <form method="POST" action="{{ route('storefront.cart.coupon.remove') }}" class="m-0">
                            @csrf
                            @method('DELETE')

                            @if($isB2b)
                                <input type="hidden" name="shipping_address_id" value="{{ $selectedShippingAddressId }}" data-shipping-address-hidden>
                            @endif

                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                Rimuovi coupon
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('storefront.cart.coupon.apply') }}">
                    @csrf

                    @if($isB2b)
                        <input type="hidden" name="shipping_address_id" value="{{ $selectedShippingAddressId }}" data-shipping-address-hidden>
                    @endif

                    <label for="checkout_coupon_code" class="form-label fw-semibold">Coupon</label>

                    <div class="input-group">
                        <input type="text" name="coupon_code" id="checkout_coupon_code" class="form-control @error('coupon_code') is-invalid @enderror" value="{{ old('coupon_code') }}" placeholder="Inserisci codice coupon" maxlength="80">

                        <button type="submit" class="btn btn-outline-primary">
                            Applica
                        </button>
                    </div>

                    @error('coupon_code')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </form>
            @endif
        </div>

        <div class="d-flex justify-content-between mb-2">
            <span>Subtotale</span>
            <span id="checkout-subtotal">€ {{ number_format($subtotal, $priceDecimals, ',', '.') }}</span>
        </div>

        <div class="d-flex justify-content-between mb-2">
            <span>Sconto promo web</span>
            <span id="checkout-discount" class="text-success">- € {{ number_format($discountTotal, $priceDecimals, ',', '.') }}</span>
        </div>

        @if($appliedPromotions->isNotEmpty() || $appliedCoupons->isNotEmpty())
            <div class="mb-3">
                @foreach($appliedPromotions as $promotion)
                    <div class="small text-success d-flex justify-content-between gap-2">
                        <span>{{ $promotion['name'] ?? $promotion['code'] ?? 'Promozione' }}</span>
                        <span>-€ {{ number_format((float) ($promotion['discount_total'] ?? 0), $priceDecimals, ',', '.') }}</span>
                    </div>
                @endforeach

                @foreach($appliedCoupons as $coupon)
                    <div class="small text-success d-flex justify-content-between gap-2">
                        <span>Coupon {{ $coupon['code'] ?? '' }}</span>
                        <span>-€ {{ number_format((float) ($coupon['discount_total'] ?? 0), $priceDecimals, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="d-flex justify-content-between mb-3">
            <span>Spedizione</span>
            <span id="checkout-summary-shipping">
                @if(!$shippingAvailable)
                    <span class="text-danger">Non disponibile</span>
                @elseif($shippingIsFree)
                    Gratis
                @else
                    € {{ number_format($shippingTotal, $priceDecimals, ',', '.') }}
                @endif
            </span>
        </div>

        <div id="checkout-summary-message" class="small {{ $shippingAvailable ? 'text-muted' : 'text-danger' }} {{ $shippingMessage !== '' ? 'mb-3' : '' }}">
            {{ $shippingMessage }}
        </div>

        <hr>

        <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
            <span>Totale finale</span>
            <span id="checkout-grand-total">€ {{ number_format($grandTotal, $priceDecimals, ',', '.') }}</span>
        </div>

        <div class="text-muted small mb-3">
            {{ $isB2b
                ? 'L\'ordine verrà registrato e poi gestito secondo le condizioni commerciali del tuo account.'
                : 'Dopo la conferma verrai reindirizzato al pagamento sicuro selezionato.' }}
        </div>

        <div class="d-grid gap-2">
            <button
                id="checkout-submit-button"
                type="submit"
                form="checkout-place-form"
                class="btn btn-success"
                {{ (($isB2b && $shippingAddresses->isEmpty()) || !$shippingAvailable) ? 'disabled' : '' }}
            >
                <i class="fa-solid fa-check me-2"></i>
                {{ $isB2b ? 'Conferma ordine' : 'Conferma e paga' }}
            </button>

            <a href="{{ route('storefront.cart.index') }}" class="btn btn-outline-secondary">
                Modifica carrello
            </a>
        </div>
    </div>
</div>
