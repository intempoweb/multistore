@extends('layouts.admin')

@section('title', 'Ordini')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Ordini</h1>
            <p class="text-muted mb-0">Gestione ordini B2B / B2C</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.orders.index') }}">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Ricerca</label>
                        <input
                            type="text"
                            name="q"
                            value="{{ $filters['q'] ?? '' }}"
                            class="form-control"
                            placeholder="Numero ordine, cliente, email, tracking..."
                        >
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="form-label">Canale</label>
                        <select name="channel" class="form-select">
                            <option value="">Tutti</option>
                            <option value="b2b" @selected(($filters['channel'] ?? '') === 'b2b')>B2B</option>
                            <option value="b2c" @selected(($filters['channel'] ?? '') === 'b2c')>B2C</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="form-label">Ordine</label>
                        <select name="status" class="form-select">
                            <option value="">Tutti</option>
                            @foreach(\App\Models\Order::orderStatusLabels() as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="form-label">Pagamento</label>
                        <select name="payment_status" class="form-select">
                            <option value="">Tutti</option>
                            @foreach(\App\Models\Order::paymentStatusLabels() as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['payment_status'] ?? '') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="form-label">Status</label>
                        <select name="fulfillment_status" class="form-select">
                            <option value="">Tutti</option>
                            @foreach(\App\Models\Order::fulfillmentStatusLabels() as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['fulfillment_status'] ?? '') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="form-label">Export ERP</label>
                        <select name="erp_export_status" class="form-select">
                            <option value="">Tutti</option>
                            <option value="pending" @selected(($filters['erp_export_status'] ?? '') === 'pending')>Da esportare</option>
                            <option value="exported" @selected(($filters['erp_export_status'] ?? '') === 'exported')>Esportato</option>
                            <option value="failed" @selected(($filters['erp_export_status'] ?? '') === 'failed')>Errore</option>
                            <option value="skipped" @selected(($filters['erp_export_status'] ?? '') === 'skipped')>Non richiesto</option>
                        </select>
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filtra</button>
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Ordine</th>
                        <th>Store</th>
                        <th>Cliente</th>
                        <th>Pagamento</th>
                        <th>Spedizione</th>
                        <th>Totale</th>
                        <th>Stato ordine</th>
                        <th>Stato spedizione</th>
                        <th>Export ERP</th>
                        <th>Data</th>
                        <th class="text-end">Azioni</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($orders as $order)
                        @php
                            $meta = $order->meta ?? [];

                            if (is_string($meta)) {
                                $meta = json_decode($meta, true) ?: [];
                            }

                            $meta = is_array($meta) ? $meta : [];
                            $sendcloudMeta = data_get($meta, 'sendcloud', []);
                            $sendcloudMeta = is_array($sendcloudMeta) ? $sendcloudMeta : [];

                            $sendcloudTracking = $order->shipping_tracking_number
                                ?: data_get($sendcloudMeta, 'tracking_number')
                                ?: data_get($sendcloudMeta, 'barcode');

                            $sendcloudBarcode = data_get($sendcloudMeta, 'barcode') ?: $sendcloudTracking;
                            $sendcloudLabelUrl = $order->shipping_label_url ?: data_get($sendcloudMeta, 'label_url');
                            $sendcloudIncomingOrderId = data_get($sendcloudMeta, 'incoming_order_id');
                            $sendcloudError = data_get($sendcloudMeta, 'error');
                            $sendcloudSkippedReason = data_get($sendcloudMeta, 'skipped_reason');
                            $sendcloudPendingWebhook = (bool) data_get($sendcloudMeta, 'pending_webhook', false);

                            $canConfirmStock = $order->isB2c()
                                && $order->canCapturePayment()
                                && !$order->isProcessing()
                                && !$order->isClosed()
                                && !$order->isCanceled();

                            $canCreateSendcloud = $order->requiresSendcloudShipment();

                            $canRefundAndClose = $order->isB2c()
                                && $order->canRefundPayment();

                            $canCancel = !$order->isPaid()
                                && !$order->isClosed()
                                && !$order->isCanceled();

                            $canExportErp = $order->canExportToErp()
                                && Route::has('admin.orders.export-erp');
                        @endphp

                        <tr>
                            <td style="min-width: 180px;">
                                <div class="fw-semibold">{{ $order->order_number }}</div>
                                <div class="small text-muted">ID #{{ $order->id }}</div>

                                <div class="mt-1">
                                    @if($order->isB2c())
                                        <span class="badge bg-primary">B2C</span>
                                    @else
                                        <span class="badge bg-dark">B2B</span>
                                    @endif
                                </div>
                            </td>

                            <td>
                                <div class="fw-semibold">{{ $order->store?->name ?? '-' }}</div>
                                <small class="text-muted">Store #{{ $order->store_id }}</small>
                            </td>

                            <td style="min-width: 220px;">
                                <div class="fw-semibold">{{ $order->customer_name ?: 'Cliente guest' }}</div>
                                <small class="text-muted">{{ $order->customer_email ?: '-' }}</small>
                            </td>

                            <td style="min-width: 190px;">
                                <div class="mb-2">
                                    <span class="badge {{ $order->paymentStatusBadgeClass() }}">
                                        {{ $order->paymentStatusLabel() }}
                                    </span>
                                </div>

                                <div class="small text-muted">{{ strtoupper($order->payment_gateway ?? '-') }}</div>

                                @if($order->payment_transaction_id)
                                    <div class="small text-muted text-truncate" style="max-width: 180px;" title="{{ $order->payment_transaction_id }}">
                                        {{ $order->payment_transaction_id }}
                                    </div>
                                @endif

                                @if($order->paid_at)
                                    <div class="small text-success">
                                        Incassato {{ $order->paid_at->format('d/m/Y H:i') }}
                                    </div>
                                @elseif($order->canCapturePayment())
                                    <div class="small text-warning">
                                        Autorizzato, da incassare
                                    </div>
                                @endif

                                @if($order->hasRefundData())
                                    <div class="small text-danger mt-1">
                                        Rimborsato
                                        @if($order->refundedAt())
                                            {{ $order->refundedAt()->format('d/m/Y H:i') }}
                                        @endif
                                    </div>

                                    @if($order->refundAmount() !== null)
                                        <div class="small text-danger">
                                            € {{ number_format($order->refundAmount(), 2, ',', '.') }}
                                        </div>
                                    @endif

                                    @if($order->refundId())
                                        <div class="small text-muted text-truncate" style="max-width: 180px;" title="{{ $order->refundId() }}">
                                            Refund ID: {{ $order->refundId() }}
                                        </div>
                                    @endif
                                @endif
                            </td>

                            <td style="min-width: 230px;">
                                @if($order->shipping_gateway === 'sendcloud')
                                    <div class="fw-semibold">Sendcloud</div>

                                    @if($sendcloudTracking)
                                        <div class="small text-success text-truncate" style="max-width: 210px;" title="{{ $sendcloudTracking }}">
                                            Tracking: {{ $sendcloudTracking }}
                                        </div>
                                    @elseif($canCreateSendcloud)
                                        <div class="small text-warning">Da sincronizzare</div>
                                    @elseif($order->isB2c() && !$order->isProcessing())
                                        <div class="small text-muted">Dopo conferma giacenza</div>
                                    @else
                                        <div class="small text-muted">In attesa</div>
                                    @endif

                                    @if($sendcloudBarcode && $sendcloudBarcode !== $sendcloudTracking)
                                        <div class="small text-muted text-truncate" style="max-width: 210px;" title="{{ $sendcloudBarcode }}">
                                            Barcode: {{ $sendcloudBarcode }}
                                        </div>
                                    @endif

                                    @if($sendcloudIncomingOrderId)
                                        <div class="small text-muted text-truncate" style="max-width: 210px;" title="{{ $sendcloudIncomingOrderId }}">
                                            Ordine SC: {{ $sendcloudIncomingOrderId }}
                                        </div>
                                    @endif

                                    @if($sendcloudLabelUrl)
                                        <div class="small">
                                            @if(Route::has('admin.orders.sendcloud.label'))
                                                <a href="{{ route('admin.orders.sendcloud.label', $order) }}" target="_blank" rel="noopener">
                                                    Etichetta
                                                </a>
                                            @else
                                                <a href="{{ $sendcloudLabelUrl }}" target="_blank" rel="noopener">
                                                    Etichetta
                                                </a>
                                            @endif
                                        </div>
                                    @elseif($sendcloudPendingWebhook)
                                        <div class="small text-info">In attesa webhook</div>
                                    @endif

                                    @if($sendcloudError)
                                        <div class="small text-danger text-truncate" style="max-width: 210px;" title="{{ $sendcloudError }}">
                                            {{ $sendcloudError }}
                                        </div>
                                    @elseif($sendcloudSkippedReason)
                                        <div class="small text-muted text-truncate" style="max-width: 210px;" title="{{ $sendcloudSkippedReason }}">
                                            {{ $sendcloudSkippedReason }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    € {{ number_format((float) $order->grand_total, 2, ',', '.') }}
                                </div>
                            </td>

                            <td style="min-width: 140px;">
                                <span class="badge {{ $order->orderStatusBadgeClass() }}">
                                    {{ $order->orderStatusLabel() }}
                                </span>
                            </td>

                            <td style="min-width: 160px;">
                                <span class="badge {{ $order->fulfillmentStatusBadgeClass() }}">
                                    {{ $order->fulfillmentStatusLabel() }}
                                </span>
                            </td>

                            <td style="min-width: 180px;">
                                @if($order->isExportedToErp())
                                    <span class="badge bg-success">Esportato</span>
                                @elseif($order->isFailedErpExport())
                                    <span class="badge bg-danger">Errore</span>
                                @elseif($order->isPendingErpExport())
                                    <span class="badge bg-warning text-dark">Da esportare</span>
                                @elseif($order->isSkippedErpExport())
                                    <span class="badge bg-secondary">Non richiesto</span>
                                @else
                                    <span class="badge bg-light text-dark">{{ strtoupper((string) ($order->erp_export_status ?? '-')) }}</span>
                                @endif

                                <div class="small text-muted mt-1">
                                    Documento: {{ $order->erpDocumentForDisplay() }}
                                </div>

                                @if($order->erp_web_numreg)
                                    <div class="small text-muted">
                                        NUMREG: {{ $order->erp_web_numreg }}
                                    </div>
                                @endif

                                @if($order->erp_export_error)
                                    <div class="small text-danger text-truncate" style="max-width: 180px;" title="{{ $order->erp_export_error }}">
                                        {{ $order->erp_export_error }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                <small>{{ optional($order->created_at)->format('d/m/Y H:i') }}</small>
                            </td>

                            <td class="text-end" style="min-width: 300px;">
                                <div class="d-flex flex-wrap justify-content-end gap-2">
                                    @if($canConfirmStock && Route::has('admin.orders.confirm-stock'))
                                        <form method="POST" action="{{ route('admin.orders.confirm-stock', $order) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                                Conferma giacenza
                                            </button>
                                        </form>
                                    @endif

                                    @if($canCreateSendcloud && Route::has('admin.orders.sendcloud.shipment.create'))
                                        <form method="POST" action="{{ route('admin.orders.sendcloud.shipment.create', $order) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                Sendcloud
                                            </button>
                                        </form>
                                    @endif

                                    @if($canRefundAndClose && Route::has('admin.orders.refund-payment'))
                                        <form
                                            method="POST"
                                            action="{{ route('admin.orders.refund-payment', $order) }}"
                                            onsubmit="return confirm('Rimborsare il pagamento su Stripe/PayPal e chiudere l’ordine?');"
                                        >
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                Rimborsa e chiudi
                                            </button>
                                        </form>
                                    @endif

                                    @if($canCancel && Route::has('admin.orders.cancel'))
                                        <form
                                            method="POST"
                                            action="{{ route('admin.orders.cancel', $order) }}"
                                            onsubmit="return confirm('Annullare questo ordine? Se il pagamento è solo autorizzato verrà annullata anche l’autorizzazione.');"
                                        >
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                Annulla
                                            </button>
                                        </form>
                                    @endif

                                    @if($canExportErp)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.orders.export-erp', $order) }}"
                                            onsubmit="return confirm('Esportare questo ordine verso ERP?');"
                                        >
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-dark">
                                                Export ERP
                                            </button>
                                        </form>
                                    @endif

                                    <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-primary">
                                        Apri
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                Nessun ordine trovato.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(method_exists($orders, 'links'))
        <div class="mt-4">
            {{ $orders->links() }}
        </div>
    @endif
</div>
@endsection