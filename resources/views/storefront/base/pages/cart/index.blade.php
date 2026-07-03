@extends($storefrontLayout)

@section('title', __('themes_b2c.cart.cart'))

@section('content')
@php
    $items = collect($items ?? []);
    $isB2b = (bool) ($store->is_b2b ?? false);
    $priceDecimals = $isB2b ? 3 : 2;
    $cartImportErrors = collect(session('cart_import_errors', []));
    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];
    $isAgentContext = session('agent_mode') === true && $agentContextId !== '' && is_array(session("agent_contexts.$agentContextId"));

    $cartTotals = is_array($cartTotals ?? null) ? $cartTotals : [];

    $cartMeta = $cart?->meta ?? [];
    if (is_string($cartMeta)) {
        $cartMeta = json_decode($cartMeta, true) ?: [];
    }
    $cartMeta = is_array($cartMeta) ? $cartMeta : [];

    $promotions = is_array($promotions ?? null)
        ? $promotions
        : (is_array(data_get($cartMeta, 'promotions')) ? data_get($cartMeta, 'promotions') : []);

    $appliedCoupons = collect($promotions['applied_coupons'] ?? []);
    $appliedPromotions = collect($promotions['applied_promotions'] ?? []);

    $appliedCouponCode = $appliedCoupons
        ->pluck('code')
        ->filter(fn ($code) => is_string($code) && trim($code) !== '')
        ->first();

    $storedCouponCode = data_get($cartMeta, 'coupon.code');

    $displayCouponCode = $appliedCouponCode ?: (
        is_string($storedCouponCode) && trim($storedCouponCode) !== ''
            ? trim($storedCouponCode)
            : null
    );

    $subtotal = (float) ($cartTotals['subtotal'] ?? ($cart->subtotal ?? 0));
    $discountTotal = (float) ($cartTotals['discount_total'] ?? ($cart->discount_total ?? 0));

    $shippingDetails = is_array($shippingDetails ?? null) ? $shippingDetails : [];
    $shippingAvailable = (bool) ($shippingDetails['available'] ?? false);
    $shippingMessage = trim((string) ($shippingDetails['message'] ?? ''));
    $shippingIsFree = (bool) ($shippingDetails['is_free'] ?? false);
    $shippingTotal = isset($shippingCost)
        ? (float) $shippingCost
        : (float) ($shippingDetails['amount'] ?? ($cart->shipping_total ?? 0));

    $grandTotal = (float) ($cartTotals['grand_total'] ?? max(0, $subtotal - $discountTotal + ($shippingAvailable ? $shippingTotal : 0)));
@endphp

