<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h5 class="mb-3">Costo spedizione</h5>

        <div class="border rounded-3 p-3 bg-light-subtle">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted">Spedizione</span>
                <span class="fw-semibold" id="checkout-shipping-price">
                    @if(!$shippingAvailable)
                        <span class="text-danger">Non disponibile</span>
                    @elseif($shippingIsFree)
                        Gratis
                    @else
                        € {{ number_format($shippingTotal, 3, ',', '.') }}
                    @endif
                </span>
            </div>

            <div id="checkout-shipping-message" class="small {{ $shippingAvailable ? 'text-muted' : 'text-danger' }}">
                {{ $shippingMessage !== '' ? $shippingMessage : 'Il costo finale viene calcolato in base a destinazione e logica spedizione dello store.' }}
            </div>
        </div>
    </div>
</div>