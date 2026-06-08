@extends('layouts.admin')

@section('title', 'Ordine ' . $order->order_number)
@section('breadcrumb', 'Ordine ' . $order->order_number)

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-start gap-3 mb-4">
        <div>
            <div class="text-muted small mb-1">Dettaglio ordine</div>

            <h1 class="h3 mb-1">
                Ordine {{ $order->order_number }}
            </h1>

            <div class="text-muted small">
                Creato il {{ optional($order->created_at)->format('d/m/Y H:i') }}
                <span class="mx-1">•</span>
                ID #{{ $order->id }}
                <span class="mx-1">•</span>
                {{ strtoupper($order->channel ?? '-') }}
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i>
                Torna agli ordini
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h2 class="h5 mb-1">Prodotti</h2>
                    <div class="text-muted small">Righe ordine</div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 72px;">Img</th>
                                    <th>Prodotto</th>
                                    <th>SKU</th>
                                    <th class="text-center">Q.tà</th>
                                    <th class="text-end">Prezzo</th>
                                    <th class="text-end">Totale</th>
                                    <th class="text-center">ERP</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse($order->items as $item)
                                    @php
                                        $isCouponDiscountItem = str_starts_with(strtoupper(trim((string) $item->sku)), 'MTBUONO');

                                        $adminBaseUrl = rtrim((string) config('app.admin_url', config('app.url')), '/');
                                        $thumbnailUrl = trim((string) ($item->product_thumbnail_url ?? ''));

                                        if ($thumbnailUrl !== '' && !str_starts_with($thumbnailUrl, 'http://') && !str_starts_with($thumbnailUrl, 'https://')) {
                                            $thumbnailUrl = $adminBaseUrl . '/' . ltrim($thumbnailUrl, '/');
                                        }

                                        if ($thumbnailUrl !== '' && str_contains($thumbnailUrl, '://')) {
                                            $thumbnailParts = parse_url($thumbnailUrl);
                                            $thumbnailPath = $thumbnailParts['path'] ?? '';

                                            if ($thumbnailPath !== '') {
                                                $thumbnailUrl = $adminBaseUrl . '/' . ltrim($thumbnailPath, '/');
                                            }
                                        }
                                    @endphp
                                    <tr @class(['table-success' => $isCouponDiscountItem])>
                                        <td>
                                            @if($isCouponDiscountItem)
                                                <div class="rounded border bg-success-subtle d-flex align-items-center justify-content-center text-success" style="width: 56px; height: 56px;">
                                                    <i class="fa-solid fa-ticket"></i>
                                                </div>
                                            @elseif($thumbnailUrl !== '')
                                                <img
                                                    src="{{ $thumbnailUrl }}"
                                                    alt="{{ $item->product_name ?: $item->sku }}"
                                                    class="rounded border"
                                                    style="width: 56px; height: 56px; object-fit: cover;"
                                                >
                                            @else
                                                <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted" style="width: 56px; height: 56px;">
                                                    <i class="fa-regular fa-image"></i>
                                                </div>
                                            @endif
                                        </td>

                                        <td style="min-width: 220px;">
                                            <div class="fw-semibold">
                                                {{ $item->product_name ?: '-' }}
                                                @if($isCouponDiscountItem)
                                                    <span class="badge bg-success-subtle text-success ms-2">Buono</span>
                                                @endif
                                            </div>

                                            @if($item->product_description)
                                                <div class="small text-muted">
                                                    {{ \Illuminate\Support\Str::limit(strip_tags($item->product_description), 90) }}
                                                </div>
                                            @endif

                                            @if(!empty($item->variant_attributes))
                                                <div class="small text-muted mt-1">
                                                    @foreach($item->variant_attributes as $key => $value)
                                                        <span>{{ ucfirst($key) }}: {{ $value }}</span>@if(!$loop->last)<span class="mx-1">•</span>@endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>

                                        <td><code>{{ $item->sku }}</code></td>

                                        <td class="text-center">
                                            {{ $isCouponDiscountItem ? number_format((float) $item->quantity, 0, ',', '.') : number_format((float) $item->quantity, 0, ',', '.') }}
                                        </td>

                                        <td class="text-end">
                                            <span @class(['text-success fw-semibold' => $isCouponDiscountItem])>
                                                € {{ number_format((float) ($item->price ?? $item->price_gross ?? $item->price_net ?? 0), 2, ',', '.') }}
                                            </span>
                                        </td>

                                        <td class="text-end fw-semibold">
                                            <span @class(['text-success' => $isCouponDiscountItem])>
                                                € {{ number_format((float) ($item->row_total ?? 0), 2, ',', '.') }}
                                            </span>
                                        </td>

                                        <td class="text-center">
                                            @if($item->erp_web_row_id || $item->erp_web_numreg)
                                                <span class="badge bg-success">Export</span>
                                                <div class="small text-muted mt-1">Riga {{ $item->erp_web_row_number ?: '-' }}</div>
                                            @elseif($order->requiresErpExport())
                                                <span class="badge bg-warning text-dark">Da export</span>
                                                <div class="small text-muted mt-1">Riga {{ $item->erp_web_row_number ?: '-' }}</div>
                                            @else
                                                <span class="badge bg-secondary">Skip</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            Nessun prodotto nell'ordine.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h2 class="h5 mb-1">Dati cliente</h2>
                    <div class="text-muted small">Anagrafica associata all'ordine</div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Nome cliente</div>
                            <div class="fw-semibold">{{ $order->customer_name ?: 'Cliente guest' }}</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Email cliente</div>
                            <div>{{ $order->customer_email ?: '-' }}</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Telefono cliente</div>
                            <div>{{ $order->customer_phone ?: '-' }}</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="text-muted small">P. IVA / Codice fiscale</div>
                            <div>{{ $order->customer_vat_number ?: $order->customer_tax_code ?: '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h2 class="h5 mb-1">Fatturazione</h2>
                    <div class="text-muted small">Dati fiscali dell'ordine</div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Intestatario</div>
                            <div class="fw-semibold">
                                {{ trim(($order->billing_first_name ?? '') . ' ' . ($order->billing_last_name ?? '')) ?: '-' }}
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Ragione sociale</div>
                            <div class="fw-semibold">{{ $order->billing_company ?: $order->customer_company_name ?: '-' }}</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Email fatturazione</div>
                            <div>{{ $order->billing_email ?: '-' }}</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Telefono fatturazione</div>
                            <div>{{ $order->billing_phone ?: '-' }}</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Paese</div>
                            <div>{{ $order->billing_country_code ?: '-' }}</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="text-muted small">P. IVA</div>
                            <div>{{ $order->customer_vat_number ?: '-' }}</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Codice fiscale</div>
                            <div>{{ $order->customer_tax_code ?: '-' }}</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="text-muted small">PEC</div>
                            <div>{{ $order->b2cInvoicePecForDisplay() }}</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Codice SDI</div>
                            <div>{{ $order->b2cInvoiceSdiForDisplay() }}</div>
                        </div>

                        <div class="col-12">
                            <div class="text-muted small">Indirizzo fatturazione</div>
                            <div>
                                {{ $order->billing_address_line_1 ?: '-' }}<br>
                                {{ trim(($order->billing_postcode ?? '') . ' ' . ($order->billing_city ?? '')) }}
                                @if(!empty($order->billing_province))
                                    ({{ $order->billing_province }})
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h2 class="h5 mb-1">Spedizione</h2>
                    <div class="text-muted small">Indirizzo e contatti di consegna</div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Destinatario</div>
                            <div class="fw-semibold">
                                {{ $order->shipping_contact_name ?: trim(($order->shipping_first_name ?? '') . ' ' . ($order->shipping_last_name ?? '')) ?: '-' }}
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Email</div>
                            <div>{{ $order->shipping_email ?: '-' }}</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Telefono</div>
                            <div>{{ $order->shipping_phone ?: '-' }}</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="text-muted small">Paese</div>
                            <div>{{ $order->shipping_country_code ?: '-' }}</div>
                        </div>

                        <div class="col-12">
                            <div class="text-muted small">Indirizzo</div>
                            <div>
                                {{ $order->shipping_address_line_1 ?: '-' }}<br>
                                {{ trim(($order->shipping_postcode ?? '') . ' ' . ($order->shipping_city ?? '')) }}
                                @if(!empty($order->shipping_province))
                                    ({{ $order->shipping_province }})
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($order->customerNotesForDisplay())
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h2 class="h5 mb-1">Note ordine</h2>
                    </div>

                    <div class="card-body">
                        <div style="white-space: pre-line;">{{ $order->customerNotesForDisplay() }}</div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h2 class="h5 mb-1">Stato ordine</h2>
                    <div class="text-muted small">Gestione operativa BO</div>
                </div>

                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Stato ordine</div>
                        <span class="badge {{ $order->orderStatusBadgeClass() }}">
                            {{ $order->orderStatusLabel() }}
                        </span>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">Stato spedizione</div>
                        <span class="badge {{ $order->fulfillmentStatusBadgeClass() }}">
                            {{ $order->fulfillmentStatusLabel() }}
                        </span>
                    </div>

                    <div class="d-grid gap-2">
                        @if($order->canConfirmStock() && Route::has('admin.orders.confirm-stock'))
                            <form method="POST" action="{{ route('admin.orders.confirm-stock', $order) }}">
                                @csrf
                                <button type="submit" class="btn btn-warning w-100">
                                    Conferma giacenza e incassa
                                </button>
                            </form>
                        @endif

                        @if($order->canRefundAndClose() && Route::has('admin.orders.refund-payment'))
                            <form method="POST" action="{{ route('admin.orders.refund-payment', $order) }}" onsubmit="return confirm('Rimborsare il pagamento su Stripe/PayPal e chiudere l’ordine?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger w-100">
                                    Rimborsa e chiudi
                                </button>
                            </form>
                        @endif

                        @if($order->canMarkCompletedFromBo() && Route::has('admin.orders.mark-completed'))
                            <form method="POST" action="{{ route('admin.orders.mark-completed', $order) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-success w-100">
                                    Segna completato
                                </button>
                            </form>
                        @endif

                        @if($order->canCancelFromBo() && Route::has('admin.orders.cancel'))
                            <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" onsubmit="return confirm('Annullare questo ordine? Se è già pagato verrà rimborsato; se è solo autorizzato verrà annullata l’autorizzazione.');">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary w-100">
                                    Annulla ordine
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h2 class="h5 mb-1">ERP</h2>
                    <div class="text-muted small">Export ordine verso ERP WEB</div>
                </div>

                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Richiede export ERP</div>
                        @if($order->requiresErpExport())
                            <span class="badge bg-primary">
                                Sì - {{ $order->erpExportReason() === 'b2b_order' ? 'B2B' : 'B2C con fattura' }}
                            </span>
                        @else
                            <span class="badge bg-secondary">No</span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">Stato export</div>
                        @if($order->isExportedToErp())
                            <span class="badge bg-success">Esportato</span>
                        @elseif($order->isFailedErpExport())
                            <span class="badge bg-danger">Errore</span>
                        @elseif($order->isSkippedErpExport())
                            <span class="badge bg-secondary">Saltato</span>
                        @elseif($order->isPendingErpExport())
                            <span class="badge bg-warning text-dark">In attesa</span>
                        @else
                            <span class="badge bg-secondary">{{ strtoupper((string) $order->erp_export_status) }}</span>
                        @endif
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">NUMREG WEB</div>
                        <code>{{ $order->erp_web_numreg ?: '-' }}</code>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">Documento ERP</div>
                        <code>{{ $order->erpDocumentForDisplay() }}</code>
                    </div>

                    @if($order->erp_exported_at)
                        <div class="mb-3">
                            <div class="text-muted small">Esportato il</div>
                            <div class="text-success">{{ $order->erp_exported_at->format('d/m/Y H:i') }}</div>
                        </div>
                    @endif

                    @if($order->erp_export_error)
                        <div class="alert alert-danger small" style="white-space: pre-line;">{{ $order->erp_export_error }}</div>
                    @endif

                    <div class="d-grid gap-2">
                        @if($order->canExportToErp() && Route::has('admin.orders.export-erp'))
                            <form method="POST" action="{{ route('admin.orders.export-erp', $order) }}" onsubmit="return confirm('Esportare o riprovare l’export ERP di questo ordine?');">
                                @csrf
                                <button type="submit" class="btn btn-primary w-100">
                                    {{ $order->isFailedErpExport() ? 'Riprova export ERP' : 'Esporta verso ERP' }}
                                </button>
                            </form>
                        @elseif($order->isExportedToErp())
                            <div class="alert alert-success small mb-0">
                                Ordine già esportato verso ERP WEB.
                            </div>
                        @elseif(!$order->requiresErpExport())
                            <div class="alert alert-secondary small mb-0">
                                Export ERP non richiesto per questo ordine.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h2 class="h5 mb-1">Riepilogo economico</h2>
                </div>

                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotale</span>
                        <strong>€ {{ number_format((float) $order->subtotal, 2, ',', '.') }}</strong>
                    </div>

                    @if((float) ($order->discount_total ?? 0) > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span>Sconto promozioni</span>
                            <strong>- € {{ number_format((float) $order->discount_total, 2, ',', '.') }}</strong>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between mb-2">
                        <span>Spedizione</span>
                        <strong>€ {{ number_format((float) $order->shipping_total, 2, ',', '.') }}</strong>
                    </div>

                    @if((float) ($order->tax_total ?? 0) > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span>IVA</span>
                            <strong>€ {{ number_format((float) $order->tax_total, 2, ',', '.') }}</strong>
                        </div>
                    @endif

                    <hr>

                    <div class="d-flex justify-content-between fs-5">
                        <span class="fw-bold">Totale</span>
                        <strong>€ {{ number_format((float) $order->grand_total, 2, ',', '.') }}</strong>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0">
                    <h2 class="h5 mb-1">Pagamento</h2>
                </div>

                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Gateway</div>
                        <div>{{ strtoupper($order->payment_gateway ?? '-') }}</div>
                    </div>

                    <div class="mb-3">
                        <div class="text-muted small">Stato pagamento</div>
                        <span class="badge {{ $order->paymentStatusBadgeClass() }}">
                            {{ $order->paymentStatusLabel() }}
                        </span>
                    </div>

                    @if($order->paid_at)
                        <div class="mb-3">
                            <div class="text-muted small">Incassato il</div>
                            <div class="text-success">{{ $order->paid_at->format('d/m/Y H:i') }}</div>
                        </div>
                    @elseif($order->canCapturePayment())
                        <div class="alert alert-warning small">
                            Pagamento autorizzato, da incassare dopo conferma giacenza.
                        </div>
                    @endif

                    <div class="mb-3">
                        <div class="text-muted small">Transaction ID</div>
                        <code style="word-break: break-all;">{{ $order->payment_transaction_id ?: '-' }}</code>
                    </div>

                    @if($order->hasRefundData())
                        <hr>

                        <div class="mb-3">
                            <div class="text-muted small">Rimborsato il</div>
                            <div class="text-danger">
                                {{ $order->refundedAt() ? $order->refundedAt()->format('d/m/Y H:i') : '-' }}
                            </div>
                        </div>

                        @if($order->refundAmount() !== null)
                            <div class="mb-3">
                                <div class="text-muted small">Importo rimborso</div>
                                <div class="fw-semibold text-danger">
                                    € {{ number_format($order->refundAmount(), 2, ',', '.') }}
                                </div>
                            </div>
                        @endif

                        <div class="mb-0">
                            <div class="text-muted small">Refund ID</div>
                            <code style="word-break: break-all;">{{ $order->refundId() ?: '-' }}</code>
                        </div>
                    @endif
                </div>
            </div>

            @if($order->isB2c())
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h2 class="h5 mb-1">Sendcloud</h2>
                        <div class="text-muted small">Spedizione B2C</div>
                    </div>

                    <div class="card-body">
                        <div class="mb-3">
                            <div class="text-muted small">Gateway</div>
                            <div>{{ strtoupper($order->shipping_gateway ?? '-') }}</div>
                        </div>

                        @if($order->sendcloudIncomingOrderId())
                            <div class="mb-3">
                                <div class="text-muted small">Incoming order ID</div>
                                <code style="word-break: break-all;">{{ $order->sendcloudIncomingOrderId() }}</code>
                            </div>
                        @endif

                        @if($order->sendcloudParcelId())
                            <div class="mb-3">
                                <div class="text-muted small">Parcel ID</div>
                                <code style="word-break: break-all;">{{ $order->sendcloudParcelId() }}</code>
                            </div>
                        @endif

                        @if($order->sendcloudBarcode())
                            <div class="mb-3">
                                <div class="text-muted small">Barcode</div>
                                <code style="word-break: break-all;">{{ $order->sendcloudBarcode() }}</code>
                            </div>
                        @endif

                        @if($order->sendcloudTrackingNumber())
                            <div class="mb-3">
                                <div class="text-muted small">Tracking</div>
                                <code style="word-break: break-all;">{{ $order->sendcloudTrackingNumber() }}</code>
                            </div>
                        @endif

                        @if($order->sendcloudTrackingUrl())
                            <div class="mb-3">
                                <div class="text-muted small">Tracking URL</div>
                                <a href="{{ $order->sendcloudTrackingUrl() }}" target="_blank" rel="noopener">
                                    Apri tracking
                                </a>
                            </div>
                        @endif

                        @if($order->sendcloudLabelUrl() && Route::has('admin.orders.sendcloud.label'))
                            <div class="mb-3">
                                <div class="text-muted small">Etichetta</div>
                                <a href="{{ route('admin.orders.sendcloud.label', $order) }}" target="_blank" rel="noopener">
                                    Apri etichetta
                                </a>
                            </div>
                        @elseif($order->sendcloudLabelUrl())
                            <div class="mb-3">
                                <div class="text-muted small">Etichetta</div>
                                <a href="{{ $order->sendcloudLabelUrl() }}" target="_blank" rel="noopener">
                                    Apri etichetta
                                </a>
                            </div>
                        @endif

                        @php
                            $sendcloudMeta = $order->sendcloudMeta();
                            $sendcloudCancelStatus = strtolower((string) data_get($sendcloudMeta, 'cancel_status'));
                            $sendcloudStatusSyncError = (string) data_get($sendcloudMeta, 'status_sync_error', '');
                            $sendcloudCancelError = (string) data_get($sendcloudMeta, 'cancel_error', '');
                            $sendcloudErrorText = $order->sendcloudError();
                            $sendcloudCombinedError = strtolower(trim($sendcloudStatusSyncError . ' ' . $sendcloudCancelError . ' ' . (string) $sendcloudErrorText));
                            $sendcloudAlreadyCancelling = str_contains($sendcloudCombinedError, 'already being cancelled')
                                || str_contains($sendcloudCombinedError, 'already being canceled')
                                || str_contains($sendcloudCombinedError, 'already cancelled')
                                || str_contains($sendcloudCombinedError, 'already canceled')
                                || str_contains($sendcloudCombinedError, 'being cancelled')
                                || str_contains($sendcloudCombinedError, 'being canceled')
                                || in_array($sendcloudCancelStatus, ['already_cancelling', 'cancelled', 'canceled'], true);
                            $sendcloudCancelledAt = data_get($sendcloudMeta, 'cancelled_at')
                                ?: data_get($sendcloudMeta, 'canceled_at')
                                ?: data_get($sendcloudMeta, 'status_synced_at');
                        @endphp

                        @if($order->sendcloudSyncedAt() || $order->sendcloudUpdatedAt() || $sendcloudCancelledAt)
                            <div class="mb-3">
                                <div class="text-muted small">Ultimo aggiornamento</div>
                                <div class="small">
                                    {{ $sendcloudCancelledAt ?: ($order->sendcloudUpdatedAt() ?: $order->sendcloudSyncedAt()) }}
                                </div>
                            </div>
                        @endif

                        @if($sendcloudAlreadyCancelling)
                            <div class="alert alert-info small mb-3">
                                Annullamento spedizione Sendcloud già richiesto o in corso. L’ordine può essere chiuso/rimborsato senza blocchi.
                            </div>
                        @elseif($sendcloudErrorText)
                            <div class="alert alert-danger small" style="white-space: pre-line;">{{ $sendcloudErrorText }}</div>
                        @elseif($order->sendcloudSkippedReason())
                            <div class="alert alert-secondary small" style="white-space: pre-line;">{{ $order->sendcloudSkippedReason() }}</div>
                        @elseif($order->sendcloudPendingWebhook())
                            <div class="alert alert-info small">
                                Ordine sincronizzato con Sendcloud. In attesa di webhook per tracking/barcode/etichetta.
                            </div>
                        @elseif(!$order->sendcloudTrackingNumber() && !$order->sendcloudLabelUrl() && !$order->sendcloudBarcode())
                            <div class="alert alert-warning small">
                                @if($order->isRefunded() || $order->isCanceled() || $order->isClosed())
                                    Spedizione non attiva per ordine chiuso, annullato o rimborsato.
                                @elseif(!$order->isPaid())
                                    Per creare la spedizione serve pagamento incassato.
                                @elseif(!$order->isProcessing())
                                    La spedizione parte dopo conferma giacenza.
                                @elseif(!$order->canCreateSendcloudShipment())
                                    Dati spedizione incompleti.
                                @else
                                    Spedizione pronta per sincronizzazione Sendcloud.
                                @endif
                            </div>
                        @endif

                        <div class="d-grid gap-2">
                            @if($order->canCreateSendcloudShipment() && Route::has('admin.orders.sendcloud.shipment.create'))
                                <form method="POST" action="{{ route('admin.orders.sendcloud.shipment.create', $order) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary w-100">
                                        Crea spedizione Sendcloud
                                    </button>
                                </form>
                            @endif

                            @if($order->sendcloudLabelUrl() && Route::has('admin.orders.sendcloud.label'))
                                <a href="{{ route('admin.orders.sendcloud.label', $order) }}" target="_blank" rel="noopener" class="btn btn-outline-dark w-100">
                                    Apri etichetta
                                </a>
                            @elseif($order->sendcloudLabelUrl())
                                <a href="{{ $order->sendcloudLabelUrl() }}" target="_blank" rel="noopener" class="btn btn-outline-dark w-100">
                                    Apri etichetta
                                </a>
                            @endif

                            @if(
                                !$order->isRefunded()
                                && !$order->isCanceled()
                                && !$order->isClosed()
                                && ($order->sendcloudTrackingNumber() || $order->sendcloudLabelUrl() || $order->sendcloudBarcode() || $order->sendcloudParcelId())
                                && Route::has('admin.orders.sendcloud.shipment.cancel')
                            )
                                <form method="POST" action="{{ route('admin.orders.sendcloud.shipment.cancel', $order) }}" onsubmit="return confirm('Annullare la spedizione Sendcloud, rimborsare il pagamento e annullare l’ordine?');">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger w-100">
                                        Annulla spedizione, rimborso e ordine
                                    </button>
                                </form>
                            @elseif($order->isRefunded() || $order->isCanceled() || $order->isClosed())
                                <div class="alert alert-secondary small mb-0">
                                    Azioni Sendcloud disabilitate: ordine {{ $order->payment_status === 'refunded' ? 'rimborsato' : 'chiuso/annullato' }}.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection