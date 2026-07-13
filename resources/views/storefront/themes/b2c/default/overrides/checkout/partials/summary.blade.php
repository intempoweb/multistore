@php($priceDecimals = 2)

<div class="storefront-checkout-summary-card card border-0 shadow-sm sticky-top">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
            <h5 class="mb-0">{{ __('themes_b2c.checkout.order_summary') }}</h5>
            <span id="checkout-summary-spinner" class="spinner-border spinner-border-sm text-muted d-none" role="status" aria-hidden="true"></span>
        </div>

        <div class="mb-3 d-flex flex-column gap-3">
            @foreach($items as $item)
                @php($thumbnailUrl = media_url($item->product_thumbnail_url))
                <div class="d-flex align-items-start gap-3 pb-3 border-bottom">
                    <div class="flex-shrink-0">
                        @if($thumbnailUrl)
                            <img src="{{ $thumbnailUrl }}" alt="{{ $item->product_name ?? $item->sku }}" class="storefront-checkout-summary-thumb rounded border">
                        @else
                            <div class="storefront-checkout-summary-thumb rounded border d-flex align-items-center justify-content-center bg-light text-muted">
                                <i class="fa-solid fa-image"></i>
                            </div>
                        @endif
                    </div>

                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold small">{{ $item->product_name ?? $item->sku }}</div>
                        <div class="text-muted small mb-2">{{ __('themes_b2c.product.sku') }}: {{ $item->sku }}</div>

                        <form method="POST" action="{{ route('storefront.cart.update', $item) }}" class="checkout-cart-update-form">
                            @csrf

                            <div class="d-flex align-items-center gap-2 mb-2">
                                <input type="number" name="qty" value="{{ number_format((float) $item->quantity, 0, '.', '') }}" min="{{ $item->quantity_min }}" step="{{ $item->quantity_step }}" inputmode="numeric" class="storefront-checkout-qty-input form-control form-control-sm cart-qty-input" data-qty-min="{{ $item->quantity_min }}" data-qty-step="{{ $item->quantity_step }}">

                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    {{ __('themes_b2c.cart.update') }}
                                </button>
                            </div>

                            <div class="text-muted small">
                                {{ __('themes_b2c.product.quantity') }}: {{ number_format((float) $item->quantity, 0, ',', '.') }}
                                · {{ __('themes_b2c.cart.minimum') }} {{ number_format((float) $item->quantity_min, 0, ',', '.') }}

                                @if($item->show_pack_multiple)
                                    · {{ __('themes_b2c.cart.multiples_of') }} {{ number_format((float) $item->pack_multiple, 0, ',', '.') }}
                                @endif
                            </div>
                        </form>

                        <div class="small mt-2 d-flex flex-column gap-1">
                            @if($item->base_price !== null && $item->final_price !== null && (float) $item->base_price !== (float) $item->final_price)
                                <div class="text-muted">{{ __('themes_b2c.cart.base_price') }}: € {{ number_format((float) $item->base_price, $priceDecimals, ',', '.') }}</div>
                                <div class="fw-semibold">{{ __('themes_b2c.cart.final_price') }}: € {{ number_format((float) $item->final_price, $priceDecimals, ',', '.') }}</div>
                            @elseif($item->final_price !== null)
                                <div class="fw-semibold">{{ __('themes_b2c.product.unit_price') }}: € {{ number_format((float) $item->final_price, $priceDecimals, ',', '.') }}</div>
                            @endif

                            @if((float) ($item->web_discount_total ?? 0) > 0)
                                <div class="text-success">{{ __('themes_b2c.cart.web_discount') }}: - € {{ number_format((float) $item->web_discount_total, $priceDecimals, ',', '.') }}</div>
                            @endif

                            <div class="fw-semibold mt-1">
                                {{ __('themes_b2c.cart.row_total') }}: € {{ number_format((float) ($item->final_row_total ?? 0), $priceDecimals, ',', '.') }}
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
                            <div class="small text-muted">{{ __('themes_b2c.cart.applied_coupon') }}</div>
                            <div class="fw-semibold">{{ $displayCouponCode }}</div>
                        </div>

                        <form method="POST" action="{{ route('storefront.cart.coupon.remove') }}" class="m-0">
                            @csrf
                            @method('DELETE')

                            @if($isB2b)
                                <input type="hidden" name="shipping_address_id" value="{{ $selectedShippingAddressId }}" data-shipping-address-hidden>
                            @endif

                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                {{ __('themes_b2c.cart.remove_coupon') }}
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

                    <label for="checkout_coupon_code" class="form-label fw-semibold">{{ __('themes_b2c.cart.coupon') }}</label>

                    <div class="input-group">
                        <input type="text" name="coupon_code" id="checkout_coupon_code" class="form-control @error('coupon_code') is-invalid @enderror" value="{{ old('coupon_code') }}" placeholder="{{ __('themes_b2c.cart.coupon_placeholder') }}" maxlength="80">

                        <button type="submit" class="btn btn-outline-primary">
                            {{ __('themes_b2c.cart.apply') }}
                        </button>
                    </div>

                    @error('coupon_code')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </form>
            @endif
        </div>

        <div class="d-flex justify-content-between mb-2">
            <span>{{ __('themes_b2c.cart.subtotal') }}</span>
            <span id="checkout-subtotal">€ {{ number_format($subtotal, $priceDecimals, ',', '.') }}</span>
        </div>

        <div class="d-flex justify-content-between mb-2">
            <span>{{ __('themes_b2c.cart.web_discount') }}</span>
            <span id="checkout-discount" class="text-success">- € {{ number_format($discountTotal, $priceDecimals, ',', '.') }}</span>
        </div>

        @if($appliedPromotions->isNotEmpty() || $appliedCoupons->isNotEmpty())
            <div class="mb-3">
                @foreach($appliedPromotions as $promotion)
                    <div class="small text-success d-flex justify-content-between gap-2">
                        <span>{{ $promotion['name'] ?? $promotion['code'] ?? __('themes_b2c.cart.promotion') }}</span>
                        <span>-€ {{ number_format((float) ($promotion['discount_total'] ?? 0), $priceDecimals, ',', '.') }}</span>
                    </div>
                @endforeach

                @foreach($appliedCoupons as $coupon)
                    <div class="small text-success d-flex justify-content-between gap-2">
                        <span>{{ __('themes_b2c.cart.coupon') }} {{ $coupon['code'] ?? '' }}</span>
                        <span>-€ {{ number_format((float) ($coupon['discount_total'] ?? 0), $priceDecimals, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="d-flex justify-content-between mb-3">
            <span>{{ __('themes_b2c.checkout.shipping') }}</span>
            <span id="checkout-summary-shipping">
                @if(!$shippingAvailable)
                    <span class="text-danger">{{ __('themes_b2c.checkout.unavailable') }}</span>
                @elseif($shippingIsFree)
                    {{ __('themes_b2c.checkout.free') }}
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
            <span>{{ __('themes_b2c.checkout.final_total') }}</span>
            <span id="checkout-grand-total">€ {{ number_format($grandTotal, $priceDecimals, ',', '.') }}</span>
        </div>

        <div class="text-muted small mb-3">
            {{ $isB2b
                ? __('themes_b2c.checkout.b2b_order_note')
                : __('themes_b2c.checkout.b2c_payment_note') }}
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
                {{ $isB2b ? __('themes_b2c.checkout.confirm_order') : __('themes_b2c.checkout.confirm_and_pay') }}
            </button>

            <a href="{{ route('storefront.cart.index') }}" class="btn btn-outline-secondary">
                {{ __('themes_b2c.cart.edit_cart') }}
            </a>
        </div>
    </div>
</div>
