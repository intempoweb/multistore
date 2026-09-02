@if($rows->isEmpty())
    <div class="border rounded p-3 text-muted small">
        Nessun prodotto selezionato con i criteri correnti.
    </div>
@else
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div class="small text-muted">
            {{ $productsCount }} prodotti validi per la newsletter.
            @if($hasManualSkus)
                Gli SKU non validi restano visibili come controllo.
            @endif
        </div>
    </div>

    <div class="table-responsive border rounded">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 54px;">Img</th>
                    <th>SKU</th>
                    <th>Prodotto</th>
                    <th>Tipo</th>
                    <th>Padre</th>
                    <th>Prezzo newsletter</th>
                    <th>Esito</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr @class(['table-warning' => $row['status'] !== 'ok'])>
                        <td>
                            @if($row['image_url'])
                                <img src="{{ $row['image_url'] }}" alt="" class="rounded border" style="width: 42px; height: 42px; object-fit: cover;">
                            @else
                                <div class="rounded border bg-light" style="width: 42px; height: 42px;"></div>
                            @endif
                        </td>
                        <td class="font-monospace">
                            @if($row['admin_url'])
                                <a href="{{ $row['admin_url'] }}" target="_blank" rel="noopener">{{ $row['sku'] }}</a>
                            @else
                                {{ $row['sku'] }}
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $row['name'] ?: '-' }}</div>
                            @if($row['message'])
                                <div class="small text-muted">{{ $row['message'] }}</div>
                            @endif
                        </td>
                        <td>{{ $row['type'] ?: '-' }}</td>
                        <td class="font-monospace">{{ $row['parent_code'] ?: '-' }}</td>
                        <td>
                            @if($row['price_label'])
                                <span class="fw-semibold">{{ $row['price_label'] }}</span>
                                <span @class([
                                    'badge ms-1',
                                    'bg-success' => $row['price_source'] === 'listino',
                                    'bg-warning text-dark' => $row['price_source'] === 'public_price',
                                    'bg-secondary' => ! in_array($row['price_source'], ['listino', 'public_price'], true),
                                ])>
                                    {{ $row['price_source'] === 'listino' ? 'listino' : 'pubblico' }}
                                </span>
                            @else
                                <span class="text-danger small">Prezzo non trovato</span>
                            @endif
                        </td>
                        <td>
                            @if($row['status'] === 'ok')
                                <span class="badge bg-success">OK</span>
                            @else
                                <span class="badge bg-warning text-dark">Non incluso</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
