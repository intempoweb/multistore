@extends('storefront.mail.layouts.base', ['title' => $isReturn ? 'Nuova richiesta di reso' : 'Nuovo ticket assistenza'])

@section('body')
@php
    $requestNumber = $isReturn
        ? ($requestModel->request_number ?? '-')
        : ($requestModel->ticket_number ?? '-');

    $title = $isReturn
        ? 'Nuova richiesta di reso'
        : 'Nuovo ticket assistenza';
@endphp

<h1 style="margin:0 0 12px;font-size:24px;color:#111827;">
    {{ $title }}
</h1>

<p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#374151;">
    Richiesta ricevuta dal sito <strong>{{ $store->name ?? 'Store' }}</strong>.
</p>

<table width="100%" cellpadding="0" cellspacing="0" style="margin:0;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
    <tr>
        <td style="padding:12px 16px;background:#f9fafb;color:#6b7280;font-size:13px;width:36%;">Numero richiesta</td>
        <td style="padding:12px 16px;background:#f9fafb;color:#111827;font-weight:bold;">{{ $requestNumber }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Documento</td>
        <td style="padding:12px 16px;color:#111827;">{{ $requestModel->document_type ?? '-' }} {{ $requestModel->document_number ?? '-' }} · NUMREG {{ $requestModel->numreg_co99 ?? '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;background:#f9fafb;color:#6b7280;font-size:13px;">Cliente</td>
        <td style="padding:12px 16px;background:#f9fafb;color:#111827;">{{ $requestModel->clifor_cg44 ?? '-' }} · {{ $requestModel->customer?->ragsoanag_cg16 ?? $requestModel->contact_name ?? '-' }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Contatto</td>
        <td style="padding:12px 16px;color:#111827;">{{ $requestModel->contact_name ?? '-' }} · {{ $requestModel->contact_email ?? '-' }} · {{ $requestModel->contact_phone ?? '-' }}</td>
    </tr>
    @unless($isReturn)
        <tr>
            <td style="padding:12px 16px;background:#f9fafb;color:#6b7280;font-size:13px;">Oggetto</td>
            <td style="padding:12px 16px;background:#f9fafb;color:#111827;">{{ $requestModel->subject ?? '-' }}</td>
        </tr>
    @endunless
    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;">Righe collegate</td>
        <td style="padding:12px 16px;color:#111827;">{{ $requestModel->items?->count() ?? 0 }}</td>
    </tr>
    <tr>
        <td style="padding:12px 16px;background:#f9fafb;color:#6b7280;font-size:13px;">Allegati</td>
        <td style="padding:12px 16px;background:#f9fafb;color:#111827;">{{ $requestModel->attachments?->count() ?? 0 }}</td>
    </tr>
</table>

<h2 style="margin:24px 0 10px;font-size:18px;color:#111827;">
    {{ $isReturn ? 'Note richiesta' : 'Messaggio' }}
</h2>

<div style="margin:0;padding:14px 16px;border:1px solid #e5e7eb;border-radius:10px;background:#f9fafb;font-size:14px;line-height:1.65;color:#374151;white-space:pre-line;">
    {{ $isReturn ? ($requestModel->notes ?? '-') : ($requestModel->message ?? '-') }}
</div>

@if(($requestModel->items?->count() ?? 0) > 0)
    <h2 style="margin:24px 0 10px;font-size:18px;color:#111827;">Righe</h2>

    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
        @foreach($requestModel->items as $item)
            <tr>
                <td style="padding:12px 16px;border-bottom:1px solid #e5e7eb;color:#111827;">
                    <strong>{{ $item->sku ?? '-' }}</strong><br>
                    <span style="color:#6b7280;">{{ $item->description ?? '-' }}</span>
                </td>
                <td style="padding:12px 16px;border-bottom:1px solid #e5e7eb;text-align:right;color:#111827;">
                    {{ $isReturn ? ($item->requested_quantity ?? '-') : ($item->document_quantity ?? '-') }} {{ $item->unit ?? '' }}
                </td>
            </tr>
        @endforeach
    </table>
@endif
@endsection
