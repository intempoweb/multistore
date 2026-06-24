@extends('storefront.mail.layouts.base')

@section('body')
<h1>Report sincronizzazione ERP</h1>

<p><strong>{{ $title }}</strong></p>

@if($startedAt)
<p><strong>Inizio:</strong> {{ $startedAt }}</p>
@endif

@if($finishedAt)
<p><strong>Fine:</strong> {{ $finishedAt }}</p>
@endif

<p>
    <strong>Completati:</strong> {{ $completed }}<br>
    <strong>Errori:</strong> {{ $failed }}
</p>

@php
    $labels = [
        'erp:sync-attributes' => 'Attributi',
        'erp:sync-products' => 'Prodotti',
        'erp:sync-product-comparisons' => 'Articoli comparativi',
        'erp:sync-product-attribute-values' => 'Attributi prodotto',
        'erp:sync-group-descriptions' => 'Descrizioni catalogo',
        'erp:sync-customers' => 'Clienti',
        'erp:sync-customer-acl' => 'Permessi clienti',
        'erp:sync-customer-shipping-addresses' => 'Indirizzi spedizione',
        'erp:sync-customer-listini' => 'Listini clienti',
        'erp:sync-store-visible-groups' => 'Visibilità gruppi store',
        'erp:sync-public-prices' => 'Prezzi pubblici',
        'erp:sync-price-tiers' => 'Fasce prezzo B2B',
        'erp:sync-stock' => 'Giacenze',
        'erp:sync-media' => 'Media',
        'erp:export-orders' => 'Esportazione ordini',
    ];
@endphp

@foreach($runs as $run)
    @php
        $name = $labels[$run->command_name] ?? $run->command_name;

        $duration = '-';
        if ($run->started_at && $run->finished_at) {
            $duration = $run->started_at->diffForHumans($run->finished_at, true);
        }
    @endphp

    <div style="margin-bottom:20px;padding:15px;border:1px solid #dcdcdc;border-radius:6px;">

        <h3 style="margin-top:0;">
            @if($run->status === 'completed')
                ✅ {{ $name }}
            @elseif($run->status === 'failed')
                ❌ {{ $name }}
            @else
                ⏳ {{ $name }}
            @endif
        </h3>

        <p>
            <strong>Durata:</strong> {{ $duration }}<br>
            <strong>Inizio:</strong> {{ $run->started_at }}<br>
            <strong>Fine:</strong> {{ $run->finished_at }}
        </p>

        @if($run->status === 'failed')
            <div style="background:#fff3f3;padding:10px;border-left:4px solid #cc0000;">
                <strong>Errore:</strong><br>
                {{ $run->error_message ?: 'Errore non specificato.' }}
            </div>
        @elseif(!empty($run->output))
            <div style="background:#f7f7f7;padding:10px;border-left:4px solid #0a7a0a;">
                <strong>Risultato:</strong><br>
                <pre style="white-space:pre-wrap;margin:10px 0 0 0;">{{ $run->output }}</pre>
            </div>
        @endif

    </div>
@endforeach
@endsection