@extends($storefrontLayout)

@section('title', __('themes_b2c.checkout.order_confirmed'))

@section('content')
<div class="container py-5 checkout-success-page">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5 text-center">
                    <div class="text-success mb-3">
                        <i class="fa-solid fa-circle-check fa-3x"></i>
                    </div>

                    <h1 class="h3 mb-3">{{ __('themes_b2c.checkout.order_confirmed') }}</h1>

                    <p class="text-muted mb-4">
                        {{ __('themes_b2c.checkout.thank_you', ['name' => $order->customer_name ?: __('themes_b2c.checkout.customer')]) }}, {{ __('themes_b2c.checkout.order_received') }}
                        @if($order->customer_email)
                            {{ __('themes_b2c.checkout.confirmation_email_sent', ['email' => $order->customer_email]) }}
                        @else
                            {{ __('themes_b2c.checkout.confirmation_email_generic') }}
                        @endif
                    </p>

                    <div class="border rounded-3 p-3 p-md-4 text-start bg-light-subtle mb-4">
                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span class="text-muted">{{ __('themes_b2c.checkout.order_number') }}</span>
                            <strong>{{ $order->order_number }}</strong>
                        </div>

                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span class="text-muted">{{ __('themes_b2c.checkout.payment_status') }}</span>
                            <strong>{{ $order->payment_status === 'paid' ? __('themes_b2c.checkout.paid') : ucfirst((string) $order->payment_status) }}</strong>
                        </div>

                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span class="text-muted">{{ __('themes_b2c.checkout.payment_method') }}</span>
                            <strong>{{ strtoupper((string) ($order->payment_gateway ?: $order->payment_method_label ?: '')) }}</strong>
                        </div>

                        <div class="d-flex justify-content-between gap-3">
                            <span class="text-muted">{{ __('themes_b2c.checkout.total') }}</span>
                            <strong>€ {{ number_format((float) $order->grand_total, 2, ',', '.') }}</strong>
                        </div>
                    </div>

                    @if($order->items->isNotEmpty())
                        <div class="text-start mb-4">
                            <h2 class="h6 mb-3">{{ __('themes_b2c.checkout.ordered_products') }}</h2>

                            <div class="list-group list-group-flush border rounded-3">
                                @foreach($order->items as $item)
                                    <div class="list-group-item d-flex justify-content-between gap-3">
                                        <div>
                                            <div class="fw-semibold">{{ $item->product_name ?: $item->sku }}</div>
                                            <div class="small text-muted">{{ __('themes_b2c.product.sku') }}: {{ $item->sku }}</div>
                                        </div>

                                        <div class="text-end flex-shrink-0">
                                            <div class="small text-muted">{{ __('themes_b2c.product.quantity') }} {{ number_format((float) $item->quantity, 0, ',', '.') }}</div>
                                            <div class="fw-semibold">€ {{ number_format((float) $item->row_total, 2, ',', '.') }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="alert alert-info text-start mb-4">
                        {{ __('themes_b2c.checkout.track_order_message') }}
                    </div>

                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2 mb-3">
                        @if(Route::has('storefront.orders.show'))
                            <a href="{{ route('storefront.orders.show', $order->order_number) }}" class="btn btn-success">
                                {{ __('themes_b2c.checkout.track_order') }}
                            </a>
                        @endif

                        @if(Route::has('storefront.account.orders.show'))
                            <a href="{{ route('storefront.account.orders.show', $order) }}" class="btn btn-outline-primary">
                                {{ __('themes_b2c.checkout.order_details') }}
                            </a>
                        @endif
                    </div>
                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                        <a href="{{ route('storefront.catalog.index') }}" class="btn btn-primary">
                            {{ __('themes_b2c.checkout.continue_shopping') }}
                        </a>

                        <a href="{{ route('storefront.home') }}" class="btn btn-outline-secondary">
                            {{ __('themes_b2c.header.home') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
