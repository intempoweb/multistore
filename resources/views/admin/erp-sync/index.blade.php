@extends('layouts.admin')

@section('title', 'ERP Sync')
@section('breadcrumb', 'Dashboard / ERP Sync')

@section('content')
@php
    $result = $result ?? null;
    $recentRuns = $recentRuns ?? collect();

    $selectedCommand = old('command', 'customers');
    $oldDitte = old('ditte', '1,3');
    $oldSites = old('sites', '');
    $oldSince = old('since', now()->subDays(7)->format('Y-m-d'));
    $oldLimit = old('limit', '');
    $oldOrderId = old('order_id', '');
    $oldSku = old('sku', '');
    $oldListini = old('listini', '');
@endphp

<div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-start gap-3 mb-4">
    <div>
        <div class="text-muted small mb-1">Pannello operativo sincronizzazioni ERP</div>
        <h1 class="h3 mb-1">ERP Sync</h1>
        <div class="text-muted small">
            Esecuzione controllata dei comandi Artisan ERP direttamente dal backend admin.
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Dashboard
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success border-0 shadow-sm">
        {{ session('success') }}
    </div>
@endif

@if(session('erp_sync_result') && (($result['status'] ?? null) === 'queued'))
    <div class="alert alert-info border-0 shadow-sm">
        <div class="fw-semibold mb-1">Job ERP accodato correttamente.</div>
        <div class="small mb-1">Il comando verrà eseguito in background senza bloccare la dashboard.</div>
        @if(!empty($result['run_id']))
            <div class="small text-muted">Run ID: #{{ $result['run_id'] }}</div>
        @endif
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger border-0 shadow-sm">
        <div class="fw-semibold mb-1">Sono presenti errori nel form.</div>
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h2 class="h5 mb-1">Lancia sincronizzazione</h2>
                <div class="text-muted small">
                    Seleziona il comando ERP, imposta i parametri e avvia dry run o sync reale.
                </div>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('admin.erp-sync.run') }}" class="row g-3">
                    @csrf

                    <div class="col-12">
                        <label class="form-label">Comando</label>
                        <select name="command" class="form-select">
                            <option value="products" @selected($selectedCommand === 'products')>Prodotti</option>
                            <option value="attributes" @selected($selectedCommand === 'attributes')>Attributi</option>
                            <option value="product_attribute_values" @selected($selectedCommand === 'product_attribute_values')>Valori attributo prodotto</option>
                            <option value="product_comparisons" @selected($selectedCommand === 'product_comparisons')>Articoli comparativi</option>
                            <option value="group_descriptions" @selected($selectedCommand === 'group_descriptions')>Descrizioni gruppi</option>
                            <option value="stock" @selected($selectedCommand === 'stock')>Stock</option>
                            <option value="customers" @selected($selectedCommand === 'customers')>Clienti</option>
                            <option value="customer_listini" @selected($selectedCommand === 'customer_listini')>Associazioni cliente/listino</option>
                            <option value="public_prices" @selected($selectedCommand === 'public_prices')>Prezzi pubblici</option>
                            <option value="price_tiers" @selected($selectedCommand === 'price_tiers')>Price tiers</option>
                            <option value="media" @selected($selectedCommand === 'media')>Media</option>
                            <option value="export_orders" @selected($selectedCommand === 'export_orders')>Export ordini ERP</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Ditte</label>
                        <input
                            type="text"
                            name="ditte"
                            class="form-control"
                            value="{{ $oldDitte }}"
                            placeholder="Es. 1,3"
                        >
                        <div class="form-text">Valori separati da virgola.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Sites</label>
                        <input
                            type="text"
                            name="sites"
                            class="form-control"
                            value="{{ $oldSites }}"
                            placeholder="Es. 1,5"
                        >
                        <div class="form-text">Opzionale, separati da virgola.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Since</label>
                        <input
                            type="date"
                            name="since"
                            class="form-control"
                            value="{{ $oldSince }}"
                        >
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Limit</label>
                        <input
                            type="number"
                            min="1"
                            name="limit"
                            class="form-control"
                            value="{{ $oldLimit }}"
                            placeholder="Es. 100"
                        >
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Order ID</label>
                        <input
                            type="number"
                            min="1"
                            name="order_id"
                            class="form-control"
                            value="{{ $oldOrderId }}"
                            placeholder="Es. 1234"
                        >
                        <div class="form-text">Usato da export ordini ERP per esportare un singolo ordine.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">SKU</label>
                        <input
                            type="text"
                            name="sku"
                            class="form-control"
                            value="{{ $oldSku }}"
                            placeholder="Es. 9239DRG31"
                        >
                        <div class="form-text">Usato da prezzi pubblici e price tiers.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Listini</label>
                        <input
                            type="text"
                            name="listini"
                            class="form-control"
                            value="{{ $oldListini }}"
                            placeholder="Es. 34,35"
                        >
                        <div class="form-text">Usato da price tiers.</div>
                    </div>

                    <div class="col-12">
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        value="1"
                                        id="dry"
                                        name="dry"
                                        @checked(old('dry'))
                                    >
                                    <label class="form-check-label" for="dry">
                                        Dry run
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        value="1"
                                        id="copy"
                                        name="copy"
                                        @checked(old('copy'))
                                    >
                                    <label class="form-check-label" for="copy">
                                        Copy files
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        value="1"
                                        id="force"
                                        name="force"
                                        @checked(old('force'))
                                    >
                                    <label class="form-check-label" for="force">
                                        Force overwrite
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        value="1"
                                        id="keep_old"
                                        name="keep_old"
                                        @checked(old('keep_old'))
                                    >
                                    <label class="form-check-label" for="keep_old">
                                        Keep old values
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-text mt-2">
                            <strong>Nota:</strong> alcune opzioni valgono solo per specifici comandi.
                            <code>copy</code> e <code>force</code> per media,
                            <code>keep old</code> per product attribute values,
                            <code>sku</code> per prezzi pubblici e price tiers,
                            <code>listini</code> per price tiers,
                            <code>limit</code> e <code>order_id</code> per export ordini ERP.
                        </div>
                    </div>

                    <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-play me-1"></i>
                            Lancia comando
                        </button>

                        <a href="{{ route('admin.erp-sync.index') }}" class="btn btn-outline-secondary">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h2 class="h5 mb-1">Output console</h2>
                <div class="text-muted small">
                    Risultato dell’ultima esecuzione oppure stato dell’ultimo job ERP lanciato dal pannello admin.
                </div>
            </div>

            <div class="card-body">
                @if($result)
                    <div class="mb-3">
                        <div class="small text-muted mb-1">Comando eseguito</div>
                        <div class="fw-semibold">{{ $result['command'] ?? '-' }}</div>
                    </div>

                    <div class="mb-3">
                        <div class="small text-muted mb-1">Stato</div>
                        <div>
                            @php $status = $result['status'] ?? null; @endphp
                            @if($status === 'success')
                                <span class="badge text-bg-success">Completato</span>
                            @elseif($status === 'queued')
                                <span class="badge text-bg-info">In coda</span>
                            @elseif($status === 'running')
                                <span class="badge text-bg-primary">In esecuzione</span>
                            @elseif($status === 'error' || $status === 'failed')
                                <span class="badge text-bg-danger">Errore</span>
                            @else
                                <span class="badge text-bg-secondary">-</span>
                            @endif

                            @if(!empty($result['run_id']))
                                <span class="ms-2 small text-muted">Run ID: #{{ $result['run_id'] }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="small text-muted mb-1">Parametri</div>
                        <div class="border rounded-3 bg-light p-3 small">
                            <pre class="mb-0" style="white-space: pre-wrap;">{{ json_encode($result['params'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    </div>

                    <div>
                        <div class="small text-muted mb-1">Output</div>
                        <div class="border rounded-3 bg-dark text-light p-3">
                            <pre class="mb-0 small" style="white-space: pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">{{ $result['output'] ?? 'Nessun output disponibile.' }}</pre>
                        </div>
                        @if(!empty($result['run_id']))
                            <div class="form-text mt-2">
                                Aggiorna la pagina per vedere lo stato aggiornato del job in background nella tabella delle ultime esecuzioni.
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-muted">
                        Nessun comando eseguito da questa sessione. Usa il form a sinistra per avviare una sincronizzazione ERP.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-0 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <div>
            <h2 class="h5 mb-1">Ultime esecuzioni ERP</h2>
            <div class="text-muted small">Storico dei job e dei comandi lanciati dalla dashboard admin.</div>
        </div>

        <a href="{{ route('admin.erp-sync.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-rotate-right me-1"></i>
            Aggiorna stato
        </a>
    </div>

    <div class="card-body">
        @if($recentRuns->isEmpty())
            <div class="text-muted">Nessuna esecuzione registrata al momento.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Comando</th>
                            <th>Stato</th>
                            <th>Avvio</th>
                            <th>Fine</th>
                            <th>Durata</th>
                            <th>Errore</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($recentRuns as $run)
                            @php
                                $isZombie = $run->status === 'running'
                                    && $run->started_at
                                    && $run->started_at->lt(now()->subMinutes(30));

                                $duration = null;

                                if ($run->started_at && $run->finished_at) {
                                    $duration = $run->started_at->diffForHumans($run->finished_at, true);
                                } elseif ($run->started_at && $run->status === 'running') {
                                    $duration = $run->started_at->diffForHumans(now(), true);
                                }
                            @endphp

                            <tr @class(['table-warning' => $isZombie])>
                                <td class="fw-semibold">#{{ $run->id }}</td>

                                <td>
                                    <div class="fw-semibold">{{ $run->command_name }}</div>

                                    @if(!empty($run->command_key))
                                        <div class="small text-muted">{{ $run->command_key }}</div>
                                    @endif

                                    @if(!empty($run->params_json))
                                        <details class="small mt-1">
                                            <summary class="text-muted">Parametri</summary>
                                            <pre class="mb-0 mt-2 bg-light border rounded p-2">{{ json_encode($run->params_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </details>
                                    @endif
                                </td>

                                <td>
                                    @if($isZombie)
                                        <span class="badge text-bg-warning">Bloccato?</span>
                                    @elseif(in_array($run->status, ['success', 'completed'], true))
                                        <span class="badge text-bg-success">Completato</span>
                                    @elseif($run->status === 'queued')
                                        <span class="badge text-bg-info">In coda</span>
                                    @elseif($run->status === 'running')
                                        <span class="badge text-bg-primary">In esecuzione</span>
                                    @elseif($run->status === 'failed')
                                        <span class="badge text-bg-danger">Fallito</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ $run->status ?? '-' }}</span>
                                    @endif
                                </td>

                                <td class="small text-muted">
                                    {{ $run->started_at?->format('d/m/Y H:i:s') ?? '-' }}
                                </td>

                                <td class="small text-muted">
                                    {{ $run->finished_at?->format('d/m/Y H:i:s') ?? '-' }}
                                </td>

                                <td class="small text-muted">
                                    {{ $duration ?? '-' }}
                                </td>

                                <td class="small">
                                    @if($run->error_message)
                                        <div class="text-danger" style="max-width: 420px; white-space: normal;">
                                            {{ $run->error_message }}
                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>

                            @if($run->output)
                                <tr>
                                    <td colspan="7" class="bg-light">
                                        <div class="small text-muted mb-1">Output</div>
                                        <pre class="mb-0 small" style="white-space: pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">{{ $run->output }}</pre>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-0">
        <h2 class="h5 mb-1">Riferimento rapido</h2>
        <div class="text-muted small">
            Mappa logica tra selettore UI e comandi Artisan disponibili.
        </div>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Voce pannello</th>
                        <th>Comando artisan</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-semibold">Prodotti</td>
                        <td><code>erp:sync-products</code></td>
                        <td class="text-muted small">Supporta ditte, sites, since, limit, dry.</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Attributi</td>
                        <td><code>erp:sync-attributes</code></td>
                        <td class="text-muted small">Tipicamente globale, usa soprattutto dry.</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Valori attributo prodotto</td>
                        <td><code>erp:sync-product-attribute-values</code></td>
                        <td class="text-muted small">Supporta keep old.</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Articoli comparativi</td>
                        <td><code>erp:sync-product-comparisons</code></td>
                        <td class="text-muted small">Supporta ditte, sites, since, limit, dry.</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Descrizioni gruppi</td>
                        <td><code>erp:sync-group-descriptions</code></td>
                        <td class="text-muted small">Usa ditte e sites.</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Stock</td>
                        <td><code>erp:sync-stock</code></td>
                        <td class="text-muted small">Supporta since e limit.</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Clienti</td>
                        <td><code>erp:sync-customers</code></td>
                        <td class="text-muted small">Supporta ditte, since, dry. Da dashboard viene lanciato in background.</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Associazioni cliente/listino</td>
                        <td><code>erp:sync-customer-listini</code></td>
                        <td class="text-muted small">Supporta ditte, since, dry.</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Prezzi pubblici</td>
                        <td><code>erp:sync-public-prices</code></td>
                        <td class="text-muted small">Supporta ditta, sku, dry.</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Price tiers</td>
                        <td><code>erp:sync-price-tiers</code></td>
                        <td class="text-muted small">Supporta ditte, sku, listini, since, dry.</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Media</td>
                        <td><code>erp:sync-media</code></td>
                        <td class="text-muted small">Supporta copy e force.</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Export ordini ERP</td>
                        <td><code>erp:export-orders</code></td>
                        <td class="text-muted small">Esporta ordini B2B e ordini B2C con fattura richiesta. Supporta limit e order_id.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection