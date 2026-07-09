@extends('storefront.mail.layouts.base', ['title' => 'Nuova richiesta regalistica aziendale'])

@section('body')
@php
    $fullName = trim(($payload['first_name'] ?? '') . ' ' . ($payload['last_name'] ?? ''));
    $productType = (string) ($payload['product_type'] ?? '-');
    $productLabel = $productType === 'agenda' ? 'Agenda' : ($productType === 'taccuino' ? 'Taccuino' : $productType);
@endphp

<h1 style="margin:0 0 12px;font-size:24px;color:#111827;">
    Nuova richiesta Regalistica Aziendale
</h1>

<p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#374151;">
    Hai ricevuto una richiesta corporate gift dal sito <strong>{{ $store->name ?? 'Store' }}</strong>.
</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:0;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
    <tr>
        <td style="padding:12px 16px;background:#f9fafb;color:#6b7280;font-size:13px;width:36%;">Nome e cognome</td>
        <td style="padding:12px 16px;background:#f9fafb;color:#111827;font-weight:bold;">{{ $fullName !== '' ? $fullName : '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Azienda</td>
        <td style="padding:12px 16px;color:#111827;">{{ $payload['company'] ?? '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Paese</td>
        <td style="padding:12px 16px;color:#111827;">{{ $payload['country'] ?? '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Prodotto</td>
        <td style="padding:12px 16px;color:#111827;">{{ $productLabel }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Quantita richiesta</td>
        <td style="padding:12px 16px;color:#111827;">{{ number_format((int) ($payload['quantity'] ?? 0), 0, ',', '.') }} pz</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Email</td>
        <td style="padding:12px 16px;color:#111827;">{{ $payload['email'] ?? '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Telefono</td>
        <td style="padding:12px 16px;color:#111827;">{{ $payload['phone'] ?? '-' }}</td>
    </tr>
</table>

@if(!empty($payload['notes']))
    <h2 style="margin:24px 0 10px;font-size:18px;color:#111827;">Contenuto allegato / note</h2>
    <div style="margin:0;padding:14px 16px;border:1px solid #e5e7eb;border-radius:10px;background:#f9fafb;font-size:14px;line-height:1.65;color:#374151;white-space:pre-line;">
        {{ $payload['notes'] }}
    </div>
@endif

<p style="margin:18px 0 0;color:#6b7280;font-size:13px;line-height:1.5;">
    Eventuali file caricati (logo e contenuto) sono allegati a questa email.
</p>
@endsection
