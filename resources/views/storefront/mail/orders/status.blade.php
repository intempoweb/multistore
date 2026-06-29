@extends('storefront.mail.layouts.base', ['title' => 'Aggiornamento ordine'])

@section('body')
@php
    $headline = match ($event) {
        'created' => 'Abbiamo ricevuto il tuo ordine',
        'accepted' => 'Il tuo ordine è stato accettato',
        'shipped' => 'Il tuo ordine è stato spedito',
        'completed' => 'Il tuo ordine è stato completato',
        'canceled' => 'Il tuo ordine è stato annullato',
        'refunded' => 'Il tuo ordine è stato rimborsato',
        default => 'Aggiornamento ordine',
    };

    $message = match ($event) {
        'created' => 'Grazie, il tuo ordine è stato registrato correttamente.',
        'accepted' => 'Abbiamo confermato la disponibilità dei prodotti e stiamo lavorando il tuo ordine.',
        'shipped' => 'Il tuo ordine è stato spedito. Trovi il tracking qui sotto.',
        'completed' => 'Il tuo ordine è stato completato.',
        'canceled' => 'Il tuo ordine è stato annullato.',
        'refunded' => 'Il pagamento del tuo ordine è stato rimborsato.',
        default => 'Lo stato del tuo ordine è stato aggiornato.',
    };

    $placedAt = $order->placed_at ?: $order->created_at;
    $decimals = $currencyDecimals ?? ($order->isB2b() ? 3 : 2);
    $fmt = fn ($value) => '€ ' . number_format((float) $value, $decimals, ',', '.');

    $customerName = trim((string) (
        $order->customer_name
        ?: $order->shipping_contact_name
        ?: trim(implode(' ', array_filter([$order->shipping_first_name, $order->shipping_last_name])))
    ));

    $billingName = trim(implode(' ', array_filter([
        $order->billing_first_name,
        $order->billing_last_name,
    ])));

    $shippingName = trim((string) (
        $order->shipping_contact_name
        ?: trim(implode(' ', array_filter([$order->shipping_first_name, $order->shipping_last_name])))
    ));

    $customerNotes = method_exists($order, 'customerNotesForDisplay')
        ? trim((string) $order->customerNotesForDisplay())
        : trim((string) ($order->notes ?? ''));

    $trackingUrl = $trackingUrl ?? null;

    if (!$trackingUrl && method_exists($order, 'sendcloudTrackingUrl')) {
        $trackingUrl = $order->sendcloudTrackingUrl();
    }

    $trackingNumber = $trackingNumber ?? null;

    if (!$trackingNumber) {
        $trackingNumber = $order->shipping_tracking_number;

        if (!$trackingNumber && method_exists($order, 'sendcloudTrackingNumber')) {
            $trackingNumber = $order->sendcloudTrackingNumber();
        }
    }

    $orderStatus = method_exists($order, 'orderStatusLabel') ? $order->orderStatusLabel() : (string) ($order->status ?? '-');
    $paymentStatus = method_exists($order, 'paymentStatusLabel') ? $order->paymentStatusLabel() : (string) ($order->payment_status ?? '-');
    $paymentMethod = $order->payment_method_label ?: strtoupper((string) $order->payment_gateway);
    $shippingMethod = $order->shipping_method_label ?: $order->shipping_carrier ?: $order->shipping_method_code;
@endphp

<h1 style="margin:0 0 16px;font-size:24px;color:#111827;">
    {{ $headline }}
</h1>

<p style="margin:0 0 24px;font-size:15px;line-height:1.6;">
    {{ $message }}
</p>

@if(!empty($productImagesDownloadUrl))
    <div style="margin:0 0 24px;padding:16px;border:1px solid #dbeafe;border-radius:10px;background:#eff6ff;font-size:14px;line-height:1.6;color:#1e3a8a;">
        <strong style="display:block;margin-bottom:6px;color:#111827;">Foto prodotti disponibili</strong>
        Il pacchetto immagini dei prodotti
        @if(!empty($productImagesZipSizeLabel))
            pesa {{ $productImagesZipSizeLabel }}
        @else
            è troppo grande
        @endif
        e non è stato allegato per evitare il blocco della mail.
        <div style="margin-top:14px;">
            <a href="{{ $productImagesDownloadUrl }}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold;">
                Scarica foto prodotti
            </a>
        </div>
    </div>
@elseif(!empty($productImagesAttachmentSkipped))
    <div style="margin:0 0 24px;padding:16px;border:1px solid #fde68a;border-radius:10px;background:#fffbeb;font-size:14px;line-height:1.6;color:#92400e;">
        Il pacchetto immagini dei prodotti non è stato allegato perché supera il limite di invio della mail.
    </div>
@endif

