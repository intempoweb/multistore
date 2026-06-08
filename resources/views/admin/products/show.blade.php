@extends('layouts.admin')

@section('title', 'Prodotto')
@section('breadcrumb', 'Catalogo / Prodotto')

@section('content')
@php
    $locale = app()->getLocale();

    $translation = method_exists($product, 'translationOrFallback')
        ? $product->translationOrFallback($locale)
        : null;

    $productName = trim((string) ($translation?->name ?? ''));
    if ($productName === '') {
        $productName = $product->sku;
    }

    $mainImage = method_exists($product, 'mainImage') ? $product->mainImage() : null;
    $galleryImages = method_exists($product, 'galleryImages') ? $product->galleryImages()->get() : collect();

    $imageUrl = function ($media) {
        if (!$media) {
            return null;
        }

        return $media->url
            ?? $media->path
            ?? $media->file_url
            ?? $media->original_url
            ?? null;
    };

    $mainImageUrl = $imageUrl($mainImage);

    if (!$mainImageUrl && $galleryImages->isNotEmpty()) {
        $mainImageUrl = $imageUrl($galleryImages->first());
    }

    $galleryUrls = $galleryImages
        ->map(fn ($media) => $imageUrl($media))
        ->filter()
        ->values();

    $familyLabel = trim((string) ($product->fam_description ?? ''));
    $subfamilyLabel = trim((string) ($product->sfam_description ?? ''));
    $groupLabel = trim((string) ($product->gruppo_description ?? ''));
    $subgroupLabel = trim((string) ($product->sgruppo_description ?? ''));

    if ($familyLabel === '') {
        $familyLabel = trim((string) ($product->fam_99 ?? ''));
    }

    if ($subfamilyLabel === '') {
        $subfamilyLabel = trim((string) ($product->sfam_99 ?? ''));
    }

    if ($groupLabel === '') {
        $groupLabel = trim((string) ($product->gruppo_99 ?? ''));
    }

    if ($subgroupLabel === '') {
        $subgroupLabel = trim((string) ($product->sgruppo_99 ?? ''));
    }

    $familyLabel = $familyLabel !== '' ? $familyLabel : null;
    $subfamilyLabel = $subfamilyLabel !== '' ? $subfamilyLabel : null;
    $groupLabel = $groupLabel !== '' ? $groupLabel : null;
    $subgroupLabel = $subgroupLabel !== '' ? $subgroupLabel : null;

    $categoryPath = trim((string) ($product->category_path_description ?? ''));
    if ($categoryPath === '') {
        $categoryPath = collect([
            $familyLabel,
            $subfamilyLabel,
            $groupLabel,
            $subgroupLabel,
        ])->filter()->implode(' / ');
    }

    if ($categoryPath === '') {
        $categoryPath = '—';
    }

    $publicPriceLabel = $product->public_price !== null
        ? number_format((float) $product->public_price, 2, ',', '.') . ' €'
        : 'N/D';

    $tierRowsCount = (int) ($product->tier_rows_count ?? 0);
    $listiniCount = (int) ($product->listini_count ?? 0);
    $customerCount = (int) ($product->customer_count ?? 0);

    $minPriceNetLabel = $product->min_price_net !== null
        ? number_format((float) $product->min_price_net, 3, ',', '.') . ' €'
        : 'N/D';

    $maxPriceNetLabel = $product->max_price_net !== null
        ? number_format((float) $product->max_price_net, 3, ',', '.') . ' €'
        : 'N/D';

    $stockLabel = $product->stock_qty ?? '-';
    $minOrderQtyLabel = $product->min_order_qty ?? '-';
    $unitLabel = $product->unit ?: '-';
    $barcodeLabel = $product->barcode ?: '-';
    $brandLabel = $product->marca_mg64 ?: '-';
    $descriptionHtml = $translation ? nl2br(e($translation->description ?: '-')) : '-';

    $productAttributePresentation = collect($productAttributePresentation ?? $product->product_attribute_presentation ?? []);
    $grammaturaValue = $product->grammatura_value ?? null;

    $comparisonSourceLabels = [
        'buffetti' => 'Buffetti',
        'flex' => 'Flex',
        'dataufficio' => 'Data Ufficio',
        'semper' => 'Semper',
        'edpro' => 'Edipro',
        'cierre' => 'Cierre',
        'screamo' => 'Screamo',
        'biemme' => 'Biemme',
        'comp01' => 'Comparativo 01',
        'comp02' => 'Comparativo 02',
    ];

    $comparisonRowsSource = isset($productComparisons) && collect($productComparisons)->isNotEmpty()
        ? collect($productComparisons)
        : collect($product->comparisons ?? []);

    $comparisons = $comparisonRowsSource
        ->map(function ($comparison) use ($comparisonSourceLabels) {
            $source = is_array($comparison)
                ? ($comparison['source'] ?? null)
                : ($comparison->source ?? null);

            $comparisonSku = is_array($comparison)
                ? ($comparison['comparison_sku'] ?? null)
                : ($comparison->comparison_sku ?? null);

            $comparisonSku = trim((string) ($comparisonSku ?? ''));

            if ($comparisonSku === '') {
                return null;
            }

            return (object) [
                'source' => $source,
                'source_label' => $comparisonSourceLabels[$source ?? ''] ?? strtoupper((string) ($source ?? '-')),
                'comparison_sku' => $comparisonSku,
            ];
        })
        ->filter()
        ->unique(fn ($comparison) => ($comparison->source ?? '') . '|' . ($comparison->comparison_sku ?? ''))
        ->values();

    $comparisonsCount = $comparisons->count();
    $comparisonsLabel = $comparisonsCount > 0
        ? number_format($comparisonsCount, 0, ',', '.')
        : 'Nessuno';

    $detailFields = collect([
        ['label' => 'Barcode', 'value' => $barcodeLabel],
        ['label' => 'Articoli comparativi', 'value' => $comparisonsLabel],
        ['label' => 'Unità', 'value' => $unitLabel],
        ['label' => 'No backorder', 'value' => $product->no_backorder ? 'Sì' : 'No'],
        ['label' => 'Codgrupfis', 'value' => $product->codgrupfis_mg61 ?: '-'],
        ['label' => 'Grammatura', 'value' => $grammaturaValue ?: '-'],
        ['label' => 'Peso netto', 'value' => $product->peson_mg68 !== null ? number_format((float) $product->peson_mg68, 4, ',', '.') : '-'],
        ['label' => 'Peso lordo', 'value' => $product->pesol_mg68 !== null ? number_format((float) $product->pesol_mg68, 4, ',', '.') : '-'],
        ['label' => 'Peso calcolato', 'value' => $product->pesocalc !== null ? number_format((float) $product->pesocalc, 4, ',', '.') : '-'],
        ['label' => 'UM peso', 'value' => $product->umpeso_mg68 ?: '-'],
        ['label' => 'Pezzi confezione', 'value' => $product->pzconf_mg68 !== null ? number_format((float) $product->pzconf_mg68, 3, ',', '.') : '-'],
        ['label' => 'Massa netta', 'value' => $product->massanetta_mg98 !== null ? number_format((float) $product->massanetta_mg98, 6, ',', '.') : '-'],
        ['label' => 'Larghezza', 'value' => $product->largh_mg68 !== null ? number_format((float) $product->largh_mg68, 4, ',', '.') : '-'],
        ['label' => 'Altezza', 'value' => $product->altez_mg68 !== null ? number_format((float) $product->altez_mg68, 4, ',', '.') : '-'],
        ['label' => 'Profondità', 'value' => $product->prof_mg68 !== null ? number_format((float) $product->prof_mg68, 4, ',', '.') : '-'],
    ]);
