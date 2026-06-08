@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-2">Controlla i dati inseriti:</div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div id="checkout-shipping-warning" class="alert alert-warning {{ !$shippingAvailable && $shippingMessage !== '' ? '' : 'd-none' }}">
    <div class="fw-semibold mb-1">Spedizione non disponibile</div>
    <div id="checkout-shipping-warning-message">{{ $shippingMessage }}</div>
</div>