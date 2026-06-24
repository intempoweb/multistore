@extends($storefrontLayout)

@section('title', 'La mia area personale')

@section('content')
@php
    $displayName = trim((string) ($customer->nomeconnweb ?: $customer->ragsoanag_cg16 ?: ''));
    $statusLabel = static fn ($order) => match ((string) $order->status) {
        'complete' => 'Spedizione preparata',
        'processing' => 'In lavorazione',
        'pending' => 'In attesa',
        'canceled' => 'Annullato',
        'closed' => 'Chiuso',
        default => ucfirst((string) $order->status),
    };
@endphp
<div class="container py-5 account-page">
    @includeIf('storefront.base.partials.alerts')

    <header class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-5">
        <div>
            <div class="text-uppercase text-danger small fw-semibold mb-2">Area personale</div>
            <h1 class="display-6 fw-semibold mb-2">Ciao{{ $displayName !== '' ? ', '.$displayName : '' }}</h1>
            <p class="text-muted mb-0">Controlla i tuoi ordini, le spedizioni e i prodotti salvati.</p>
        </div>
        <form method="POST" action="{{ route('storefront.logout') }}">
            @csrf
            <button class="btn btn-outline-dark btn-sm">Esci</button>
        </form>
    </header>

    <div class="row g-3 mb-5">
        <div class="col-4">
            <div class="border-top pt-3">
                <div class="h3 mb-1">{{ $orderStats['total'] ?? 0 }}</div>
                <div class="small text-muted">Ordini</div>
            </div>
        </div>
        <div class="col-4">
            <div class="border-top pt-3">
                <div class="h3 mb-1">{{ $orderStats['processing'] ?? 0 }}</div>
                <div class="small text-muted">In lavorazione</div>
            </div>
        </div>
        <div class="col-4">
            <div class="border-top pt-3">
                <div class="h3 mb-1">{{ $orderStats['shipped'] ?? 0 }}</div>
                <div class="small text-muted">Con tracking</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h4 mb-0">Ordini recenti</h2>
                <a href="{{ route('storefront.account.orders.index') }}" class="small text-decoration-none">Vedi tutti</a>
            </div>

            <div class="border-top">
                @forelse($recentOrders as $order)
                    <a href="{{ route('storefront.account.orders.show', $order) }}" class="d-grid text-body text-decoration-none border-bottom py-3" style="grid-template-columns: 1fr auto; gap: 16px;">
                        <div>
                            <div class="fw-semibold">Ordine #{{ $order->order_number }}</div>
                            <div class="small text-muted">
                                {{ optional($order->placed_at)->format('d/m/Y') }}
                                · {{ $order->items_count }} {{ $order->items_count === 1 ? 'articolo' : 'articoli' }}
                                · {{ $statusLabel($order) }}
                            </div>
                        </div>
                        <strong>€ {{ number_format((float) $order->grand_total, 2, ',', '.') }}</strong>
                    </a>
                @empty
                    <div class="py-5 text-muted">Non hai ancora effettuato ordini.</div>
                @endforelse
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <h2 class="h4 mb-3">Il tuo account</h2>
            <div class="border-top">
                <a href="{{ route('storefront.wishlist.index') }}" class="d-flex justify-content-between align-items-center text-body text-decoration-none border-bottom py-3">
                    <span>Preferiti</span><i class="fa-solid fa-arrow-right small"></i>
                </a>
                <a href="{{ route('storefront.catalog.index') }}" class="d-flex justify-content-between align-items-center text-body text-decoration-none border-bottom py-3">
                    <span>Continua gli acquisti</span><i class="fa-solid fa-arrow-right small"></i>
                </a>
                <div class="border-bottom py-3">
                    <div class="small text-muted">Email</div>
                    <div>{{ $customer->indemail_cg16 }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
