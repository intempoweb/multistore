<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Ricevuta Doganale {{ $order->order_number }}</title>
<style>
@page { margin: 28px 32px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; line-height: 1.35; }
h1 { font-size: 20px; margin: 0 0 4px; }
h2 { font-size: 12px; margin: 0 0 7px; text-transform: uppercase; }
.muted { color: #666; }
.header, .grid, .items { width: 100%; border-collapse: collapse; }
.header { margin-bottom: 22px; }
.header td { vertical-align: top; }
.right, .num { text-align: right; }
.center { text-align: center; }
.grid { margin-bottom: 14px; }
.grid td { width: 50%; vertical-align: top; padding: 10px; border: 1px solid #bbb; }
.box { border: 1px solid #bbb; padding: 10px; margin-bottom: 14px; }
.items th { background: #eee; padding: 7px 5px; border: 1px solid #bbb; font-size: 9px; text-align: left; }
.items td { padding: 7px 5px; border: 1px solid #ccc; vertical-align: top; }
.items .num, .items .center { white-space: nowrap; }
.totals { width: 45%; margin-left: 55%; margin-top: 12px; border-collapse: collapse; }
.totals td { padding: 4px 6px; }
.totals .grand td { border-top: 1px solid #555; font-weight: bold; font-size: 12px; padding-top: 7px; }
.footer { margin-top: 25px; padding-top: 8px; border-top: 1px solid #ccc; font-size: 8px; color: #666; }
</style>
</head>
<body>

<table class="header">
<tr>
<td>
<h1>RICEVUTA DOGANALE</h1>
<div class="muted">CUSTOMS RECEIPT</div>
</td>
<td class="right">
<strong>Ordine {{ $order->order_number }}</strong><br>
Data {{ optional($order->placed_at ?? $order->created_at)->format('d/m/Y') }}<br>
Valuta {{ $order->currency ?? 'EUR' }}
</td>
</tr>
</table>

<table class="grid">
<tr>
<td>
<h2>Mittente / Seller</h2>
<strong>{{ $seller['company'] ?? '-' }}</strong><br>
{{ $seller['address'] ?? '' }}<br>
{{ $seller['city'] ?? '' }} {{ $seller['country'] ?? '' }}<br>
@if(!empty($seller['vat']))
P. IVA / VAT: {{ $seller['vat'] }}<br>
@endif
@if(!empty($seller['tax_code']))
C.F.: {{ $seller['tax_code'] }}<br>
@endif
@if(!empty($seller['email']))
{{ $seller['email'] }}
@endif
</td>

<td>
<h2>Destinatario / Consignee</h2>
@if($order->shipping_company)
<strong>{{ $order->shipping_company }}</strong><br>
@endif
<strong>{{ $order->shipping_contact_name ?: trim(($order->shipping_first_name ?? '') . ' ' . ($order->shipping_last_name ?? '')) }}</strong><br>
{{ $order->shipping_address_line_1 }}
@if($order->shipping_address_line_2)
<br>{{ $order->shipping_address_line_2 }}
@endif
<br>
{{ $order->shipping_postcode }} {{ $order->shipping_city }}
@if($order->shipping_province)
({{ $order->shipping_province }})
@endif
<br>
<strong>{{ $order->shipping_country_code }}</strong>
@if($order->shipping_phone)
<br>Tel. {{ $order->shipping_phone }}
@endif
@if($order->shipping_email)
<br>{{ $order->shipping_email }}
@endif
</td>
</tr>
</table>

<div class="box">
<strong>Riferimento ordine:</strong> {{ $order->order_number }}
&nbsp;&nbsp;
<strong>Metodo di pagamento:</strong>
{{ $order->payment_method_label ?: ($order->payment_gateway ?: '-') }}

@if($order->shipping_method_label)
&nbsp;&nbsp;
<strong>Spedizione:</strong> {{ $order->shipping_method_label }}
@endif
</div>

<h2>Merce / Goods</h2>

<table class="items">
<thead>
<tr>
<th>SKU</th>
<th>Descrizione</th>
<th class="center">Q.tà</th>
<th class="num">Prezzo unit.</th>
<th class="num">Totale</th>
</tr>
</thead>
<tbody>

@foreach($order->items as $item)
<tr>
<td>{{ $item->sku }}</td>
<td>
<strong>{{ $item->product_name }}</strong>
@if($item->product_description)
<br><span class="muted">{{ $item->product_description }}</span>
@endif
</td>
<td class="center">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', '.'), '0'), ',') }}</td>
<td class="num">{{ number_format((float) ($item->price ?? $item->price_gross ?? $item->price_net ?? 0), 2, ',', '.') }} {{ $order->currency }}</td>
<td class="num">{{ number_format((float) ($item->row_total ?? 0), 2, ',', '.') }} {{ $order->currency }}</td>
</tr>
@endforeach

</tbody>
</table>

<table class="totals">
<tr>
<td>Subtotale</td>
<td class="num">{{ number_format((float) $order->subtotal, 2, ',', '.') }} {{ $order->currency }}</td>
</tr>

@if((float) $order->discount_total != 0)
<tr>
<td>Sconti</td>
<td class="num">- {{ number_format(abs((float) $order->discount_total), 2, ',', '.') }} {{ $order->currency }}</td>
</tr>
@endif

<tr>
<td>Spedizione</td>
<td class="num">{{ number_format((float) $order->shipping_total, 2, ',', '.') }} {{ $order->currency }}</td>
</tr>

<tr>
<td>Imposte</td>
<td class="num">{{ number_format((float) $order->tax_total, 2, ',', '.') }} {{ $order->currency }}</td>
</tr>

<tr class="grand">
<td>TOTALE</td>
<td class="num">{{ number_format((float) $order->grand_total, 2, ',', '.') }} {{ $order->currency }}</td>
</tr>
</table>

<div class="footer">
Documento generato dal sistema e-commerce sulla base dei dati registrati nell'ordine {{ $order->order_number }}.
</div>

</body>
</html>
