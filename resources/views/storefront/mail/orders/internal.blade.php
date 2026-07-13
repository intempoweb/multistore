@extends('storefront.mail.layouts.base', ['title' => 'Notifica ordine interna'])

@section('body')
@php
    $decimals = $currencyDecimals ?? $order->priceDecimals();
    $fmt = fn ($value) => '€ ' . number_format((float) $value, $decimals, ',', '.');

    $orderNumber = $order->order_number ?: $order->id;
    $placedAt = $order->placed_at ?? $order->created_at;
    $orderStatus = method_exists($order, 'orderStatusLabel') ? $order->orderStatusLabel() : (string) ($order->status ?? '-');
    $paymentStatus = method_exists($order, 'paymentStatusLabel') ? $order->paymentStatusLabel() : (string) ($order->payment_status ?? '-');
    $customerNotes = method_exists($order, 'customerNotesForDisplay') ? trim((string) $order->customerNotesForDisplay()) : trim((string) ($order->notes ?? ''));

    $customerLabel = trim((string) (
        $order->customer_company_name
        ?: $order->billing_company
        ?: $order->customer_name
        ?: $order->shipping_contact_name
        ?: trim(implode(' ', array_filter([$order->shipping_first_name, $order->shipping_last_name])))
        ?: '-'
    ));

    $billingName = trim(implode(' ', array_filter([
        $order->billing_first_name,
        $order->billing_last_name,
    ])));

    $shippingName = trim((string) (
        $order->shipping_contact_name
        ?: trim(implode(' ', array_filter([$order->shipping_first_name, $order->shipping_last_name])))
    ));

    $billingAddress = trim((string) $order->billing_address_line_1 . ' ' . (string) $order->billing_address_line_2);
    $billingCityLine = trim((string) $order->billing_postcode . ' ' . (string) $order->billing_city . ' ' . (string) $order->billing_province);

    $shippingAddress = trim((string) $order->shipping_address_line_1 . ' ' . (string) $order->shipping_address_line_2);
    $shippingCityLine = trim((string) $order->shipping_postcode . ' ' . (string) $order->shipping_city . ' ' . (string) $order->shipping_province);

    $invoiceSdi = method_exists($order, 'b2cInvoiceSdiForDisplay') ? $order->b2cInvoiceSdiForDisplay() : ($order->billing_sdi ?? '-');
    $invoicePec = method_exists($order, 'b2cInvoicePecForDisplay') ? $order->b2cInvoicePecForDisplay() : ($order->billing_pec ?? '-');

    $trackingNumber = trim((string) ($order->shipping_tracking_number ?? ''));

    if ($trackingNumber === '' && method_exists($order, 'sendcloudTrackingNumber')) {
        $trackingNumber = trim((string) $order->sendcloudTrackingNumber());
    }

    $trackingUrl = null;

    if (method_exists($order, 'sendcloudTrackingUrl')) {
        $trackingUrl = $order->sendcloudTrackingUrl();
    }
@endphp

<h1 style="margin:0 0 12px;font-size:24px;color:#111827;">
    {{ $eventLabel ?? 'Nuovo ordine' }} #{{ $orderNumber }}
</h1>

<p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#374151;">
    Ordine effettuato da <strong>{{ $customerLabel }}</strong>.
</p>

@if(!empty($adminOrderUrl))
    <p style="margin:0 0 26px;">
        <a href="{{ $adminOrderUrl }}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold;">
            Apri ordine in admin
        </a>
    </p>
@endif

@include('storefront.mail.orders.partials.items', [
    'items' => $items ?? $order->items,
    'currencyDecimals' => $decimals,
    'showInternalData' => true,
    'itemsTotalCount' => $itemsTotalCount ?? $order->items->count(),
    'itemsDisplayLimit' => $itemsDisplayLimit ?? null,
])

<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;border-top:1px solid #e5e7eb;padding-top:14px;">
    <tr>
        <td style="padding:5px 0;color:#6b7280;">Imponibile</td>
        <td align="right" style="padding:5px 0;">{{ $fmt($order->subtotal) }}</td>
    </tr>
    <tr>
        <td style="padding:5px 0;color:#6b7280;">Sconti</td>
        <td align="right" style="padding:5px 0;">{{ $fmt($order->discount_total) }}</td>
    </tr>
    <tr>
        <td style="padding:5px 0;color:#6b7280;">Spedizione</td>
        <td align="right" style="padding:5px 0;">{{ $fmt($order->shipping_total) }}</td>
    </tr>
    <tr>
        <td style="padding:5px 0;color:#6b7280;">IVA</td>
        <td align="right" style="padding:5px 0;">{{ $fmt($order->tax_total) }}</td>
    </tr>
    <tr>
        <td style="padding:10px 0 0;font-weight:bold;color:#111827;font-size:16px;">Totale</td>
        <td align="right" style="padding:10px 0 0;font-weight:bold;color:#111827;font-size:16px;">
            {{ $fmt($order->grand_total) }}
        </td>
    </tr>
</table>

@if($customerNotes !== '')
    <h2 style="margin:0 0 10px;font-size:18px;color:#111827;">Note cliente</h2>
    <div style="margin:0 0 24px;padding:14px 16px;border:1px solid #e5e7eb;border-radius:10px;background:#f9fafb;font-size:14px;line-height:1.6;color:#374151;">
        {{ $customerNotes }}
    </div>
