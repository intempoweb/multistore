@extends($storefrontLayout)

@section('title', 'Ordine confermato')

@section('content')
<div class="container py-5 checkout-success-page">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5 text-center">
                    <div class="text-success mb-3">
                        <i class="fa-solid fa-circle-check fa-3x"></i>
                    </div>

                    <h1 class="h3 mb-3">Ordine confermato</h1>

                    <p class="text-muted mb-4">
                        Grazie {{ $order->customer_name ?: 'cliente' }}, il tuo ordine è stato ricevuto correttamente.
                        Riceverai al più presto una mail di conferma dell'ordine
                        @if($order->customer_email)
                            all'indirizzo {{ $order->customer_email }}.
                        @else
                            all'indirizzo email indicato.
                        @endif
                    </p>

                    <div class="border rounded-3 p-3 p-md-4 text-start bg-light-subtle mb-4">
                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span class="text-muted">Numero ordine</span>
                            <strong>{{ $order->order_number }}</strong>
                        </div>

                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span class="text-muted">Stato pagamento</span>
                            <strong>{{ $order->payment_status === 'paid' ? 'Pagato' : ucfirst((string) $order->payment_status) }}</strong>
                        </div>

                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span class="text-muted">Metodo pagamento</span>
                            <strong>{{ strtoupper((string) ($order->payment_gateway ?: $order->payment_method_label ?: '')) }}</strong>
                        </div>

                        <div class="d-flex justify-content-between gap-3">
                            <span class="text-muted">Totale</span>
                            <strong>€ {{ number_format((float) $order->grand_total, 3, ',', '.') }}</strong>
                        </div>
                    </div>

                    @if($order->items->isNotEmpty())
                        <div class="text-start mb-4">
                            <h2 class="h6 mb-3">Prodotti ordinati</h2>

                            <div class="list-group list-group-flush border rounded-3">
                                @foreach($order->items as $item)
                                    <div class="list-group-item d-flex justify-content-between gap-3">
                                        <div>
                                            <div class="fw-semibold">{{ $item->product_name ?: $item->sku }}</div>
                                            <div class="small text-muted">SKU: {{ $item->sku }}</div>
                                        </div>

                                        <div class="text-end flex-shrink-0">
                                            <div class="small text-muted">Qtà {{ number_format((float) $item->quantity, 0, ',', '.') }}</div>
                                            <div class="fw-semibold">€ {{ number_format((float) $item->row_total, 3, ',', '.') }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="alert alert-info text-start mb-4">
                        Potrai controllare lo stato di avanzamento dell'ordine fino alla consegna dalla pagina dedicata.
                    </div>

                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2 mb-3">
                        @if(Route::has('storefront.orders.show'))
                            <a href="{{ route('storefront.orders.show', $order->order_number) }}" class="btn btn-success">
                                Segui stato ordine
                            </a>
                        @endif

                        @if(Route::has('storefront.account.orders.show'))
                            <a href="{{ route('storefront.account.orders.show', $order) }}" class="btn btn-outline-primary">
                                Vai al dettaglio ordine
                            </a>
                        @endif
                    </div>
                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                        <a href="{{ route('storefront.catalog.index') }}" class="btn btn-primary">
                            Continua acquisti
                        </a>

                        <a href="{{ route('storefront.home') }}" class="btn btn-outline-secondary">
                            Torna alla home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection