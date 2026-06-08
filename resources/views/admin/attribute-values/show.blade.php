@extends('layouts.admin')

@section('title', 'Valore attributo')
@section('breadcrumb', 'Catalogo / Valore attributo')

@section('content')
@php
    $attributeTranslation = method_exists($value->attribute, 'translationOrFallback')
        ? $value->attribute->translationOrFallback(app()->getLocale())
        : null;

    $attributeLabel = trim((string) ($attributeTranslation?->label ?? ''));
    if ($attributeLabel === '') {
        $attributeLabel = $value->attribute?->code ?? '-';
    }

    $translation = method_exists($value, 'translationOrFallback')
        ? $value->translationOrFallback(app()->getLocale())
        : null;

    $valueLabel = trim((string) ($translation?->label ?? ''));
    if ($valueLabel === '') {
        $valueLabel = $value->value_code;
    }

    $swatch = method_exists($value, 'swatch') ? $value->swatch() : null;
    $swatchUrl = $swatch?->url
        ?? $swatch?->path
        ?? $swatch?->file_url
        ?? $swatch?->original_url
        ?? null;
@endphp

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
    <div>
        <div class="text-muted small mb-2">
            <a href="{{ route('admin.attribute-values.index') }}" class="text-decoration-none">Valori attributo</a>
            <span class="mx-1">/</span>
            <span>{{ $value->value_code }}</span>
        </div>

        <h1 class="h3 mb-1">{{ $valueLabel }}</h1>

        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border text-dark">Codice: {{ $value->value_code }}</span>
            <span class="badge text-bg-light border text-dark">Attributo: {{ $value->attribute?->code }}</span>
        </div>
    </div>

    <div>
        <a href="{{ route('admin.attribute-values.index') }}" class="btn btn-outline-dark">
            Torna alla lista
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <strong>Dettaglio valore</strong>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <div class="text-muted small mb-2">Swatch</div>
                    <div class="border rounded-3 bg-light d-flex align-items-center justify-content-center overflow-hidden" style="width: 96px; height: 96px;">
                        @if($swatchUrl)
                            <img
                                src="{{ $swatchUrl }}"
                                alt="{{ $valueLabel }}"
                                class="img-fluid"
                                style="max-width: 100%; max-height: 100%; object-fit: cover;"
                            >
                        @else
                            <span class="text-muted small">Nessuna immagine</span>
                        @endif
                    </div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Attributo</div>
                    <div class="fw-semibold">{{ $attributeLabel }}</div>
                    <div class="text-muted small">{{ $value->attribute?->code }}</div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Codice valore</div>
                    <div class="fw-semibold">{{ $value->value_code }}</div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Label</div>
                    <div class="fw-semibold">{{ $valueLabel }}</div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Ordine</div>
                    <div class="fw-semibold">{{ $value->sort_order }}</div>
                </div>

                <div class="mb-0">
                    <div class="text-muted small">ERP lastchange</div>
                    <div class="fw-semibold">{{ optional($value->erp_lastchange)->format('d/m/Y H:i:s') ?: '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <strong>Prodotti collegati</strong>
            </div>
            <div class="card-body p-0">
                @if($value->products->isEmpty())
                    <div class="p-4 text-muted">Nessun prodotto collegato.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>SKU</th>
                                    <th>Tipo</th>
                                    <th>Valore raw</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($value->products as $product)
                                    <tr>
                                        <td class="fw-semibold">{{ $product->sku }}</td>
                                        <td>{{ $product->type ?: '-' }}</td>
                                        <td>{{ $product->pivot->raw_value ?: '-' }}</td>
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
@endsection