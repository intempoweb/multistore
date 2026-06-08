@extends('layouts.admin')

@section('title', 'Prodotti')
@section('breadcrumb', 'Catalogo / Prodotti')

@section('content')
<div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
    <div>
        <div class="text-muted small mb-1">Catalogo prodotti store corrente</div>
        <h1 class="h3 mb-1">Prodotti</h1>
        <div class="text-muted small d-flex flex-wrap gap-2 align-items-center">
            <span><strong>{{ $store->name }}</strong></span>
            <span>•</span>
            <span>Ditta {{ $store->ditta_cg18 }}</span>
            <span>•</span>
            <span>Site {{ $store->erp_site_code }}</span>
            <span>•</span>
            <span>{{ number_format($products->total(), 0, ',', '.') }} risultati</span>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.catalog.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-sitemap me-1"></i>
            Catalogo
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0">
        <div>
            <h2 class="h5 mb-1">Filtri</h2>
            <div class="text-muted small">
                Ricerca prodotti nello store selezionato.
            </div>
        </div>
    </div>

    <div class="card-body">
        <form method="GET" action="{{ route('admin.products.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-xl-4">
                <label class="form-label">SKU o codice</label>
                <input
                    type="text"
                    name="sku"
                    class="form-control"
                    value="{{ $filters['sku'] }}"
                    placeholder="Es. ABC123"
                >
            </div>

            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label">Tipo</label>
                <select name="type" class="form-select">
                    <option value="">Tutti</option>
                    <option value="simple" @selected($filters['type'] === 'simple')>Semplice</option>
                    <option value="configurable" @selected($filters['type'] === 'configurable')>Configurabile</option>
                </select>
            </div>

            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label">Stato</label>
                <select name="is_active" class="form-select">
                    <option value="" @selected($filters['is_active'] === '')>Solo attivi</option>
                    <option value="1" @selected($filters['is_active'] === '1')>Attivi</option>
                    <option value="0" @selected($filters['is_active'] === '0')>Disattivi</option>
                    <option value="all" @selected($filters['is_active'] === 'all')>Tutti</option>
                </select>
            </div>

            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label">Prezzi</label>
                <select name="has_price" class="form-select">
                    <option value="" @selected($filters['has_price'] === '')>Tutti</option>
                    <option value="1" @selected($filters['has_price'] === '1')>Con almeno un prezzo</option>
                    <option value="0" @selected($filters['has_price'] === '0')>Senza prezzi</option>
                </select>
            </div>

            <div class="col-6 col-md-3 col-xl-1">
                <label class="form-label">Fam.</label>
                <input type="text" name="fam_99" class="form-control" value="{{ $filters['fam_99'] }}">
            </div>

            <div class="col-6 col-md-3 col-xl-1">
                <label class="form-label">S.Fam.</label>
                <input type="text" name="sfam_99" class="form-control" value="{{ $filters['sfam_99'] }}">
            </div>

            <div class="col-6 col-md-3 col-xl-1">
                <label class="form-label">Gruppo</label>
                <input type="text" name="gruppo_99" class="form-control" value="{{ $filters['gruppo_99'] }}">
            </div>

            <div class="col-6 col-md-3 col-xl-1">
                <label class="form-label">S.Gruppo</label>
                <input type="text" name="sgruppo_99" class="form-control" value="{{ $filters['sgruppo_99'] }}">
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 pt-1">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-magnifying-glass me-1"></i>
                    Filtra
                </button>

                <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
        <div>
            <h2 class="h5 mb-1">Elenco prodotti</h2>
            <div class="text-muted small">
                Vista compatta e leggibile con riepilogo commerciale rapido.
            </div>
        </div>

        <span class="badge rounded-pill text-bg-light border px-3 py-2">
            {{ number_format($products->total(), 0, ',', '.') }} elementi
        </span>
    </div>

    <div class="card-body">
        @if($products->isEmpty())
            <div class="text-muted">Nessun prodotto trovato per i filtri selezionati.</div>
        @else
            <div class="row g-3">
                @foreach($products as $product)
                    @php
                        $translation = method_exists($product, 'translationOrFallback')
                            ? $product->translationOrFallback(app()->getLocale())
                            : null;

                        $productName = trim((string) ($translation?->name ?? ''));
                        if ($productName === '') {
                            $productName = $product->sku;
                        }

                        $familyLabel = trim((string) ($product->fam_description ?? ''));
                        $subfamilyLabel = trim((string) ($product->sfam_description ?? ''));
                        $groupLabel = trim((string) ($product->gruppo_description ?? ''));
                        $subgroupLabel = trim((string) ($product->sgruppo_description ?? ''));

                        $categoryPath = collect([
                            $familyLabel !== '' ? $familyLabel : ($product->fam_99 ?? null),
                            $subfamilyLabel !== '' ? $subfamilyLabel : ($product->sfam_99 ?? null),
                            $groupLabel !== '' ? $groupLabel : ($product->gruppo_99 ?? null),
                            $subgroupLabel !== '' ? $subgroupLabel : ($product->sgruppo_99 ?? null),
                        ])->filter(fn ($value) => trim((string) $value) !== '')->implode(' / ');

                        if ($categoryPath === '') {
                            $categoryPath = '—';
                        }

                        $mainImage = method_exists($product, 'mainImage') ? $product->mainImage() : null;
                        $imageUrl = $mainImage?->url ?? $mainImage?->path ?? null;

                        $publicPriceLabel = $product->public_price !== null
                            ? number_format((float) $product->public_price, 2, ',', '.') . ' €'
                            : 'N/D';

                        $tierRowsCount = (int) ($product->tier_rows_count ?? 0);
                        $listiniCount = (int) ($product->listini_count ?? 0);
                        $customerCount = (int) ($product->customer_count ?? 0);

                        $minPriceNet = $product->min_price_net !== null
                            ? number_format((float) $product->min_price_net, 3, ',', '.') . ' €'
                            : null;

                        $maxPriceNet = $product->max_price_net !== null
                            ? number_format((float) $product->max_price_net, 3, ',', '.') . ' €'
                            : null;

                        $typeLabel = $product->type === 'configurable' ? 'Configurabile' : 'Semplice';
                        $typeBadge = $product->type === 'configurable'
                            ? 'text-bg-dark'
                            : 'text-bg-light border text-dark';

                        $grammaturaValue = trim((string) ($product->grammatura_value ?? ''));
                        $pesoNettoValue = $product->peson_mg68 !== null && $product->peson_mg68 !== ''
                            ? number_format((float) $product->peson_mg68, 4, ',', '.')
                            : null;
                        $pesoCalcValue = $product->pesocalc !== null && $product->pesocalc !== ''
                            ? number_format((float) $product->pesocalc, 4, ',', '.')
                            : null;
                        $pesoUnit = trim((string) ($product->umpeso_mg68 ?? ''));
                    @endphp

                    <div class="col-12">
                        <div class="border rounded-3 p-3 bg-white h-100">
                            <div class="row g-3 align-items-start">
                                <div class="col-12 col-lg-5">
                                    <div class="d-flex gap-3">
                                        <div class="border rounded-3 bg-light d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0" style="width: 72px; height: 72px;">
                                            @if($imageUrl)
                                                <img
                                                    src="{{ $imageUrl }}"
                                                    alt="{{ $productName }}"
                                                    class="img-fluid"
                                                    style="max-width: 100%; max-height: 100%; object-fit: cover;"
                                                >
                                            @else
                                                <i class="fa-regular fa-image text-muted"></i>
                                            @endif
                                        </div>

                                        <div class="min-w-0 flex-grow-1">
                                            <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                                                <h3 class="h6 mb-0 fw-semibold">{{ $productName }}</h3>

                                                <span class="badge {{ $typeBadge }}">
                                                    {{ $typeLabel }}
                                                </span>

                                                @if($product->is_active)
                                                    <span class="badge text-bg-success">Attivo</span>
                                                @else
                                                    <span class="badge text-bg-secondary">Disattivo</span>
                                                @endif
                                            </div>

                                            <div class="text-muted small">SKU: {{ $product->sku }}</div>

                                            @if(!empty($product->barcode))
                                                <div class="text-muted small">Barcode: {{ $product->barcode }}</div>
                                            @endif

                                            @if($grammaturaValue !== '')
                                                <div class="text-muted small">Grammatura: {{ $grammaturaValue }}</div>
                                            @endif

                                            @if($pesoNettoValue !== null)
                                                <div class="text-muted small">
                                                    Peso netto: {{ $pesoNettoValue }}{{ $pesoUnit !== '' ? ' ' . $pesoUnit : '' }}
                                                </div>
                                            @elseif($pesoCalcValue !== null)
                                                <div class="text-muted small">
                                                    Peso calcolato: {{ $pesoCalcValue }}{{ $pesoUnit !== '' ? ' ' . $pesoUnit : '' }}
                                                </div>
                                            @endif

                                            <div class="small mt-2">
                                                <div class="text-muted mb-1">Categoria ERP</div>
                                                <div class="fw-semibold">{{ $categoryPath }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6 col-lg-2">
                                    <div class="small text-muted mb-1">Prezzo pubblico</div>
                                    <div class="fw-semibold fs-6">{{ $publicPriceLabel }}</div>
                                    <div class="text-muted small mt-1">
                                        {{ $product->type === 'configurable' ? 'Configurabile: nessun prezzo diretto' : 'Prezzo store' }}
                                    </div>
                                </div>

                                <div class="col-12 col-md-6 col-lg-3">
                                    <div class="small text-muted mb-1">Prezzi B2B</div>

                                    @if($tierRowsCount > 0)
                                        <div class="fw-semibold">{{ number_format($tierRowsCount, 0, ',', '.') }} righe prezzo</div>
                                        <div class="text-muted small">
                                            {{ number_format($listiniCount, 0, ',', '.') }} listini
                                            <span class="mx-1">•</span>
                                            {{ number_format($customerCount, 0, ',', '.') }} clienti
                                        </div>
                                        <div class="text-muted small mt-1">
                                            {{ $minPriceNet ?? 'N/D' }}
                                            <span class="mx-1">—</span>
                                            {{ $maxPriceNet ?? 'N/D' }}
                                        </div>
                                    @else
                                        <div class="fw-semibold text-danger">Nessun prezzo B2B</div>
                                        <div class="text-muted small">Apri dettaglio prodotto</div>
                                    @endif
                                </div>

                                <div class="col-12 col-lg-2">
                                    <div class="d-flex flex-lg-column justify-content-between align-items-stretch gap-2 h-100">
                                        <a href="{{ route('admin.products.show', $product) }}#pricing-overview" class="btn btn-outline-secondary btn-sm">
                                            Prezzi
                                        </a>

                                        <a href="{{ route('admin.products.show', $product) }}" class="btn btn-primary btn-sm">
                                            Apri
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if($products->hasPages())
        <div class="card-footer bg-white">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="text-muted small">
                    Mostrati {{ $products->firstItem() ?: 0 }}–{{ $products->lastItem() ?: 0 }} di {{ number_format($products->total(), 0, ',', '.') }} prodotti
                </div>
                <div>
                    {{ $products->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    @endif
</div>
@endsection