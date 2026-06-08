@extends('layouts.admin')

@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
@php
    $priceMin = $stats['price_min'] ?? null;
    $priceMax = $stats['price_max'] ?? null;
@endphp

<div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-start gap-3 mb-4">
    <div>
        <div class="text-muted small mb-1">Pannello di controllo multistore</div>
        <h1 class="h3 mb-1">Dashboard amministrativa</h1>
        <div class="text-muted small">
            <strong>{{ $store->name }}</strong>
            <span class="mx-1">•</span>
            Ditta {{ $store->ditta_cg18 }}
            <span class="mx-1">•</span>
            Site {{ $store->erp_site_code }}
            <span class="mx-1">•</span>
            {{ $store->domain }}
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.catalog.index') }}" class="btn btn-outline-primary">
            <i class="fa-solid fa-sitemap me-1"></i>
            Catalogo
        </a>

        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-box me-1"></i>
            Prodotti
        </a>

        @if(Route::has('admin.attributes.index'))
            <a href="{{ route('admin.attributes.index') }}" class="btn btn-outline-warning">
                <i class="fa-solid fa-tags me-1"></i>
                Attributi
            </a>
        @endif

        @if(Route::has('admin.customers.index'))
            <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-dark">
                <i class="fa-solid fa-users me-1"></i>
                Clienti
            </a>
        @endif
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-1">Prodotti</div>
                <div class="fs-3 fw-bold">{{ number_format($stats['products_total'] ?? 0, 0, ',', '.') }}</div>
                <div class="text-muted small mt-2">Totale prodotti nel contesto store selezionato</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-1">Attivi</div>
                <div class="fs-3 fw-bold">{{ number_format($stats['products_active'] ?? 0, 0, ',', '.') }}</div>
                <div class="text-muted small mt-2">
                    Semplici: {{ number_format($stats['products_simple'] ?? 0, 0, ',', '.') }}
                    <span class="mx-1">•</span>
                    Configurabili: {{ number_format($stats['products_configurable'] ?? 0, 0, ',', '.') }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-1">Catalogo ERP</div>
                <div class="fs-3 fw-bold">{{ number_format($stats['families_total'] ?? 0, 0, ',', '.') }}</div>
                <div class="text-muted small mt-2">
                    Famiglie
                    <span class="mx-1">•</span>
                    Sottofamiglie: {{ number_format($stats['subfamilies_total'] ?? 0, 0, ',', '.') }}
                    <span class="mx-1">•</span>
                    Gruppi: {{ number_format($stats['groups_total'] ?? 0, 0, ',', '.') }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-1">Prezzi</div>
                <div class="fs-5 fw-bold">
                    @if($priceMin !== null || $priceMax !== null)
                        {{ $priceMin !== null ? number_format((float) $priceMin, 2, ',', '.') . ' €' : 'N/D' }}
                        <span class="mx-1">—</span>
                        {{ $priceMax !== null ? number_format((float) $priceMax, 2, ',', '.') . ' €' : 'N/D' }}
                    @else
                        N/D
                    @endif
                </div>
                <div class="text-muted small mt-2">
                    Con prezzo: {{ number_format($stats['products_with_price'] ?? 0, 0, ',', '.') }}
                    <span class="mx-1">•</span>
                    Senza prezzo: {{ number_format($stats['products_without_price'] ?? 0, 0, ',', '.') }}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <div>
                    <h2 class="h5 mb-1">Accesso rapido</h2>
                    <div class="text-muted small">Navigazione rapida delle aree principali dello store attivo</div>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <a href="{{ route('admin.catalog.index') }}" class="card border h-100 text-decoration-none text-reset">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center"
                                     style="width: 48px; height: 48px;">
                                    <i class="fa-solid fa-sitemap"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">Catalogo ERP</div>
                                    <div class="small text-muted">Struttura famiglie, sottofamiglie, gruppi e navigazione catalogo</div>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-12 col-md-6">
                        <a href="{{ route('admin.products.index') }}" class="card border h-100 text-decoration-none text-reset">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center"
                                     style="width: 48px; height: 48px;">
                                    <i class="fa-solid fa-box"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">Prodotti</div>
                                    <div class="small text-muted">Elenco prodotti, categorie ERP, immagini, prezzi e dettaglio commerciale</div>
                                </div>
                            </div>
                        </a>
                    </div>

                    @if(Route::has('admin.attributes.index'))
                        <div class="col-12 col-md-6">
                            <a href="{{ route('admin.attributes.index') }}" class="card border h-100 text-decoration-none text-reset">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center"
                                         style="width: 48px; height: 48px;">
                                        <i class="fa-solid fa-tags"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold">Attributi</div>
                                        <div class="small text-muted">Gestione attributi globali e valori associati ai prodotti</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if(Route::has('admin.attribute-values.index'))
                        <div class="col-12 col-md-6">
                            <a href="{{ route('admin.attribute-values.index') }}" class="card border h-100 text-decoration-none text-reset">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-secondary-subtle text-secondary d-inline-flex align-items-center justify-content-center"
                                         style="width: 48px; height: 48px;">
                                        <i class="fa-solid fa-list"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold">Valori attributo</div>
                                        <div class="small text-muted">Valori disponibili, mappature e struttura dei dati attributo</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if(Route::has('admin.customers.index'))
                        <div class="col-12 col-md-6">
                            <a href="{{ route('admin.customers.index') }}" class="card border h-100 text-decoration-none text-reset">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-dark-subtle text-dark d-inline-flex align-items-center justify-content-center"
                                         style="width: 48px; height: 48px;">
                                        <i class="fa-solid fa-users"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold">Clienti</div>
                                        <div class="small text-muted">Anagrafiche clienti, ACL e listini associati al contesto store</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if(Route::has('admin.store-visible-groups.index'))
                        <div class="col-12 col-md-6">
                            <a href="{{ route('admin.store-visible-groups.index') }}" class="card border h-100 text-decoration-none text-reset">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-info-subtle text-info d-inline-flex align-items-center justify-content-center"
                                         style="width: 48px; height: 48px;">
                                        <i class="fa-solid fa-store"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold">Gruppi visibili store</div>
                                        <div class="small text-muted">Associazioni gruppi commerciali per sito e store</div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h2 class="h5 mb-1">Contesto corrente</h2>
                <div class="text-muted small">Lo switch store admin cambia i dati mostrati nel backend</div>
            </div>

            <div class="card-body">
                <div class="mb-3">
                    <div class="text-muted small">Store selezionato</div>
                    <div class="fw-semibold">{{ $store->name }}</div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Dominio storefront</div>
                    <div class="fw-semibold">{{ $store->domain }}</div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Ditta / Site</div>
                    <div class="fw-semibold">{{ $store->ditta_cg18 }} / {{ $store->erp_site_code }}</div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Tipologia store</div>
                    <div class="fw-semibold">{{ !empty($store->is_b2b) ? 'B2B' : 'B2C' }}</div>
                </div>

                <div class="mb-0">
                    <div class="text-muted small">Attributi globali</div>
                    <div class="fw-semibold">
                        {{ number_format($stats['attributes_total'] ?? 0, 0, ',', '.') }}
                        <span class="mx-1">•</span>
                        valori:
                        {{ number_format($stats['attribute_values_total'] ?? 0, 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection