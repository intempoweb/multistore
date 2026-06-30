@extends($storefrontLayout)

@section('title', 'Ordine #'.$order->order_number)

@section('content')
@php
    $statusLabel = match ((string) $order->status) {
        'complete' => 'Spedizione preparata',
        'processing' => 'In lavorazione',
        'pending' => 'In attesa',
        'canceled' => 'Annullato',
        'closed' => 'Chiuso',
        default => ucfirst((string) $order->status),
    };
    $sendcloudStatus = trim((string) data_get($order->sendcloudMeta(), 'status_message', ''));
    $trackingNumber = $order->sendcloudTrackingNumber();
    $trackingUrl = $order->sendcloudTrackingUrl();
@endphp
<div class="container py-5 account-order-page">
    <a href="{{ route('storefront.account.orders.index') }}" class="small text-decoration-none">← I miei ordini</a>

    <header class="d-flex flex-wrap justify-content-between align-items-end gap-3 my-4 mb-5">
        <div>
            <div class="text-uppercase text-danger small fw-semibold mb-2">{{ $statusLabel }}</div>
            <h1 class="display-6 fw-semibold mb-2">Ordine #{{ $order->order_number }}</h1>
            <div class="text-muted">{{ optional($order->placed_at)->format('d/m/Y H:i') }}</div>
        </div>
        <div class="h4 mb-0">€ {{ number_format((float) $order->grand_total, 2, ',', '.') }}</div>
    </header>

    @if($trackingNumber || $sendcloudStatus !== '')
        <section class="border p-4 mb-5">
            <div class="text-uppercase small fw-semibold text-danger mb-2">Spedizione</div>
            <h2 class="h4 mb-2">{{ $sendcloudStatus !== '' ? $sendcloudStatus : 'La spedizione è tracciabile' }}</h2>
            @if($trackingNumber)
                <div class="text-muted mb-3">Codice tracking: <strong class="text-body">{{ $trackingNumber }}</strong></div>
            @endif
            @if($trackingUrl)
                <a href="{{ $trackingUrl }}" target="_blank" rel="noopener" class="btn btn-dark">
                    Segui la spedizione
                </a>
            @endif
        </section>
    @endif

    <div class="row g-5">
        <div class="col-12 col-lg-8">
            <h2 class="h4 mb-3">Articoli</h2>
            <div class="border-top">
                @foreach($order->items as $item)
                    <div class="d-grid align-items-center border-bottom py-3" style="grid-template-columns: 72px minmax(0, 1fr) auto; gap: 16px;">
                        <div class="ratio ratio-1x1 bg-light">
                            @php($thumbnailUrl = media_url($item->product_thumbnail_url))
                            @if($thumbnailUrl)
                                <img src="{{ $thumbnailUrl }}" alt="" class="w-100 h-100 object-fit-contain">
                            @endif
                        </div>
                        <div>
                            <div class="fw-semibold">{{ $item->product_name ?: $item->sku }}</div>
                            <div class="small text-muted">SKU {{ $item->sku }} · Quantità {{ number_format((float) $item->quantity, 0, ',', '.') }}</div>
                        </div>
                        <strong>€ {{ number_format((float) $item->row_total, 2, ',', '.') }}</strong>
                    </div>
                @endforeach
            </div>

            <div class="ms-auto mt-4" style="max-width: 360px;">
                <div class="d-flex justify-content-between py-2"><span>Subtotale</span><span>€ {{ number_format((float) $order->subtotal, 2, ',', '.') }}</span></div>
                @if((float) $order->discount_total > 0)
                    <div class="d-flex justify-content-between py-2"><span>Sconti</span><span>− € {{ number_format((float) $order->discount_total, 2, ',', '.') }}</span></div>
                @endif
                <div class="d-flex justify-content-between py-2"><span>Spedizione</span><span>€ {{ number_format((float) $order->shipping_total, 2, ',', '.') }}</span></div>
                <div class="d-flex justify-content-between border-top pt-3 mt-2 fw-semibold"><span>Totale</span><span>€ {{ number_format((float) $order->grand_total, 2, ',', '.') }}</span></div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <h2 class="h4 mb-3">Consegna</h2>
            <address class="border-top pt-3 text-muted">
                <strong class="text-body">{{ trim($order->shipping_first_name.' '.$order->shipping_last_name) }}</strong><br>
                {{ $order->shipping_address_line_1 }}<br>
                {{ $order->shipping_postcode }} {{ $order->shipping_city }}
                @if($order->shipping_province) ({{ $order->shipping_province }}) @endif<br>
                {{ $order->shipping_country_code }}<br>
                @if($order->shipping_email){{ $order->shipping_email }}<br>@endif
                @if($order->shipping_phone){{ $order->shipping_phone }}@endif
            </address>
        </div>
    </div>
</div>
@endsection
