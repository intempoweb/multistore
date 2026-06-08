@extends('storefront.mail.layouts.base', ['title' => 'Reset password'])

@section('body')
    <h1 style="margin:0 0 16px;font-size:24px;color:#111827;">
        Reimposta la password
    </h1>

    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
        Ciao, abbiamo ricevuto una richiesta di reset password per il tuo account.
    </p>

    <p style="margin:0 0 20px;font-size:15px;line-height:1.6;">
        Il link è valido per <strong>{{ $expiresMinutes ?? 60 }} minuti</strong>.
    </p>

    <p style="margin:28px 0;">
        <a href="{{ $resetUrl }}" style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;padding:13px 22px;border-radius:8px;font-weight:bold;">
            Reimposta password
        </a>
    </p>

    <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;">
        Se il pulsante non funziona, copia e incolla questo link nel browser:<br>
        <span style="word-break:break-all;">{{ $resetUrl }}</span>
    </p>
@endsection