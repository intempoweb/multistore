<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;border-top:1px solid #e5e7eb;">
    <tr>
        <td width="180" valign="top" style="padding:22px 12px 22px 8px;width:180px;">
            @if($product['image_url'])
                <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }}" width="168" style="display:block;width:168px;max-width:168px;height:auto;border:0;">
            @endif
        </td>
        <td valign="top" style="padding:22px 8px 22px 12px;font-family:Arial,sans-serif;color:#111827;">
            @if(!empty($product['badges']))
                <div style="margin-bottom:8px;">
                    @foreach($product['badges'] as $badge)
                        <span style="display:inline-block;margin:0 4px 4px 0;padding:3px 8px;background:#111827;color:#ffffff;font-size:11px;line-height:14px;font-weight:bold;text-transform:uppercase;">{{ $badge }}</span>
                    @endforeach
                </div>
            @endif
            <h2 style="margin:0 0 6px 0;font-size:20px;line-height:26px;font-weight:bold;">{{ $product['name'] }}</h2>
            <div style="font-size:12px;line-height:18px;color:#6b7280;margin-bottom:10px;">SKU {{ $product['sku'] }}</div>
            @if($product['description'])
                <p style="margin:0 0 12px 0;font-size:14px;line-height:21px;color:#4b5563;">{{ $product['description'] }}</p>
            @endif
            @if($product['price_label'])
                <div style="margin:0 0 14px 0;font-size:22px;line-height:28px;font-weight:bold;color:#111827;">{{ $product['price_label'] }}</div>
            @endif
            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td style="background:#111827;">
                        <a href="{{ $product['url'] }}" style="display:inline-block;padding:11px 18px;font-family:Arial,sans-serif;font-size:14px;line-height:18px;color:#ffffff;text-decoration:none;font-weight:bold;">Ordina</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
