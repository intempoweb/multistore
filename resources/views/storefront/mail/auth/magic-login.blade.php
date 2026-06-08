@extends('storefront.mail.layouts.base', ['title' => 'Link di accesso'])

@section('body')
    <h1 style="margin:0 0 16px;font-size:24px;color:#111827;">
        Accedi al tuo account
    </h1>

    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
        Ciao, usa il pulsante qui sotto per accedere al tuo account senza password.
    </p>

    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
        Il link scade tra <strong>{{ $expireMinutes ?? 30 }} minuti</strong>.
    </p>

    <p style="margin:28px 0;">
        <a href="{{ $signedUrl }}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:13px 22px;border-radius:8px;font-weight:bold;">
            Accedi ora
        </a>
    </p>

    <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;">
        Se il pulsante non funziona, copia e incolla questo link nel browser:<br>
        <span style="word-break:break-all;">{{ $signedUrl }}</span>
    </p>
@endsection