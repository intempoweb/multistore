@extends('layouts.admin')

@section('title', 'Catalogo')
@section('breadcrumb', 'Catalogo')

@section('content')
@php
    $hasItems = isset($items) && $items instanceof \Illuminate\Support\Collection && $items->isNotEmpty();
    $hasProducts = isset($products) && $products;
@endphp

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <div class="text-muted small mb-1">Catalogo store</div>

        <h1 class="h3 mb-1">
            {{ $currentLabel ?? 'Catalogo' }}
        </h1>

        <div class="text-muted small">
            <strong>{{ $store->name }}</strong>
            <span class="mx-1">•</span>
            Ditta {{ $store->ditta_cg18 }}
            <span class="mx-1">•</span>
            Site {{ $store->erp_site_code }}
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        @if($hasItems)
            <span class="badge rounded-pill text-bg-light border px-3 py-2">
                {{ number_format($items->count(), 0, ',', '.') }} categorie
            </span>
        @endif

        @if($hasProducts)
            <span class="badge rounded-pill text-bg-light border px-3 py-2">
                {{ number_format($products->total(), 0, ',', '.') }} prodotti
            </span>
        @endif

        <a href="{{ $backUrl ?? route('admin.catalog.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>
            {{ $backLabel ?? 'Catalogo' }}
        </a>

        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-box me-1"></i>
            Prodotti
        </a>
    </div>
</div>

@if($hasItems)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0">
            <div>
                <h2 class="h5 mb-1">{{ $sectionTitle ?? 'Sottocategorie' }}</h2>
                <div class="text-muted small">
                    Navigazione catalogo ERP per lo store selezionato.
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="row g-3">
                @foreach($items as $item)
                    @php
                        $label = trim((string) ($item->description ?? ''));

                        if ($label === '') {
                            $label = 'Categoria senza descrizione';
                        }
                    @endphp

                    <div class="col-12 col-md-6 col-xl-4">
                        <a href="{{ $item->url }}" class="text-decoration-none text-reset d-block h-100">
                            <div class="card border-0 shadow-sm h-100 catalog-node-card">
                                <div class="card-body d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                            <div>
                                                <div class="text-muted small text-uppercase mb-1">
                                                    {{ $item->level_label }}
                                                </div>

                                                <div class="fs-5 fw-semibold">
                                                    {{ $label }}
                                                </div>
                                            </div>

                                            <span class="badge rounded-pill text-bg-light border">
                                                {{ $item->level }}
                                            </span>
                                        </div>

                                        <div class="text-muted small mb-1">
                                            Prodotti attivi nello store
                                        </div>

                                        <div class="fs-4 fw-bold">
                                            {{ number_format($item->prodotti ?? 0, 0, ',', '.') }}
                                        </div>
                                    </div>

                                    <div class="pt-3 mt-3 border-top d-flex justify-content-between align-items-center">
                                        <span class="text-muted small">
                                            Apri categoria
                                        </span>

                                        <span class="btn btn-primary btn-sm">
                                            Apri
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

@if($hasProducts)
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0">
            <div>
                <h2 class="h5 mb-1">Prodotti del livello corrente</h2>
                <div class="text-muted small">
                    Prodotti associati direttamente a questo livello del catalogo.
                </div>
            </div>
        </div>

        <div class="card-body">
            @if($products->isEmpty())
                <div class="alert alert-light border mb-0">
                    Nessun prodotto disponibile per questo livello.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 80px"></th>
                                <th>Prodotto</th>
                                <th style="width: 140px">SKU</th>
                                <th style="width: 140px">Prezzo</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($products as $product)
                                @php
                                    $translation = method_exists($product, 'translationOrFallback')
                                        ? $product->translationOrFallback(app()->getLocale())
                                        : null;

                                    $name = trim((string) ($translation?->name ?? ''));
                                    if ($name === '') {
                                        $name = $product->sku;
                                    }

                                    $mainImage = method_exists($product, 'mainImage') ? $product->mainImage() : null;
                                    $image = $mainImage?->url
                                        ?? $mainImage?->path
                                        ?? $product->mediaAssets->first()?->url
                                        ?? $product->mediaAssets->first()?->path
                                        ?? null;
                                @endphp

                                <tr>
                                    <td>
                                        <div class="border rounded-3 bg-light d-flex align-items-center justify-content-center overflow-hidden" style="width: 56px; height: 56px;">
                                            @if($image)
                                                <img src="{{ $image }}" alt="{{ $name }}" class="img-fluid" style="max-width: 100%; max-height: 100%; object-fit: cover;">
                                            @else
                                                <i class="fa-regular fa-image text-muted"></i>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="fw-semibold">
                                        <a href="{{ route('admin.products.show', $product) }}" class="text-decoration-none">
                                            {{ $name }}
                                        </a>
                                    </td>

                                    <td>{{ $product->sku }}</td>

                                    <td>
                                        {{ $product->public_price !== null ? number_format((float) $product->public_price, 2, ',', '.') . ' €' : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($products->hasPages())
                    <div class="mt-3">
                        {{ $products->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
@endif

@if(!$hasItems && !$hasProducts)
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="alert alert-light border mb-0">
                Nessuna categoria o prodotto disponibile per questo livello.
            </div>
        </div>
    </div>
@endif
@endsection