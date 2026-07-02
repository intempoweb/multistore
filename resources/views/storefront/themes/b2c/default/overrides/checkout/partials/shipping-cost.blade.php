<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h5 class="mb-3">{{ __('themes_b2c.checkout.shipping') }}</h5>

        <div class="border rounded-3 p-3 bg-light-subtle">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted">{{ __('themes_b2c.checkout.shipping') }}</span>
                <span class="fw-semibold" id="checkout-shipping-price">
                    @if(!$shippingAvailable)
                        <span class="text-danger">{{ __('themes_b2c.checkout.not_available') }}</span>
                    @elseif($shippingIsFree)
                        {{ __('themes_b2c.checkout.free_shipping') }}
                    @else
                        € {{ number_format($shippingTotal, 2, ',', '.') }}
                    @endif
                </span>
            </div>

            <div id="checkout-shipping-message" class="small {{ $shippingAvailable ? 'text-muted' : 'text-danger' }}">
                {{ $shippingMessage !== '' ? $shippingMessage : __('themes_b2c.checkout.shipping_cost_message') }}
            </div>
        </div>
    </div>
</div>
