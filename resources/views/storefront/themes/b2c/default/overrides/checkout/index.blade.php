@extends($storefrontLayout)

@section('title', 'Checkout')

@section('content')
<div
    class="container py-5 checkout-page"
    data-checkout-mode="{{ $isB2b ? 'b2b' : 'b2c' }}"
    data-checkout-url="{{ route('storefront.checkout.show') }}"
    data-payment-preview-url="{{ route('storefront.checkout.payment.preview') }}"
    data-shipping-storage-key="{{ $shippingSelectionStorageKey }}"
    data-stripe-key="{{ $paymentConfig['stripe_key'] ?? '' }}"
    data-stripe-return-url="{{ route('storefront.payment.stripe.success') }}"
    data-paypal-capture-url="{{ route('storefront.payment.paypal.capture') }}"
>
    @include('storefront.themes.b2c.default.overrides.checkout.partials.header')
    @include('storefront.themes.b2c.default.overrides.checkout.partials.alerts')

    @if (!$cart || $items->isEmpty())
        @include('storefront.themes.b2c.default.overrides.checkout.partials.empty-cart')
    @else
        <form method="POST" action="{{ route('storefront.checkout.place') }}" id="checkout-place-form" class="d-none">
            @csrf
        </form>

        <div class="row g-4">
            <div class="col-12 col-xl-4">
                @if($isB2b)
                    @include('storefront.themes.b2c.default.overrides.checkout.partials.shipping-addresses')
                    @include('storefront.themes.b2c.default.overrides.checkout.partials.billing-summary')
                @else
                    @include('storefront.themes.b2c.default.overrides.checkout.partials.customer-account')
                    @include('storefront.themes.b2c.default.overrides.checkout.partials.customer-shipping-form')
                    @include('storefront.themes.b2c.default.overrides.checkout.partials.customer-billing-form')
                @endif
            </div>

            <div class="col-12 col-xl-4">
                @include('storefront.themes.b2c.default.overrides.checkout.partials.shipping-cost')

                @if($isB2b)
                    @include('storefront.themes.b2c.default.overrides.checkout.partials.bank-details')
                @else
                    @include('storefront.themes.b2c.default.overrides.checkout.partials.payment-methods')
                @endif

                @include('storefront.themes.b2c.default.overrides.checkout.partials.notes')
            </div>

            <div class="col-12 col-xl-4">
                @include('storefront.themes.b2c.default.overrides.checkout.partials.summary')
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
    @if($isB2b && $shippingAddresses->isNotEmpty())
        <script src="{{ asset('js/checkout-b2b.js') }}" defer></script>
    @else
        <script src="https://js.stripe.com/v3/"></script>

        @if(!empty($paymentConfig['paypal_client_id']))
            <script src="https://www.paypal.com/sdk/js?client-id={{ urlencode($paymentConfig['paypal_client_id']) }}&currency={{ urlencode($paymentConfig['currency'] ?? 'EUR') }}&intent={{ urlencode($paymentConfig['paypal_intent'] ?? 'authorize') }}&components=buttons"></script>
        @endif

        <script src="{{ asset('js/checkout-b2c.js') }}" defer></script>
    @endif
@endpush
