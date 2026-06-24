@extends($storefrontLayout)

@section('title', 'I miei ordini')

@section('content')
@php
    $statusLabel = static fn ($order) => match ((string) $order->status) {
        'complete' => 'Spedizione preparata',
        'processing' => 'In lavorazione',
        'pending' => 'In attesa',
        'canceled' => 'Annullato',
        'closed' => 'Chiuso',
        default => ucfirst((string) $order->status),
    };
@endphp
<div class="container py-5 account-orders-page">
    <a href="{{ route('storefront.account.index') }}" class="small text-decoration-none">← Area personale</a>
    <header class="my-4 mb-5">
        <div class="text-uppercase text-danger small fw-semibold mb-2">Acquisti</div>
        <h1 class="display-6 fw-semibold mb-2">I miei ordini</h1>
        <p class="text-muted mb-0">Consulta i dettagli e segui le spedizioni disponibili.</p>
    </header>

    <div class="border-top">
        @forelse($orders as $order)
            <a href="{{ route('storefront.account.orders.show', $order) }}" class="row g-3 align-items-center text-body text-decoration-none border-bottom py-4">
                <div class="col-7 col-md-3">
                    <div class="small text-muted">Ordine</div>
                    <div class="fw-semibold">#{{ $order->order_number }}</div>
                </div>
                <div class="col-5 col-md-2">
                    <div class="small text-muted">Data</div>
                    <div>{{ optional($order->placed_at)->format('d/m/Y') }}</div>
                </div>
                <div class="col-7 col-md-3">
                    <div class="small text-muted">Stato</div>
                    <div>{{ $statusLabel($order) }}</div>
                    @if($order->sendcloudTrackingNumber())
                        <div class="small text-success">Tracking disponibile</div>
                    @endif
                </div>
                <div class="col-5 col-md-2">
                    <div class="small text-muted">Articoli</div>
                    <div>{{ $order->items_count }}</div>
                </div>
                <div class="col-12 col-md-2 text-md-end">
                    <strong>€ {{ number_format((float) $order->grand_total, 2, ',', '.') }}</strong>
                </div>
            </a>
        @empty
            <div class="py-5 text-muted">Non hai ancora effettuato ordini.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
</div>
@endsection
