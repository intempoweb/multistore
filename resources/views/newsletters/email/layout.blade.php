@php
    $logo = $store?->logo_url;
    $heroTitle = $newsletter->hero_title ?: $newsletter->subject;
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f3f4f6;margin:0;padding:0;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;background:#ffffff;border-collapse:collapse;">
                <tr>
                    <td align="center" style="padding:24px 24px 16px 24px;">
                        @if($logo)
                            <img src="{{ $logo }}" alt="{{ $store?->name }}" width="180" style="display:block;max-width:180px;height:auto;border:0;">
                        @else
                            <div style="font-family:Arial,sans-serif;font-size:22px;font-weight:bold;color:#111827;">{{ $store?->name }}</div>
                        @endif
                    </td>
                </tr>
                @if($newsletter->hero_image_url)
                    <tr>
                        <td>
                            <img src="{{ $newsletter->hero_image_url }}" alt="" width="640" style="display:block;width:100%;max-width:640px;height:auto;border:0;">
                        </td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:28px 32px 14px 32px;font-family:Arial,sans-serif;color:#111827;text-align:center;">
                        <h1 style="margin:0;font-size:30px;line-height:36px;font-weight:bold;">{{ $heroTitle }}</h1>
                        @if($newsletter->hero_text)
                            <p style="margin:14px 0 0 0;font-size:16px;line-height:24px;color:#4b5563;">{{ $newsletter->hero_text }}</p>
                        @endif
                    </td>
                </tr>
                @if($newsletter->intro_html)
                    <tr>
                        <td style="padding:0 32px 20px 32px;font-family:Arial,sans-serif;font-size:15px;line-height:23px;color:#374151;">
                            {!! $newsletter->intro_html !!}
                        </td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:8px 24px 16px 24px;">
                        @forelse($products as $product)
                            @include('newsletters.email.components.product', ['product' => $product])
                        @empty
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="padding:20px;font-family:Arial,sans-serif;font-size:15px;color:#6b7280;text-align:center;">
                                        Nessun prodotto selezionato.
                                    </td>
                                </tr>
                            </table>
                        @endforelse
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px 32px;background:#111827;font-family:Arial,sans-serif;color:#ffffff;text-align:center;">
                        <div style="font-size:16px;font-weight:bold;">{{ $store?->name }}</div>
                        <div style="margin-top:8px;font-size:12px;line-height:18px;color:#d1d5db;">
                            Ricevi questa comunicazione tramite il provider newsletter configurato. Gestione iscrizione e disiscrizione restano nel provider.
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
