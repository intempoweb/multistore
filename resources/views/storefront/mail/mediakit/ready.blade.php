@extends('storefront.mail.layouts.base', ['title' => 'MediaKit prodotti'])

@section('body')
@php
    $customerName = trim((string) data_get(
        $mediaKitRequest->meta,
        'customer.ragione_sociale',
        ''
    ));

    $expiresAt = $mediaKitRequest->expires_at ?? null;

    $fileSizeLabel = null;

    if (!empty($mediaKitRequest->output_size)) {
        $bytes = (int) $mediaKitRequest->output_size;

        if ($bytes >= 1073741824) {
            $fileSizeLabel = number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
        } elseif ($bytes >= 1048576) {
            $fileSizeLabel = number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        } elseif ($bytes >= 1024) {
            $fileSizeLabel = number_format($bytes / 1024, 2, ',', '.') . ' KB';
        } else {
            $fileSizeLabel = $bytes . ' byte';
        }
    }
@endphp

<h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;color:#111827;">
    Il tuo MediaKit è pronto
</h1>

<p style="margin:0 0 18px;font-size:15px;line-height:1.65;color:#374151;">
    Gentile{{ $customerName !== '' ? ' ' . $customerName : ' cliente' }},
</p>

<p style="margin:0 0 24px;font-size:15px;line-height:1.65;color:#374151;">
    abbiamo preparato l’archivio con le immagini e i documenti dei prodotti selezionati.
</p>

@if($attachArchive)
    <div style="margin:0 0 24px;padding:16px;border:1px solid #d1fae5;border-radius:10px;background:#ecfdf5;font-size:14px;line-height:1.6;color:#065f46;">
        Il MediaKit è allegato direttamente a questa email.
    </div>
@elseif(!empty($downloadUrl))
    <div style="margin:0 0 24px;padding:20px;border:1px solid #dbeafe;border-radius:10px;background:#eff6ff;">
        <div style="margin:0 0 14px;font-size:14px;line-height:1.6;color:#1e3a8a;">
            Il file è disponibile tramite il collegamento seguente.
        </div>

        <a href="{{ $downloadUrl }}"
           style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:13px 22px;border-radius:8px;font-size:14px;font-weight:bold;">
            Scarica MediaKit
        </a>
    </div>
@endif

@if(!$attachArchive && $expiresAt)
    <p style="margin:0 0 24px;font-size:13px;line-height:1.6;color:#6b7280;">
        Il collegamento è temporaneo e sarà disponibile fino al
        <strong style="color:#374151;">
            {{ \Illuminate\Support\Carbon::parse($expiresAt)->format('d/m/Y H:i') }}
        </strong>.
    </p>
@endif

<table width="100%" cellpadding="0" cellspacing="0"
       style="margin:0;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;border-collapse:separate;">
    <tr>
        <td style="padding:12px 16px;background:#f9fafb;color:#6b7280;font-size:13px;">
            Tipo archivio
        </td>
        <td align="right"
            style="padding:12px 16px;background:#f9fafb;font-weight:bold;color:#111827;">
            {{ ucfirst((string) ($mediaKitRequest->source_type ?? 'MediaKit')) }}
        </td>
    </tr>

    @if($fileSizeLabel)
        <tr>
            <td style="padding:12px 16px;color:#6b7280;font-size:13px;border-top:1px solid #e5e7eb;">
                Dimensione
            </td>
            <td align="right"
                style="padding:12px 16px;color:#111827;border-top:1px solid #e5e7eb;">
                {{ $fileSizeLabel }}
            </td>
        </tr>
    @endif

    <tr>
        <td style="padding:12px 16px;color:#6b7280;font-size:13px;border-top:1px solid #e5e7eb;">
            Identificativo
        </td>
        <td align="right"
            style="padding:12px 16px;color:#111827;border-top:1px solid #e5e7eb;font-size:12px;">
            {{ $mediaKitRequest->uuid }}
        </td>
    </tr>
</table>
@endsection