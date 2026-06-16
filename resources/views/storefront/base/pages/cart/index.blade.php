@extends($storefrontLayout)

@section('title', 'Carrello')

@section('content')
@php
    $items = collect($items ?? []);
    $isB2b = (bool) ($store->is_b2b ?? false);
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
            <h1 class="mb-1">Carrello</h1>
            <div class="text-muted small">Controlla quantità, coupon e riepilogo prima del checkout.</div>
            @if($isAgentContext)
                <div class="alert alert-warning border-0 mt-3 mb-0 small">
                    <i class="fa-solid fa-user-tie me-1"></i>
                    Stai operando come agente per questo cliente.
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
                    Acquisto rapido
                </button>
            @endif

            <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-2"></i>
                Continua acquisti
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
            <div class="fw-semibold mb-2">Controlla i dati inseriti:</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($cartImportErrors->isNotEmpty())
        <div class="alert alert-warning">
            <div class="fw-semibold mb-2">Alcune righe non sono state importate:</div>
            <ul class="mb-0 ps-3">
                @foreach($cartImportErrors->take(20) as $importError)
                    <li>{{ $importError }}</li>
                @endforeach
            </ul>

            @if($cartImportErrors->count() > 20)
                <div class="small mt-2">
                    Altri {{ $cartImportErrors->count() - 20 }} errori non mostrati.
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

                <h5 class="mb-2">Il carrello è vuoto</h5>
                <p class="text-muted mb-4">Aggiungi prodotti dal catalogo per procedere con l'ordine.</p>

                <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="btn btn-primary">
                    Vai al catalogo
                </a>
            </div>
        </div>
    @else

        @if(!$shippingAvailable && $shippingMessage !== '')
            <div class="alert alert-warning">
                <div class="fw-semibold mb-1">Spedizione non ancora disponibile</div>
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
                                        <th class="ps-4 py-3">Prodotto</th>
                                        <th class="py-3">Quantità</th>
                                        <th class="py-3 text-end">Prezzo unitario</th>
                                        <th class="py-3 text-end">Totale riga</th>
                                        <th class="py-3 text-center pe-4">Azioni</th>
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
                                                                Aggiorna
                                                            </button>
                                                        </div>

                                                        <div class="small text-muted lh-sm">
                                                            Minimo <strong>{{ number_format($quantityMin, 0, ',', '.') }}</strong>
                                                            @if($showPackMultiple)
                                                                <span class="d-block d-xl-inline">
                                                                    · Multipli di <strong>{{ number_format($packMultiple, 0, ',', '.') }}</strong>
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
                                                            € {{ number_format($basePrice, 3, ',', '.') }}
                                                        </div>
                                                    @endif

                                                    <div class="fw-semibold">
                                                        € {{ number_format($finalPrice, 3, ',', '.') }}
                                                    </div>

                                                    @if($hasLineDiscount)
                                                        <div class="small text-success">
                                                            Sconto web: -€ {{ number_format($webDiscountTotal / max((float) ($item->quantity ?? 1), 1), 3, ',', '.') }} / cad
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
                                                            € {{ number_format($baseRowTotal, 3, ',', '.') }}
                                                        </div>
                                                    @endif

                                                    <div class="fw-semibold">
                                                        € {{ number_format($finalRowTotal, 3, ',', '.') }}
                                                    </div>

                                                    @if($hasLineDiscount)
                                                        <div class="small text-success">
                                                            Risparmio: -€ {{ number_format($webDiscountTotal, 3, ',', '.') }}
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
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Rimuovi prodotto">
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
                        <h5 class="mb-3">Totali</h5>

                        <div class="mb-4">
                            @if($displayCouponCode)
                                <div class="border rounded-3 p-3 bg-light-subtle">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="small text-muted">Coupon applicato</div>
                                            <div class="fw-semibold">{{ $displayCouponCode }}</div>
                                        </div>

                                        <form method="POST" action="{{ route('storefront.cart.coupon.remove', $contextParams) }}" class="m-0">
                                            @csrf
                                            @method('DELETE')
                                            @if($agentContextId !== '')
                                                <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                                            @endif

                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                Rimuovi coupon
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

                                    <label for="coupon_code" class="form-label fw-semibold">Coupon</label>

                                    <div class="input-group">
                                        <input
                                            type="text"
                                            name="coupon_code"
                                            id="coupon_code"
                                            class="form-control @error('coupon_code') is-invalid @enderror"
                                            value="{{ old('coupon_code') }}"
                                            placeholder="Inserisci codice coupon"
                                            maxlength="80"
                                        >

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

                        @if($appliedPromotions->isNotEmpty() || $appliedCoupons->isNotEmpty())
                            <div class="mb-3">
                                @foreach($appliedPromotions as $promotion)
                                    <div class="small text-success d-flex justify-content-between gap-2">
                                        <span>{{ $promotion['name'] ?? $promotion['code'] ?? 'Promozione' }}</span>
                                        <span>-€ {{ number_format((float) ($promotion['discount_total'] ?? 0), 3, ',', '.') }}</span>
                                    </div>
                                @endforeach

                                @foreach($appliedCoupons as $coupon)
                                    <div class="small text-success d-flex justify-content-between gap-2">
                                        <span>Coupon {{ $coupon['code'] ?? '' }}</span>
                                        <span>-€ {{ number_format((float) ($coupon['discount_total'] ?? 0), 3, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotale</span>
                            <span>€ {{ number_format($subtotal, 3, ',', '.') }}</span>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Sconti web</span>
                            <span class="text-success">
                                -€ {{ number_format($discountTotal, 3, ',', '.') }}
                            </span>
                        </div>

                        <div class="d-flex justify-content-between mb-1">
                            <span>Spedizione</span>
                            <span>
                                @if(!$shippingAvailable)
                                    <span class="text-danger">Non disponibile</span>
                                @elseif($shippingIsFree)
                                    Gratis
                                @else
                                    € {{ number_format($shippingTotal, 3, ',', '.') }}
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
                                Hai risparmiato € {{ number_format($discountTotal, 3, ',', '.') }} su questo ordine.
                            </div>
                        @endif

                        <div class="d-flex justify-content-between fw-bold fs-5 mb-4">
                            <span>Totale</span>
                            <span>€ {{ number_format($grandTotal, 3, ',', '.') }}</span>
                        </div>

                        <div class="d-grid gap-2">
                            <a
                                href="{{ $shippingAvailable ? route('storefront.checkout.show', $contextParams) : '#' }}"
                                class="btn btn-primary {{ $shippingAvailable ? '' : 'disabled' }}"
                                {{ $shippingAvailable ? '' : 'aria-disabled=true tabindex=-1' }}
                            >
                                Vai al checkout
                            </a>

                            <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="btn btn-outline-secondary">
                                Continua acquisti
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