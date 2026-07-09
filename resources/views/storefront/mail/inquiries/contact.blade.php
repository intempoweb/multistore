@extends('storefront.mail.layouts.base', ['title' => 'Nuova richiesta contatti'])

@section('body')
@php
    $fullName = trim(($payload['first_name'] ?? '') . ' ' . ($payload['last_name'] ?? ''));
@endphp

<h1 style="margin:0 0 12px;font-size:24px;color:#111827;">
    Nuova richiesta da form Contatti
</h1>

<p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#374151;">
    Hai ricevuto una nuova richiesta dal sito <strong>{{ $store->name ?? 'Store' }}</strong>.
</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:0;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
    <tr>
        <td style="padding:12px 16px;background:#f9fafb;color:#6b7280;font-size:13px;width:36%;">Nome e cognome</td>
        <td style="padding:12px 16px;background:#f9fafb;color:#111827;font-weight:bold;">{{ $fullName !== '' ? $fullName : '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Paese</td>
        <td style="padding:12px 16px;color:#111827;">{{ $payload['country'] ?? '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Azienda</td>
        <td style="padding:12px 16px;color:#111827;">{{ ($payload['company'] ?? '') !== '' ? $payload['company'] : '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Email</td>
        <td style="padding:12px 16px;color:#111827;">{{ $payload['email'] ?? '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Telefono</td>
        <td style="padding:12px 16px;color:#111827;">{{ $payload['phone'] ?? '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Oggetto</td>
        <td style="padding:12px 16px;color:#111827;">{{ $payload['subject'] ?? '-' }}</td>
    </tr>
</table>

<h2 style="margin:24px 0 10px;font-size:18px;color:#111827;">Messaggio</h2>

<div style="margin:0;padding:14px 16px;border:1px solid #e5e7eb;border-radius:10px;background:#f9fafb;font-size:14px;line-height:1.65;color:#374151;white-space:pre-line;">
    {{ $payload['message'] ?? '-' }}
</div>
@endsection