@endphp

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
    <div>
        <div class="text-muted small mb-2">
            <a href="{{ route('admin.products.index') }}" class="text-decoration-none">Prodotti</a>
            <span class="mx-1">/</span>
            <span>{{ $product->sku }}</span>
        </div>

        <h1 class="h3 mb-1">{{ $productName }}</h1>

        <div class="d-flex flex-wrap gap-2 mb-2">
            <span class="badge {{ $product->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                {{ $product->is_active ? 'Attivo' : 'Disattivo' }}
            </span>
            <span class="badge text-bg-light border text-dark">{{ strtoupper((string) $product->type) }}</span>
            <span class="badge text-bg-light border text-dark">SKU: {{ $product->sku }}</span>
            <span class="badge text-bg-light border text-dark">Prezzo pubblico: {{ $publicPriceLabel }}</span>
        </div>

        <div class="text-muted small d-flex flex-wrap gap-2 align-items-center mb-2">
            <span><strong>{{ $store->name }}</strong></span>
            <span>•</span>
            <span>Ditta {{ $product->ditta_cg18 }}</span>
            <span>•</span>
            <span>Site {{ $product->site_type }}</span>
            @if($brandLabel !== '-')
                <span>•</span>
                <span>Marca {{ $brandLabel }}</span>
            @endif
        </div>

        <div class="small">
            <div class="text-muted mb-1">Categoria ERP</div>
            <div class="fw-semibold">{{ $categoryPath }}</div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.catalog.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-sitemap me-1"></i>
            Catalogo
        </a>
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-dark">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Torna alla lista
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Comparativi</div>
                <div class="fw-bold fs-5">{{ $comparisonsLabel }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Prezzo pubblico</div>
                <div class="fw-bold fs-5">{{ $publicPriceLabel }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Listini</div>
                <div class="fw-bold fs-5">{{ number_format($listiniCount, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Righe B2B</div>
                <div class="fw-bold fs-5">{{ number_format($tierRowsCount, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Clienti collegati</div>
                <div class="fw-bold fs-5">{{ number_format($customerCount, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Stock</div>
                <div class="fw-bold fs-5">{{ $stockLabel }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small mb-1">Min. ordine</div>
                <div class="fw-bold fs-5">{{ $minOrderQtyLabel }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center gap-2">
                <strong>Immagini</strong>
                @if($galleryUrls->isNotEmpty())
                    <span class="badge rounded-pill text-bg-light border">{{ number_format($galleryUrls->count(), 0, ',', '.') }}</span>
                @endif
            </div>
            <div class="card-body">
                @if($mainImageUrl)
                    <div class="border rounded-3 overflow-hidden bg-light mb-3 text-center p-3">
                        <img src="{{ $mainImageUrl }}" alt="{{ $productName }}" class="img-fluid" style="max-height: 320px; width: auto;">
                    </div>
                @else
                    <div class="border rounded-3 bg-light d-flex align-items-center justify-content-center text-muted" style="min-height: 220px;">
                        Nessuna immagine disponibile
                    </div>
                @endif

                @if($galleryUrls->isNotEmpty())
                    <div class="row g-2">
                        @foreach($galleryUrls->take(6) as $url)
                            <div class="col-4">
                                <div class="border rounded-3 overflow-hidden bg-light text-center p-2 h-100">
                                    <img src="{{ $url }}" alt="{{ $productName }}" class="img-fluid" style="max-height: 80px; width: auto;">
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <strong>Riepilogo rapido</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Nome prodotto</div>
                        <div class="fw-semibold">{{ $productName }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Tipo</div>
                        <div class="fw-semibold">{{ $product->type ?: '-' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Marca</div>
                        <div class="fw-semibold">{{ $brandLabel }}</div>
                    </div>

                    <div class="col-md-3">
                        <div class="text-muted small">Famiglia</div>
                        <div class="fw-semibold">{{ $familyLabel ?: '-' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Sottofamiglia</div>
                        <div class="fw-semibold">{{ $subfamilyLabel ?: '-' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Gruppo</div>
                        <div class="fw-semibold">{{ $groupLabel ?: '-' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Sottogruppo</div>
                        <div class="fw-semibold">{{ $subgroupLabel ?: '-' }}</div>
                    </div>

                    @foreach($detailFields as $field)
                        <div class="col-md-3">
                            <div class="text-muted small">{{ $field['label'] }}</div>
                            <div class="fw-semibold">{{ $field['value'] }}</div>
                        </div>
                    @endforeach

                    <div class="col-md-6">
                        <div class="text-muted small">Range prezzi B2B</div>
                        <div class="fw-semibold">{{ $minPriceNetLabel }} — {{ $maxPriceNetLabel }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">ERP data ultimo agg</div>
                        <div class="fw-semibold">{{ optional($product->erp_dataultimoagg)->format('d/m/Y') ?: '-' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">ERP lastchange</div>
                        <div class="fw-semibold">{{ optional($product->erp_lastchange)->format('d/m/Y H:i:s') ?: '-' }}</div>
                    </div>
                </div>

                @if($translation)
                    <hr class="my-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="text-muted small">Nome tradotto</div>
                            <div class="fw-semibold">{{ $translation->name ?: '-' }}</div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small">Descrizione</div>
                            <div class="small">{!! $descriptionHtml !!}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="accordion" id="productDetailAccordion">
    <div class="accordion-item border-0 shadow-sm mb-4 rounded-3 overflow-hidden" id="pricing-overview">
        <h2 class="accordion-header" id="headingPricingSummary">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePricingSummary" aria-expanded="true" aria-controls="collapsePricingSummary">
                <span class="fw-semibold">Prezzi B2B per listino</span>
            </button>
        </h2>
        <div id="collapsePricingSummary" class="accordion-collapse collapse show" aria-labelledby="headingPricingSummary" data-bs-parent="#productDetailAccordion">
            <div class="accordion-body p-0" id="customer-listini">
                @if($tierSummaryByListino->isEmpty())
                    <div class="p-4 text-muted">Nessun prezzo B2B disponibile per questo prodotto.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 120px;">Listino</th>
                                    <th style="width: 120px;">Righe</th>
                                    <th style="width: 180px;">Range prezzo</th>
                                    <th style="width: 120px;">Clienti</th>
                                    <th>Dettaglio</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tierSummaryByListino as $summary)
                                    @php
                                        $summaryMin = $summary->min_price_net !== null
                                            ? number_format((float) $summary->min_price_net, 3, ',', '.') . ' €'
                                            : 'N/D';

                                        $summaryMax = $summary->max_price_net !== null
                                            ? number_format((float) $summary->max_price_net, 3, ',', '.') . ' €'
                                            : 'N/D';
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $summary->listino_id }}</td>
                                        <td>{{ number_format($summary->rows_count, 0, ',', '.') }}</td>
                                        <td>{{ $summaryMin }} — {{ $summaryMax }}</td>
                                        <td>{{ number_format($summary->customers_count, 0, ',', '.') }}</td>
                                        <td class="text-muted small">Scaglioni prezzo e clienti assegnati al listino</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="accordion-item border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
        <h2 class="accordion-header" id="headingPriceTiers">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePriceTiers" aria-expanded="false" aria-controls="collapsePriceTiers">
                <span class="fw-semibold">Scaglioni prezzo B2B</span>
            </button>
        </h2>
        <div id="collapsePriceTiers" class="accordion-collapse collapse" aria-labelledby="headingPriceTiers" data-bs-parent="#productDetailAccordion">
            <div class="accordion-body p-0">
                @if($priceTiers->isEmpty())
                    <div class="p-4 text-muted">Nessuno scaglione prezzo disponibile.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Listino</th>
                                    <th>Da qty</th>
                                    <th>A qty</th>
                                    <th>Prezzo netto</th>
                                    <th>Sconti</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($priceTiers as $tier)
                                    <tr>
                                        <td class="fw-semibold">{{ $tier->listino_id }}</td>
                                        <td>{{ $tier->qty_from }}</td>
                                        <td>{{ $tier->qty_to }}</td>
                                        <td>{{ number_format((float) $tier->price_net, 3, ',', '.') }} €</td>
                                        <td class="text-muted small">
                                            {{ $tier->sc1 ?? '0.000' }} /
                                            {{ $tier->sc2 ?? '0.000' }} /
                                            {{ $tier->sc3 ?? '0.000' }} /
                                            {{ $tier->sc4 ?? '0.000' }} /
                                            {{ $tier->sc5 ?? '0.000' }} /
                                            {{ $tier->sc6 ?? '0.000' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="accordion-item border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
        <h2 class="accordion-header" id="headingCustomers">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCustomers" aria-expanded="false" aria-controls="collapseCustomers">
                <span class="fw-semibold">Clienti associati ai listini</span>
            </button>
        </h2>
        <div id="collapseCustomers" class="accordion-collapse collapse" aria-labelledby="headingCustomers" data-bs-parent="#productDetailAccordion">
            <div class="accordion-body p-0">
                @if($customerListinoAssignments->isEmpty())
                    <div class="p-4 text-muted">Nessun cliente collegato ai listini di questo prodotto.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Listino</th>
                                    <th>Tipo CF</th>
                                    <th>Cliente</th>
                                    <th>Ragione sociale</th>
                                    <th>Stato</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customerListinoAssignments as $assignment)
                                    <tr>
                                        <td class="fw-semibold">
                                            {{ $assignment->customer_listino_id ?? $assignment->listino_id ?? $assignment->codlistinoded ?? '-' }}
                                        </td>
                                        <td>{{ $assignment->tipocf_cg44 ?? '-' }}</td>
                                        <td>{{ $assignment->clifor_cg44 ?? '-' }}</td>
                                        <td>{{ $assignment->ragsoanag_cg16 ?: '-' }}</td>
                                        <td>
                                            @if($assignment->is_active)
                                                <span class="badge text-bg-success">Attivo</span>
                                            @else
                                                <span class="badge text-bg-secondary">Disattivo</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="accordion-item border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
        <h2 class="accordion-header" id="headingAttributes">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAttributes" aria-expanded="false" aria-controls="collapseAttributes">
                <span class="fw-semibold">Attributi</span>
            </button>
        </h2>
        <div id="collapseAttributes" class="accordion-collapse collapse" aria-labelledby="headingAttributes" data-bs-parent="#productDetailAccordion">
            <div class="accordion-body p-0">
                @if($productAttributePresentation->isEmpty())
                    <div class="p-4 text-muted">Nessun attributo associato.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Codice</th>
                                    <th>Attributo</th>
                                    <th>Valore</th>
                                    <th>Raw value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($productAttributePresentation as $attribute)
                                    <tr>
                                        <td class="fw-semibold">{{ $attribute['code'] ?? '-' }}</td>
                                        <td>{{ $attribute['label'] ?? '-' }}</td>
                                        <td>{{ $attribute['value'] ?? '-' }}</td>
                                        <td class="text-muted">{{ $attribute['raw_value'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="accordion-item border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
        <h2 class="accordion-header" id="headingRelations">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRelations" aria-expanded="false" aria-controls="collapseRelations">
                <span class="fw-semibold">Relazioni</span>
            </button>
        </h2>
        <div id="collapseRelations" class="accordion-collapse collapse" aria-labelledby="headingRelations" data-bs-parent="#productDetailAccordion">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Prodotto padre</div>
                        @if($parent)
                            <a href="{{ route('admin.products.show', $parent) }}" class="fw-semibold text-decoration-none">
                                {{ $parent->sku }}
                            </a>
                        @else
                            <div class="fw-semibold">-</div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Configurable meta</div>
                        <div class="fw-semibold">{{ $product->configurable?->parent_code ?? '-' }}</div>
                    </div>
                </div>

                <hr class="my-4">

                <div>
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <div class="text-muted small">Articoli comparativi ERP</div>
                            <div class="fw-semibold">{{ $comparisonsLabel }}</div>
                        </div>
                    </div>

                    @if($comparisons->isEmpty())
                        <div class="text-muted small">Nessun articolo comparativo associato a questo prodotto.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Comparativo</th>
                                        <th>Fonte</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($comparisons as $comparison)
                                        <tr>
                                            <td class="fw-semibold">{{ $comparison->comparison_sku }}</td>
                                            <td>{{ $comparison->source_label ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($children->isNotEmpty())
        <div class="accordion-item border-0 shadow-sm rounded-3 overflow-hidden">
            <h2 class="accordion-header" id="headingChildren">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseChildren" aria-expanded="false" aria-controls="collapseChildren">
                    <span class="fw-semibold">Varianti / figli</span>
                </button>
            </h2>
            <div id="collapseChildren" class="accordion-collapse collapse" aria-labelledby="headingChildren" data-bs-parent="#productDetailAccordion">
                <div class="accordion-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>SKU</th>
                                    <th>Nome</th>
                                    <th>Categoria</th>
                                    <th>Grammatura</th>
                                    <th>Peso netto</th>
                                    <th>Prezzo pubblico</th>
                                    <th>Prezzi B2B</th>
                                    <th>Comparativi</th>
                                    <th>Stato</th>
                                    <th class="text-end">Apri</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($children as $child)
                                    @php
                                        $childTranslation = method_exists($child, 'translationOrFallback')
                                            ? $child->translationOrFallback($locale)
                                            : null;

                                        $childName = trim((string) ($childTranslation?->name ?? ''));
                                        if ($childName === '') {
                                            $childName = $child->sku;
                                        }

                                        $childPublicPrice = $child->public_price !== null
                                            ? number_format((float) $child->public_price, 2, ',', '.') . ' €'
                                            : 'N/D';

                                        $childMinPrice = $child->min_price_net !== null
                                            ? number_format((float) $child->min_price_net, 2, ',', '.') . ' €'
                                            : 'N/D';

                                        $childMaxPrice = $child->max_price_net !== null
                                            ? number_format((float) $child->max_price_net, 2, ',', '.') . ' €'
                                            : 'N/D';

                                        $childCategoryPath = trim((string) ($child->category_path_description ?? ''));
                                        if ($childCategoryPath === '') {
                                            $childCategoryPath = collect([
                                                $child->fam_description ?: ($child->fam_99 ?? null),
                                                $child->sfam_description ?: ($child->sfam_99 ?? null),
                                                $child->gruppo_description ?: ($child->gruppo_99 ?? null),
                                                $child->sgruppo_description ?: ($child->sgruppo_99 ?? null),
                                            ])->filter()->implode(' / ');
                                        }

                                        if ($childCategoryPath === '') {
                                            $childCategoryPath = '—';
                                        }

                                        $childGrammatura = $child->grammatura_value ?: '—';
                                        $childPesoNetto = $child->peson_mg68 !== null
                                            ? number_format((float) $child->peson_mg68, 4, ',', '.')
                                            : '—';

                                        $childComparisonsCount = collect($child->comparisons ?? [])
                                            ->filter(fn ($comparison) => trim((string) ($comparison->comparison_sku ?? '')) !== '')
                                            ->unique(fn ($comparison) => ($comparison->source ?? '') . '|' . ($comparison->comparison_sku ?? ''))
                                            ->count();
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $child->sku }}</td>
                                        <td>{{ $childName }}</td>
                                        <td class="small">{{ $childCategoryPath }}</td>
                                        <td>{{ $childGrammatura }}</td>
                                        <td>{{ $childPesoNetto }}</td>
                                        <td>{{ $childPublicPrice }}</td>
                                        <td>
                                            @if((int) ($child->tier_rows_count ?? 0) > 0)
                                                <div class="fw-semibold">{{ number_format((int) ($child->tier_rows_count ?? 0), 0, ',', '.') }} righe</div>
                                                <div class="text-muted small">{{ $childMinPrice }} — {{ $childMaxPrice }}</div>
                                            @else
                                                <span class="text-muted">Nessun prezzo B2B</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($childComparisonsCount > 0)
                                                <span class="badge text-bg-light border text-dark">
                                                    {{ number_format($childComparisonsCount, 0, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $child->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                                {{ $child->is_active ? 'Attivo' : 'Disattivo' }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.products.show', $child) }}" class="btn btn-sm btn-outline-primary">
                                                Apri
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection