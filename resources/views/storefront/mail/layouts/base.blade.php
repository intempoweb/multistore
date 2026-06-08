@php
    $mailConfig = $mailConfig ?? [];
    $storeName = $store->name ?? config('app.name', 'Store');

    $logo = trim((string) ($mailConfig['logo'] ?? ''));
    $contacts = trim((string) ($mailConfig['contacts'] ?? ''));
    $info = trim((string) ($mailConfig['info'] ?? ''));

    $storeDomain = trim((string) ($store->domain ?? ''));
    $storeBaseUrl = '';

    if ($storeDomain !== '') {
        $storeBaseUrl = str_starts_with($storeDomain, 'http://') || str_starts_with($storeDomain, 'https://')
            ? rtrim($storeDomain, '/')
            : 'https://' . trim($storeDomain, '/');
    }

    if ($logo === '' && !empty($store?->logo_url)) {
        $logo = trim((string) $store->logo_url);
    }

    if ($logo !== '' && !str_starts_with($logo, 'http://') && !str_starts_with($logo, 'https://')) {
        $logo = '/' . ltrim($logo, '/');
        $logo = $storeBaseUrl !== ''
            ? $storeBaseUrl . $logo
            : asset(ltrim($logo, '/'));
    }
@endphp

<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? $storeName }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,sans-serif;color:#1f2937;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:32px 12px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:14px;overflow:hidden;">
                <tr>
                    <td align="center" style="padding:30px 32px 24px;border-bottom:1px solid #e5e7eb;">
                        @if($logo !== '')
                            <img src="{{ $logo }}" alt="{{ $storeName }}" style="display:block;max-width:210px;max-height:74px;width:auto;height:auto;margin:0 auto;">
                        @else
                            <strong style="font-size:22px;line-height:1.2;">{{ $storeName }}</strong>
                        @endif
                    </td>
                </tr>

                <tr>
                    <td style="padding:34px 32px;">
                        @yield('body')
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:26px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px;line-height:1.55;">
                        @if($logo !== '')
                            <img src="{{ $logo }}" alt="{{ $storeName }}" style="display:block;max-width:130px;max-height:46px;width:auto;height:auto;margin:0 auto 14px;">
                        @else
                            <strong style="display:block;color:#374151;font-size:14px;margin-bottom:8px;">{{ $storeName }}</strong>
                        @endif

                        @if($contacts !== '')
                            <div>{{ $contacts }}</div>
                        @endif

                        @if($info !== '')
                            <div>{{ $info }}</div>
                        @endif

                        <div style="margin-top:14px;">
                            Email automatica. Se non hai richiesto questa operazione, puoi ignorare il messaggio.
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>