@endif

<h2 style="margin:0 0 10px;font-size:18px;color:#111827;">Spedizione</h2>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#ffffff;">
    <tr>
        <td style="padding:12px 16px;background:#f9fafb;color:#6b7280;font-size:13px;width:38%;">Destinatario</td>
        <td style="padding:12px 16px;background:#f9fafb;font-weight:bold;color:#111827;">
            {{ $order->shipping_company ?: $shippingName ?: '-' }}
        </td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;vertical-align:top;">Indirizzo</td>
        <td style="padding:12px 16px;line-height:1.5;color:#111827;">
            {{ $shippingAddress ?: '-' }}<br>
            {{ $shippingCityLine ?: '-' }}<br>
            {{ $order->shipping_country_code ?: '-' }}
        </td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Email</td>
        <td style="padding:12px 16px;color:#111827;">{{ $order->shipping_email ?: $order->customer_email ?: '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Telefono</td>
        <td style="padding:12px 16px;color:#111827;">{{ $order->shipping_phone ?: $order->customer_phone ?: '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Metodo spedizione</td>
        <td style="padding:12px 16px;color:#111827;">{{ $order->shipping_method_label ?: $order->shipping_method_code ?: $order->shipping_carrier ?: '-' }}</td>
    </tr>
</table>

<h2 style="margin:0 0 10px;font-size:18px;color:#111827;">Fatturazione</h2>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#ffffff;">
    <tr>
        <td style="padding:12px 16px;background:#f9fafb;color:#6b7280;font-size:13px;width:38%;">Intestatario</td>
        <td style="padding:12px 16px;background:#f9fafb;font-weight:bold;color:#111827;">
            {{ $order->billing_company ?: $billingName ?: $customerLabel ?: '-' }}
        </td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;vertical-align:top;">Indirizzo</td>
        <td style="padding:12px 16px;line-height:1.5;color:#111827;">
            {{ $billingAddress ?: '-' }}<br>
            {{ $billingCityLine ?: '-' }}<br>
            {{ $order->billing_country_code ?: '-' }}
        </td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Email</td>
        <td style="padding:12px 16px;color:#111827;">{{ $order->billing_email ?: $order->customer_email ?: '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Telefono</td>
        <td style="padding:12px 16px;color:#111827;">{{ $order->billing_phone ?: $order->customer_phone ?: '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Fattura richiesta</td>
        <td style="padding:12px 16px;color:#111827;">{{ $order->invoice_required ? 'Sì' : 'No' }}</td>
    </tr>
    @if($order->invoice_required)
        <tr>
            <td style="padding:12px 16px;color:#6b7280;font-size:13px;">P.IVA / Cod. fiscale</td>
            <td style="padding:12px 16px;color:#111827;">
                {{ $order->customer_vat_number ?: '-' }} / {{ $order->customer_tax_code ?: '-' }}
            </td>
        </tr>
        <tr>
            <td style="padding:12px 16px;color:#6b7280;font-size:13px;">SDI / PEC</td>
            <td style="padding:12px 16px;color:#111827;">
                {{ $invoiceSdi ?: '-' }} / {{ $invoicePec ?: '-' }}
            </td>
        </tr>
    @endif
</table>

@if($trackingNumber !== '')
    <h2 style="margin:0 0 10px;font-size:18px;color:#111827;">Tracking</h2>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#ffffff;">
        <tr>
            <td style="padding:12px 16px;background:#f9fafb;color:#6b7280;font-size:13px;width:38%;">Codice tracking</td>
            <td style="padding:12px 16px;background:#f9fafb;font-weight:bold;color:#111827;">{{ $trackingNumber }}</td>
        </tr>
    </table>

    @if($trackingUrl)
        <p style="margin:0 0 24px;">
            <a href="{{ $trackingUrl }}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold;">
                Traccia la spedizione
            </a>
        </p>
    @endif
@endif

<h2 style="margin:0 0 10px;font-size:18px;color:#111827;">Riepilogo ordine</h2>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:0;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
    <tr>
        <td style="padding:12px 16px;background:#f9fafb;color:#6b7280;font-size:13px;">Store / canale</td>
        <td align="right" style="padding:12px 16px;background:#f9fafb;font-weight:bold;color:#111827;">
            {{ $store->name ?? 'Store' }} / {{ $order->channelLabel() }}
        </td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Ordine</td>
        <td align="right" style="padding:12px 16px;color:#111827;">#{{ $orderNumber }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Data</td>
        <td align="right" style="padding:12px 16px;color:#111827;">
            {{ optional($placedAt)->format('d/m/Y H:i') ?: '-' }}
        </td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Stato ordine</td>
        <td align="right" style="padding:12px 16px;color:#111827;">{{ $orderStatus }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Pagamento</td>
        <td align="right" style="padding:12px 16px;color:#111827;">{{ $paymentStatus }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Metodo pagamento</td>
        <td align="right" style="padding:12px 16px;color:#111827;">{{ $order->payment_method_label ?: $order->payment_method_code ?: $order->payment_gateway ?: '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Transaction ID</td>
        <td align="right" style="padding:12px 16px;color:#111827;">{{ $order->payment_transaction_id ?: '-' }}</td>
    </tr>
</table>
@endsection
