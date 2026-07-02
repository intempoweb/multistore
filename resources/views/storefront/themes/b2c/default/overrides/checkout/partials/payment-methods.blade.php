<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h5 class="mb-3">{{ __('themes_b2c.checkout.payment_method') }}</h5>

        <div class="d-flex flex-column gap-3 mb-3">
            <div class="border rounded-3 p-3 {{ $selectedPaymentGateway === 'stripe' ? 'border-primary bg-light-subtle' : '' }}" data-payment-card data-payment-card-gateway="stripe">
                <div class="d-flex align-items-start gap-3">
                    <input
                        class="form-check-input mt-1"
                        type="radio"
                        name="payment_gateway"
                        id="payment_gateway_stripe"
                        value="stripe"
                        form="checkout-place-form"
                        required
                        {{ $selectedPaymentGateway === 'stripe' ? 'checked' : '' }}
                        data-payment-radio
                    >

                    <div class="w-100">
                        <label class="fw-semibold d-block mb-0" for="payment_gateway_stripe">{{ __('themes_b2c.checkout.payment_stripe') }}</label>
                        <div class="small text-muted mb-3">
                            {{ __('themes_b2c.checkout.payment_stripe_description') }}
                        </div>

                        <div data-payment-panel="stripe" class="{{ $selectedPaymentGateway === 'stripe' ? '' : 'd-none' }}">
                            <div id="stripe-payment-element" class="border rounded-3 p-3 bg-white"></div>
                            <div id="stripe-payment-error" class="text-danger small mt-2 d-none"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border rounded-3 p-3 {{ $selectedPaymentGateway === 'paypal' ? 'border-primary bg-light-subtle' : '' }}" data-payment-card data-payment-card-gateway="paypal">
                <div class="d-flex align-items-start gap-3">
                    <input
                        class="form-check-input mt-1"
                        type="radio"
                        name="payment_gateway"
                        id="payment_gateway_paypal"
                        value="paypal"
                        form="checkout-place-form"
                        required
                        {{ $selectedPaymentGateway === 'paypal' ? 'checked' : '' }}
                        data-payment-radio
                    >

                    <div class="w-100">
                        <label class="fw-semibold d-block mb-0" for="payment_gateway_paypal">{{ __('themes_b2c.checkout.payment_paypal') }}</label>
                        <div class="small text-muted mb-3">
                            {{ __('themes_b2c.checkout.payment_paypal_description') }}
                        </div>

                        <div data-payment-panel="paypal" class="{{ $selectedPaymentGateway === 'paypal' ? '' : 'd-none' }}">
                            <div id="paypal-buttons"></div>
                            <div id="paypal-payment-error" class="text-danger small mt-2 d-none"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="checkout-payment-error" class="alert alert-danger d-none mb-0"></div>

        @error('payment_gateway')
            <div class="text-danger small mt-3">{{ $message }}</div>
        @enderror
    </div>
</div>
