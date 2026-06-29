@extends($storefrontLayout)

@section('title', 'Ordine #'.$order->order_number)

@section('content')
@php
    $statusLabel = match ((string) $order->status) {
        'complete', 'completed' => 'Completato',
        'processing' => 'In lavorazione',
        'pending' => 'In attesa',
        'canceled', 'cancelled' => 'Annullato',
        'closed' => 'Chiuso',
        default => ucfirst((string) $order->status),
    };
    $erpStatusLabel = match ((string) $order->erp_export_status) {
        'exported' => 'Esportato ERP',
        'pending' => 'In attesa ERP',
        'failed' => 'Errore ERP',
        default => ucfirst((string) $order->erp_export_status),
    };
    $sendcloudStatus = trim((string) data_get($order->sendcloudMeta(), 'status_message', ''));
    $trackingNumber = $order->sendcloudTrackingNumber();
    $trackingUrl = $order->sendcloudTrackingUrl();
    $fmt = static fn ($value) => '€ ' . number_format((float) $value, 3, ',', '.');
@endphp

<div class="container py-4 py-lg-5 account-order-page">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <a href="{{ route('storefront.account.orders.index') }}" class="small text-decoration-none">
            <i class="fa-solid fa-arrow-left me-1"></i>
            I miei ordini
        </a>

        <a href="{{ route('storefront.catalog.index') }}" class="btn btn-outline-secondary">
            Continua acquisti
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="row g-4 align-items-end">
                <div class="col-12 col-lg">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge text-bg-dark">{{ $statusLabel }}</span>
                        <span class="badge {{ $order->erp_export_status === 'exported' ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $erpStatusLabel }}
                        </span>
                    </div>

                    <h1 class="display-6 fw-bold mb-2">Ordine #{{ $order->order_number }}</h1>
                    <div class="text-muted">
                        Inserito il {{ optional($order->placed_at)->format('d/m/Y H:i') }}
                        @if($order->erp_web_numreg)
                            · Numero ERP {{ $order->erp_web_numreg }}
                        @endif
                    </div>
                </div>

                <div class="col-12 col-lg-auto text-lg-end">
                    <div class="text-muted small">Totale ordine</div>
                    <div class="h3 fw-bold mb-0">{{ $fmt($order->grand_total) }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($productImagesDownload)
        <section class="alert alert-primary d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <div class="fw-semibold">Foto prodotti disponibili</div>
                <div>Il pacchetto pesa {{ $productImagesDownload['size_label'] }} ed è scaricabile da questa pagina.</div>
            </div>

            <a href="{{ $productImagesDownload['url'] }}" class="btn btn-dark">
                <i class="fa-solid fa-download me-2"></i>
                Scarica foto prodotti
            </a>
        </section>
    @endif

    @if($trackingNumber || $sendcloudStatus !== '')
        <section class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="text-uppercase small fw-semibold text-success mb-2">Spedizione</div>
                <h2 class="h5 mb-2">{{ $sendcloudStatus !== '' ? $sendcloudStatus : 'Tracking disponibile' }}</h2>
                @if($trackingNumber)
                    <div class="text-muted mb-3">Codice tracking: <strong class="text-body">{{ $trackingNumber }}</strong></div>
                @endif
                @if($trackingUrl)
                    <a href="{{ $trackingUrl }}" target="_blank" rel="noopener" class="btn btn-outline-dark">
                        Segui la spedizione
                    </a>
                @endif
            </div>
        </section>
    @endif

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <section class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">Articoli ordinati</h2>
                </div>

                <div class="card-body p-0">
                    @foreach($order->items as $item)
                        <div class="row g-3 align-items-center border-bottom p-3 p-lg-4">
                            <div class="col-auto">
                                <div class="ratio ratio-1x1 bg-light rounded" style="width: 76px;">
                                    @if($item->product_thumbnail_url)
                                        <img src="{{ $item->product_thumbnail_url }}" alt="" class="w-100 h-100 object-fit-contain p-2">
                                    @endif
                                </div>
                            </div>

                            <div class="col">
                                <div class="fw-semibold">{{ $item->product_name ?: $item->sku }}</div>
                                <div class="small text-muted">
                                    SKU {{ $item->sku }}
                                    · Quantità {{ number_format((float) $item->quantity, 0, ',', '.') }}
                                    · Prezzo {{ $fmt($item->price) }}
                                </div>
                            </div>

                            <div class="col-12 col-md-auto text-md-end fw-semibold">
                                {{ $fmt($item->row_total) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-4">
            <section class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">Totali</h2>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2">
                        <span>Subtotale</span>
                        <span>{{ $fmt($order->subtotal) }}</span>
                    </div>
                    @if((float) $order->discount_total > 0)
                        <div class="d-flex justify-content-between py-2">
                            <span>Sconti</span>
                            <span>− {{ $fmt($order->discount_total) }}</span>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between py-2">
                        <span>Spedizione</span>
                        <span>{{ $fmt($order->shipping_total) }}</span>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-3 mt-2 fw-bold">
                        <span>Totale</span>
                        <span>{{ $fmt($order->grand_total) }}</span>
                    </div>
                </div>
            </section>

            <section class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h2 class="h5 mb-0">Consegna</h2>
                </div>
                <div class="card-body">
                    <address class="mb-0 text-muted">
                        <strong class="text-body">{{ $order->shipping_company ?: trim($order->shipping_first_name.' '.$order->shipping_last_name) }}</strong><br>
                        {{ $order->shipping_address_line_1 }}<br>
                        {{ $order->shipping_postcode }} {{ $order->shipping_city }}
                        @if($order->shipping_province) ({{ $order->shipping_province }}) @endif<br>
                        @if($order->shipping_email){{ $order->shipping_email }}<br>@endif
                        @if($order->shipping_phone){{ $order->shipping_phone }}@endif
                    </address>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
