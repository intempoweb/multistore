@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-2">{{ __('themes_b2c.checkout.check_entered_data') }}</div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div id="checkout-shipping-warning" class="alert alert-warning {{ !$shippingAvailable && $shippingMessage !== '' ? '' : 'd-none' }}">
    <div class="fw-semibold mb-1">{{ __('themes_b2c.checkout.shipping_unavailable') }}</div>
    <div id="checkout-shipping-warning-message">{{ $shippingMessage }}</div>
</div>