@include('storefront.mail.orders.partials.items', [
    'items' => $order->items,
    'currencyDecimals' => $decimals,
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
    <div style="margin:0 0 24px;padding:14px 16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;font-size:14px;line-height:1.6;color:#374151;">
        {{ $customerNotes }}
    </div>
@endif

<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;border-collapse:collapse;">
    <tr>
        <td style="padding:0 0 12px;font-weight:bold;color:#111827;font-size:18px;">
            Spedizione
        </td>
    </tr>
    <tr>
        <td style="padding:16px;border:1px solid #e5e7eb;border-radius:10px;background:#ffffff;line-height:1.55;font-size:14px;color:#374151;">
            <strong style="display:block;color:#111827;margin-bottom:4px;">
                {{ $order->shipping_company ?: $shippingName ?: $customerName ?: '-' }}
            </strong>
            {{ trim((string) $order->shipping_address_line_1 . ' ' . (string) $order->shipping_address_line_2) ?: '-' }}<br>
            {{ trim((string) $order->shipping_postcode . ' ' . (string) $order->shipping_city . ' ' . (string) $order->shipping_province) ?: '-' }}<br>
            {{ $order->shipping_country_code ?: '-' }}

            @if($order->shipping_email || $order->shipping_phone)
                <div style="margin-top:10px;color:#6b7280;font-size:13px;line-height:1.5;">
                    @if($order->shipping_email)
                        Email: {{ $order->shipping_email }}<br>
                    @endif
                    @if($order->shipping_phone)
                        Telefono: {{ $order->shipping_phone }}
                    @endif
                </div>
            @endif
        </td>
    </tr>
</table>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;border-collapse:collapse;">
    <tr>
        <td style="padding:0 0 12px;font-weight:bold;color:#111827;font-size:18px;">
            Fatturazione
        </td>
    </tr>
    <tr>
        <td style="padding:16px;border:1px solid #e5e7eb;border-radius:10px;background:#ffffff;line-height:1.55;font-size:14px;color:#374151;">
            <strong style="display:block;color:#111827;margin-bottom:4px;">
                {{ $order->billing_company ?: $billingName ?: $customerName ?: '-' }}
            </strong>
            {{ trim((string) $order->billing_address_line_1 . ' ' . (string) $order->billing_address_line_2) ?: '-' }}<br>
            {{ trim((string) $order->billing_postcode . ' ' . (string) $order->billing_city . ' ' . (string) $order->billing_province) ?: '-' }}<br>
            {{ $order->billing_country_code ?: '-' }}

            @if($order->billing_email || $order->billing_phone)
                <div style="margin-top:10px;color:#6b7280;font-size:13px;line-height:1.5;">
                    @if($order->billing_email)
                        Email: {{ $order->billing_email }}<br>
                    @endif
                    @if($order->billing_phone)
                        Telefono: {{ $order->billing_phone }}
                    @endif
                </div>
            @endif

            @if($order->invoice_required)
                <div style="margin-top:10px;color:#6b7280;font-size:13px;line-height:1.5;">
                    Fattura richiesta: Sì<br>
                    @if($order->customer_vat_number)
                        P.IVA: {{ $order->customer_vat_number }}<br>
                    @endif
                    @if($order->customer_tax_code)
                        Codice fiscale: {{ $order->customer_tax_code }}
                    @endif
                </div>
            @endif
        </td>
    </tr>
</table>

@if($trackingNumber)
    <h2 style="margin:0 0 10px;font-size:18px;color:#111827;">Tracking spedizione</h2>

    <div style="margin:0 0 24px;padding:16px;border:1px solid #e5e7eb;border-radius:10px;background:#f9fafb;font-size:14px;line-height:1.6;">
        Codice tracking:<br>
        <strong style="color:#111827;">{{ $trackingNumber }}</strong>

        @if($trackingUrl)
            <div style="margin-top:14px;">
                <a href="{{ $trackingUrl }}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold;">
                    Traccia la spedizione
                </a>
            </div>
        @endif
    </div>
@endif

<table width="100%" cellpadding="0" cellspacing="0" style="margin:0;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
    <tr>
        <td style="padding:12px 16px;background:#f9fafb;color:#6b7280;font-size:13px;">Ordine</td>
        <td align="right" style="padding:12px 16px;background:#f9fafb;font-weight:bold;">#{{ $order->order_number }}</td>
    </tr>

    @if($placedAt)
        <tr>
            <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Data ordine</td>
            <td align="right" style="padding:12px 16px;">
                {{ $placedAt->format('d/m/Y H:i') }}
            </td>
        </tr>
    @endif

    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Stato ordine</td>
        <td align="right" style="padding:12px 16px;">{{ $orderStatus }}</td>
    </tr>

    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Pagamento</td>
        <td align="right" style="padding:12px 16px;">{{ $paymentStatus }}</td>
    </tr>

    @if($paymentMethod)
        <tr>
            <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Metodo pagamento</td>
            <td align="right" style="padding:12px 16px;">{{ $paymentMethod }}</td>
        </tr>
    @endif

    @if($shippingMethod)
        <tr>
            <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Metodo spedizione</td>
            <td align="right" style="padding:12px 16px;">{{ $shippingMethod }}</td>
        </tr>
    @endif
</table>
@endsection
