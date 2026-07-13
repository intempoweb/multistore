@php
    $currencyDecimals = $currencyDecimals
        ?? (isset($order) && method_exists($order, 'priceDecimals') ? $order->priceDecimals() : 2);

    $items = collect($items ?? [])
        ->filter(function ($item) {
            $sku = strtolower(trim((string) ($item->sku ?? '')));
            $name = strtolower(trim((string) ($item->product_name ?? '')));
            $description = strtolower(trim((string) ($item->product_description ?? '')));

            return !str_contains($name, 'spesa spedizione')
                && !str_contains($description, 'spesa spedizione')
                && !str_contains($sku, 'shipping')
                && !str_contains($sku, 'spedizione');
        })
        ->values();
@endphp

@if($items->isNotEmpty())
    <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;border-collapse:collapse;">
        <tr>
            <td colspan="4" style="padding:0 0 12px;font-weight:bold;color:#111827;font-size:18px;">
                Prodotti ordinati
            </td>
        </tr>

        @foreach($items as $item)
            @php
                $thumbnail = media_url($item->product_thumbnail_url, 60 * 24 * 7);

                $name = trim((string) ($item->product_name ?: $item->sku));
                $description = trim(strip_tags((string) ($item->product_description ?? '')));
                $sku = trim((string) ($item->sku ?? ''));
                $quantity = (float) ($item->quantity ?? 0);
                $rowTotal = (float) ($item->row_total ?? 0);
            @endphp

            <tr>
                <td style="padding:14px 0;border-top:1px solid #e5e7eb;width:64px;vertical-align:top;">
                    @if($thumbnail)
                        <img src="{{ $thumbnail }}"
                             alt="{{ $name }}"
                             style="display:block;width:54px;height:54px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;">
                    @else
                        <div style="width:54px;height:54px;border-radius:8px;background:#f3f4f6;border:1px solid #e5e7eb;"></div>
                    @endif
                </td>

                <td style="padding:14px 10px;border-top:1px solid #e5e7eb;vertical-align:top;">
                    <div style="font-size:14px;font-weight:bold;color:#111827;line-height:1.35;">
                        {{ $name }}
                    </div>

                    @if($description !== '')
                        <div style="font-size:12px;color:#6b7280;line-height:1.45;margin-top:4px;">
                            {{ Str::limit($description, 120) }}
                        </div>
                    @endif

                    @if($sku !== '')
                        <div style="font-size:12px;color:#6b7280;margin-top:6px;">
                            SKU {{ $sku }}
                        </div>
                    @endif
                </td>

                <td align="center"
                    style="padding:14px 8px;border-top:1px solid #e5e7eb;font-size:13px;white-space:nowrap;vertical-align:top;color:#374151;">
                    Q.tà {{ number_format($quantity, 0, ',', '.') }}
                </td>

                <td align="right"
                    style="padding:14px 0;border-top:1px solid #e5e7eb;font-size:14px;font-weight:bold;white-space:nowrap;vertical-align:top;color:#111827;">
                    € {{ number_format($rowTotal, $currencyDecimals, ',', '.') }}
                </td>
            </tr>
        @endforeach
    </table>
@endif
