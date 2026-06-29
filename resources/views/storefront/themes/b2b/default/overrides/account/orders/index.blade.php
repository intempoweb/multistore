@extends($storefrontLayout)

@section('title', 'I miei ordini')

@section('content')
@php
    $statusLabel = static fn ($order) => match ((string) $order->status) {
        'complete', 'completed' => 'Completato',
        'processing' => 'In lavorazione',
        'pending' => 'In attesa',
        'canceled', 'cancelled' => 'Annullato',
        'closed' => 'Chiuso',
        default => ucfirst((string) $order->status),
    };
@endphp

<div class="container py-4 py-lg-5 account-orders-page">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <a href="{{ route('storefront.account.index') }}" class="small text-decoration-none">
                <i class="fa-solid fa-arrow-left me-1"></i>
                Area cliente
            </a>
            <h1 class="h2 fw-bold mt-3 mb-1">I miei ordini</h1>
            <p class="text-muted mb-0">Consulta gli ordini inviati, lo stato ERP e le spedizioni disponibili.</p>
        </div>

        <a href="{{ route('storefront.catalog.index') }}" class="btn btn-dark">
            Torna al catalogo
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @forelse($orders as $order)
                <a
                    href="{{ route('storefront.account.orders.show', $order) }}"
                    class="row g-3 align-items-center text-body text-decoration-none border-bottom p-3 p-lg-4"
                >
                    <div class="col-12 col-md-3">
                        <div class="small text-muted">Ordine</div>
                        <div class="fw-semibold">#{{ $order->order_number }}</div>
                        @if($order->erp_web_numreg)
                            <div class="small text-muted">ERP {{ $order->erp_web_numreg }}</div>
                        @endif
                    </div>

                    <div class="col-6 col-md-2">
                        <div class="small text-muted">Data</div>
                        <div>{{ optional($order->placed_at)->format('d/m/Y H:i') }}</div>
                    </div>

                    <div class="col-6 col-md-2">
                        <div class="small text-muted">Stato</div>
                        <div>{{ $statusLabel($order) }}</div>
                    </div>

                    <div class="col-6 col-md-2">
                        <div class="small text-muted">Articoli</div>
                        <div>{{ $order->items_count }}</div>
                    </div>

                    <div class="col-6 col-md-2 text-md-end">
                        <div class="small text-muted">Totale</div>
                        <div class="fw-semibold">€ {{ number_format((float) $order->grand_total, 3, ',', '.') }}</div>
                    </div>

                    <div class="col-12 col-md-1 text-md-end">
                        <i class="fa-solid fa-chevron-right text-muted"></i>
                    </div>
                </a>
            @empty
                <div class="p-4 p-lg-5 text-muted">
                    Non hai ancora ordini nello storico.
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-4">
        {{ $orders->links() }}
    </div>
</div>
@endsection