<div class="container py-5 cart-page" data-cart-page>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ __('themes_b2c.cart.cart') }}</h1>
            <div class="text-muted small">{{ __('themes_b2c.cart.page_description') }}</div>
            @if($isAgentContext)
                <div class="alert alert-warning border-0 mt-3 mb-0 small">
                    <i class="fa-solid fa-user-tie me-1"></i>
                    {{ __('themes_b2c.cart.agent_context_notice') }}
                </div>
            @endif
        </div>

        <div class="d-flex flex-wrap gap-2">
            @if($isB2b && Route::has('storefront.cart.import'))
                <button
                    type="button"
                    class="btn btn-success btn-sm"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#storefrontCartImport"
                    aria-controls="storefrontCartImport"
                >
                    <i class="fa-solid fa-bolt me-2"></i>
                    {{ __('themes_b2c.cart.quick_order') }}
                </button>
            @endif

            @if($cart && $items->isNotEmpty() && Route::has('storefront.cart.clear'))
                <form
                    method="POST"
                    action="{{ route('storefront.cart.clear', $contextParams) }}"
                    class="d-inline-block m-0"
                    onsubmit="return confirm('{{ __('themes_b2c.cart.clear_confirm_complete') }}');"
                >
                    @csrf
                    @method('DELETE')
                    @if($agentContextId !== '')
                        <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                    @endif

                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="fa-solid fa-trash-can me-2"></i>
                        {{ __('themes_b2c.cart.clear_cart') }}
                    </button>
                </form>
            @endif

            <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-2"></i>
                {{ __('themes_b2c.checkout.continue_shopping') }}
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
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

    @if($cartImportErrors->isNotEmpty())
        <div class="alert alert-warning">
            <div class="fw-semibold mb-2">{{ __('themes_b2c.cart.import_rows_not_imported') }}</div>
            <ul class="mb-0 ps-3">
                @foreach($cartImportErrors->take(20) as $importError)
                    <li>{{ $importError }}</li>
                @endforeach
            </ul>

            @if($cartImportErrors->count() > 20)
                <div class="small mt-2">
                    {{ __('themes_b2c.cart.more_errors_not_shown', ['count' => $cartImportErrors->count() - 20]) }}
                </div>
            @endif
        </div>
    @endif



    @if(!$cart || $items->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body py-5 text-center">
                <div class="mb-3 text-muted">
                    <i class="fa-solid fa-cart-shopping fa-2x"></i>
                </div>

                <h5 class="mb-2">{{ __('themes_b2c.cart.empty') }}</h5>
                <p class="text-muted mb-4">{{ __('themes_b2c.cart.add_products') }}</p>

                <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="btn btn-primary">
                    {{ __('themes_b2c.cart.view_catalog') }}
                </a>
            </div>
        </div>
    @else

        @if(!$shippingAvailable && $shippingMessage !== '')
            <div class="alert alert-warning">
                <div class="fw-semibold mb-1">{{ __('themes_b2c.cart.shipping_not_yet_available') }}</div>
                <div>{{ $shippingMessage }}</div>
            </div>
        @endif


        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table cart-table align-top mb-0">
                                <colgroup>
                                    <col style="width: 48%;">
                                    <col style="width: 24%;">
                                    <col style="width: 11%;">
                                    <col style="width: 11%;">
                                    <col style="width: 6%;">
                                </colgroup>
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4 py-3">{{ __('themes_b2c.product.product') }}</th>
                                        <th class="py-3">{{ __('themes_b2c.cart.quantity') }}</th>
                                        <th class="py-3 text-end">{{ __('themes_b2c.product.unit_price') }}</th>
                                        <th class="py-3 text-end">{{ __('themes_b2c.cart.row_total') }}</th>
                                        <th class="py-3 text-center pe-4">{{ __('themes_b2c.cart.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $item)
                                        @php
                                            $quantityMin = max(1, (int) ($item->quantity_min ?? 1));
                                            $quantityStep = max(1, (int) ($item->quantity_step ?? 1));
                                            $packMultiple = max(1, (int) ($item->pack_multiple ?? 1));
                                            $showPackMultiple = (bool) ($item->show_pack_multiple ?? false);

                                            $basePrice = $item->base_price !== null ? (float) $item->base_price : null;
                                            $finalPrice = $item->final_price !== null ? (float) $item->final_price : ($item->price !== null ? (float) $item->price : null);
                                            $baseRowTotal = $item->base_row_total !== null ? (float) $item->base_row_total : null;
                                            $finalRowTotal = $item->final_row_total !== null ? (float) $item->final_row_total : ($item->row_total !== null ? (float) $item->row_total : null);
                                            $webDiscountTotal = $item->web_discount_total !== null ? (float) $item->web_discount_total : 0.0;
                                            $hasLineDiscount = $webDiscountTotal > 0.000;
                                            $thumbnailUrl = media_url($item->product_thumbnail_url);
                                        @endphp

                                        <tr class="align-top">
                                            <td class="ps-4 py-4">
                                                <div class="d-flex align-items-start gap-3">
                                                    <div class="flex-shrink-0">
                                                        @if($thumbnailUrl)
                                                            <img src="{{ $thumbnailUrl }}" alt="{{ $item->product_name ?? $item->sku }}" class="rounded border" style="width: 64px; height: 64px; object-fit: cover;">
                                                        @else
                                                            <div class="rounded border d-flex align-items-center justify-content-center bg-light text-muted" style="width: 64px; height: 64px;">
                                                                <i class="fa-solid fa-image"></i>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="flex-grow-1">
                                                        <div class="fw-semibold mb-1">
                                                            {{ $item->product_name ?? $item->sku }}
                                                        </div>

                                                        <div class="small text-muted mb-2">
                                                            SKU: {{ $item->sku }}
                                                        </div>

                                                        @if(!empty($item->product_description))
                                                            <div class="small text-muted cart-product-description">
                                                                {{ $item->product_description }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="py-4">
                                                <form method="POST" action="{{ route('storefront.cart.update', array_merge(['item' => $item], $contextParams)) }}" class="cart-update-form">
                                                    @csrf
                                                    @if($agentContextId !== '')
                                                        <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                                                    @endif

                                                    <div class="d-flex flex-column gap-2">
                                                        <div class="d-flex align-items-center gap-2 cart-qty-row">
                                                            <input type="number" name="qty" value="{{ number_format((float) $item->quantity, 0, '.', '') }}" min="{{ $quantityMin }}" step="{{ $quantityStep }}" inputmode="numeric" class="form-control form-control-sm cart-qty-input" data-qty-min="{{ $quantityMin }}" data-qty-step="{{ $quantityStep }}">

                                                            <button type="submit" class="btn btn-sm btn-outline-secondary cart-update-btn">
                                                                {{ __('themes_b2c.cart.update') }}
                                                            </button>
                                                        </div>

                                                        <div class="small text-muted lh-sm">
                                                            {{ __('themes_b2c.cart.minimum') }} <strong>{{ number_format($quantityMin, 0, ',', '.') }}</strong>
                                                            @if($showPackMultiple)
                                                                <span class="d-block d-xl-inline">
                                                                    · {{ __('themes_b2c.cart.multiples_of') }} <strong>{{ number_format($packMultiple, 0, ',', '.') }}</strong>
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </form>
                                            </td>

                                            <td class="py-4 text-end text-nowrap">
                                                @if($finalPrice !== null)
                                                    @if($hasLineDiscount && $basePrice !== null)
                                                        <div class="small text-muted text-decoration-line-through">
                                                            € {{ number_format($basePrice, $priceDecimals, ',', '.') }}
                                                        </div>
                                                    @endif

                                                    <div class="fw-semibold">
                                                        € {{ number_format($finalPrice, $priceDecimals, ',', '.') }}
                                                    </div>

                                                    @if($hasLineDiscount)
                                                        <div class="small text-success">
                                                            {{ __('themes_b2c.cart.web_discount') }}: -€ {{ number_format($webDiscountTotal / max((float) ($item->quantity ?? 1), 1), $priceDecimals, ',', '.') }} / {{ __('themes_b2c.cart.each') }}
                                                        </div>
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </td>

                                            <td class="py-4 text-end text-nowrap">
                                                @if($finalRowTotal !== null)
                                                    @if($hasLineDiscount && $baseRowTotal !== null)
                                                        <div class="small text-muted text-decoration-line-through">
                                                            € {{ number_format($baseRowTotal, $priceDecimals, ',', '.') }}
                                                        </div>
                                                    @endif

                                                    <div class="fw-semibold">
                                                        € {{ number_format($finalRowTotal, $priceDecimals, ',', '.') }}
                                                    </div>

                                                    @if($hasLineDiscount)
                                                        <div class="small text-success">
                                                            {{ __('themes_b2c.cart.savings') }}: -€ {{ number_format($webDiscountTotal, $priceDecimals, ',', '.') }}
                                                        </div>
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </td>

                                            <td class="py-4 text-center pe-4">
                                                <form method="POST" action="{{ route('storefront.cart.remove', array_merge(['item' => $item], $contextParams)) }}" class="d-inline-block m-0">
                                                    @csrf
                                                    @method('DELETE')
                                                    @if($agentContextId !== '')
                                                        <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                                                    @endif
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('themes_b2c.cart.remove_product') }}">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="top: 1.5rem;">
                    <div class="card-body">
                        <h5 class="mb-3">{{ __('themes_b2c.cart.totals') }}</h5>

                        <div class="mb-4">
                            @if($displayCouponCode)
                                <div class="border rounded-3 p-3 bg-light-subtle">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="small text-muted">{{ __('themes_b2c.cart.applied_coupon') }}</div>
                                            <div class="fw-semibold">{{ $displayCouponCode }}</div>
                                        </div>

                                        <form method="POST" action="{{ route('storefront.cart.coupon.remove', $contextParams) }}" class="m-0">
                                            @csrf
                                            @method('DELETE')
                                            @if($agentContextId !== '')
                                                <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                                            @endif

                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                {{ __('themes_b2c.cart.remove_coupon') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                <form method="POST" action="{{ route('storefront.cart.coupon.apply', $contextParams) }}">
                                    @csrf
                                    @if($agentContextId !== '')
                                        <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                                    @endif

                                    <label for="coupon_code" class="form-label fw-semibold">{{ __('themes_b2c.cart.coupon') }}</label>

                                    <div class="input-group">
                                        <input
                                            type="text"
                                            name="coupon_code"
                                            id="coupon_code"
                                            class="form-control @error('coupon_code') is-invalid @enderror"
                                            value="{{ old('coupon_code') }}"
                                            placeholder="{{ __('themes_b2c.cart.coupon_placeholder') }}"
                                            maxlength="80"
                                        >

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
                                        <span>Coupon {{ $coupon['code'] ?? '' }}</span>
                                        <span>-€ {{ number_format((float) ($coupon['discount_total'] ?? 0), $priceDecimals, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ __('themes_b2c.cart.subtotal') }}</span>
                            <span>€ {{ number_format($subtotal, $priceDecimals, ',', '.') }}</span>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ __('themes_b2c.cart.web_discounts') }}</span>
                            <span class="text-success">
                                -€ {{ number_format($discountTotal, $priceDecimals, ',', '.') }}
                            </span>
                        </div>

                        <div class="d-flex justify-content-between mb-1">
                            <span>{{ __('themes_b2c.checkout.shipping') }}</span>
                            <span>
                                @if(!$shippingAvailable)
                                    <span class="text-danger">{{ __('themes_b2c.checkout.not_available') }}</span>
                                @elseif($shippingIsFree)
                                    {{ __('themes_b2c.checkout.free') }}
                                @else
                                    € {{ number_format($shippingTotal, $priceDecimals, ',', '.') }}
                                @endif
                            </span>
                        </div>

                        @if($shippingMessage !== '')
                            <div class="small {{ $shippingAvailable ? 'text-muted' : 'text-danger' }} mb-3">
                                {{ $shippingMessage }}
                            </div>
                        @else
                            <div class="mb-3"></div>
                        @endif

                        @if($discountTotal > 0)
                            <div class="small text-success mb-3">
                                {{ __('themes_b2c.cart.order_savings', ['amount' => number_format($discountTotal, $priceDecimals, ',', '.')]) }}
                            </div>
                        @endif

                        <div class="d-flex justify-content-between fw-bold fs-5 mb-4">
                            <span>{{ __('themes_b2c.checkout.total') }}</span>
                            <span>€ {{ number_format($grandTotal, $priceDecimals, ',', '.') }}</span>
                        </div>

                        <div class="d-grid gap-2">
                            <a
                                href="{{ $shippingAvailable ? route('storefront.checkout.show', $contextParams) : '#' }}"
                                class="btn btn-primary {{ $shippingAvailable ? '' : 'disabled' }}"
                                {{ $shippingAvailable ? '' : 'aria-disabled=true tabindex=-1' }}
                            >
                                {{ __('themes_b2c.cart.go_to_checkout') }}
                            </a>

                            @if($cart && $items->isNotEmpty() && Route::has('storefront.cart.clear'))
                                <form
                                    method="POST"
                                    action="{{ route('storefront.cart.clear', $contextParams) }}"
                                    class="d-grid m-0"
                                    onsubmit="return confirm('{{ __('themes_b2c.cart.clear_confirm_complete') }}');"
                                >
                                    @csrf
                                    @method('DELETE')
                                    @if($agentContextId !== '')
                                        <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                                    @endif

                                    <button type="submit" class="btn btn-outline-danger">
                                        <i class="fa-solid fa-trash-can me-2"></i>
                                        {{ __('themes_b2c.cart.clear_cart') }}
                                    </button>
                                </form>
                            @endif

                            <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="btn btn-outline-secondary">
                                {{ __('themes_b2c.checkout.continue_shopping') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    @endif

</div>

@includeIf('storefront.base.partials.cart-import-offcanvas', [
    'store' => $store,
    'cart' => $cart,
    'items' => $items,
    'contextParams' => $contextParams,
    'agentContextId' => $agentContextId,
])
@endsection
