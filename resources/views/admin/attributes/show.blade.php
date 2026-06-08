@extends('layouts.admin')

@section('title', 'Attributo')
@section('breadcrumb', 'Catalogo / Attributo')

@section('content')
@php
    $translation = method_exists($attribute, 'translationOrFallback')
        ? $attribute->translationOrFallback(app()->getLocale())
        : null;

    $label = trim((string) ($translation?->label ?? ''));
    if ($label === '') {
        $label = $attribute->code;
    }
@endphp

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
    <div>
        <div class="text-muted small mb-2">
            <a href="{{ route('admin.attributes.index') }}" class="text-decoration-none">Attributi</a>
            <span class="mx-1">/</span>
            <span>{{ $attribute->code }}</span>
        </div>

        <h1 class="h3 mb-1">{{ $label }}</h1>

        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border text-dark">Codice: {{ $attribute->code }}</span>
            <span class="badge text-bg-light border text-dark">Tipo: {{ $attribute->type ?: '-' }}</span>
            <span class="badge {{ $attribute->is_filterable ? 'text-bg-success' : 'text-bg-secondary' }}">
                Filtrabile: {{ $attribute->is_filterable ? 'Sì' : 'No' }}
            </span>
            <span class="badge {{ $attribute->is_variant ? 'text-bg-primary' : 'text-bg-secondary' }}">
                Variante: {{ $attribute->is_variant ? 'Sì' : 'No' }}
            </span>
        </div>
    </div>

    <div>
        <a href="{{ route('admin.attributes.index') }}" class="btn btn-outline-dark">
            Torna alla lista
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <strong>Dettaglio attributo</strong>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="text-muted small">Codice</div>
                    <div class="fw-semibold">{{ $attribute->code }}</div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Label</div>
                    <div class="fw-semibold">{{ $label }}</div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Help text</div>
                    <div>{{ $translation?->help_text ?: '-' }}</div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Tipo</div>
                    <div class="fw-semibold">{{ $attribute->type ?: '-' }}</div>
                </div>

                <div class="mb-3">
                    <div class="text-muted small">Ordine</div>
                    <div class="fw-semibold">{{ $attribute->sort_order }}</div>
                </div>

                <div class="mb-0">
                    <div class="text-muted small">ERP lastchange</div>
                    <div class="fw-semibold">{{ optional($attribute->erp_lastchange)->format('d/m/Y H:i:s') ?: '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <strong>Valori attributo</strong>
            </div>
            <div class="card-body p-0">
                @if($attribute->values->isEmpty())
                    <div class="p-4 text-muted">Nessun valore associato.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 72px;">Swatch</th>
                                    <th>Codice</th>
                                    <th>Label</th>
                                    <th>Ordine</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($attribute->values as $value)
                                    @php
                                        $valueTranslation = method_exists($value, 'translationOrFallback')
                                            ? $value->translationOrFallback(app()->getLocale())
                                            : null;

                                        $valueLabel = trim((string) ($valueTranslation?->label ?? ''));
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
                                    <tr>
                                        <td>
                                            @if($swatchUrl)
                                                <div class="border rounded-3 bg-light d-inline-flex align-items-center justify-content-center overflow-hidden"
                                                     style="width: 44px; height: 44px;">
                                                    <img
                                                        src="{{ $swatchUrl }}"
                                                        alt="{{ $valueLabel }}"
                                                        class="img-fluid"
                                                        style="max-width: 100%; max-height: 100%; object-fit: cover;"
                                                    >
                                                </div>
                                            @else
                                                <div class="border rounded-3 bg-light d-inline-flex align-items-center justify-content-center text-muted"
                                                     style="width: 44px; height: 44px;">
                                                    <i class="fa-regular fa-image"></i>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="fw-semibold">{{ $value->value_code }}</td>
                                        <td>{{ $valueLabel }}</td>
                                        <td>{{ $value->sort_order }}</td>
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