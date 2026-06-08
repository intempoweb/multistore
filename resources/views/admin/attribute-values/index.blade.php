@extends('layouts.admin')

@section('title', 'Valori attributo')
@section('breadcrumb', 'Catalogo / Valori attributo')

@section('content')
<div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
    <div>
        <div class="text-muted small mb-1">Catalogo valori attributo</div>
        <h1 class="h3 mb-1">Valori attributo</h1>
        <div class="text-muted small">
            {{ number_format($values->total(), 0, ',', '.') }} valori disponibili
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0">
        <h2 class="h5 mb-1">Filtri</h2>
        <div class="text-muted small">Ricerca per attributo o codice valore.</div>
    </div>

    <div class="card-body">
        <form method="GET" action="{{ route('admin.attribute-values.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label">Attributo</label>
                <select name="attribute_id" class="form-select">
                    <option value="">Tutti</option>
                    @foreach($attributes as $attribute)
                        <option value="{{ $attribute->id }}" @selected($filters['attribute_id'] === (string) $attribute->id)>
                            {{ $attribute->code }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-5">
                <label class="form-label">Codice valore</label>
                <input type="text" name="value_code" class="form-control" value="{{ $filters['value_code'] }}" placeholder="Es. red">
            </div>

            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filtra</button>
                <a href="{{ route('admin.attribute-values.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0">
        <h2 class="h5 mb-1">Elenco valori</h2>
        <div class="text-muted small">Valori con attributo di appartenenza e utilizzo su prodotti.</div>
    </div>

    <div class="card-body">
        @if($values->isEmpty())
            <div class="text-muted">Nessun valore attributo trovato.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 84px;">Swatch</th>
                            <th>Attributo</th>
                            <th>Codice</th>
                            <th>Label</th>
                            <th>Prodotti</th>
                            <th>Ordine</th>
                            <th class="text-end">Apri</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($values as $value)
                            @php
                                $attributeTranslation = method_exists($value->attribute, 'translationOrFallback')
                                    ? $value->attribute->translationOrFallback(app()->getLocale())
                                    : null;

                                $attributeLabel = trim((string) ($attributeTranslation?->label ?? ''));
                                if ($attributeLabel === '') {
                                    $attributeLabel = $value->attribute?->code ?? '-';
                                }

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
                                    <div class="border rounded-3 bg-light d-flex align-items-center justify-content-center overflow-hidden" style="width: 48px; height: 48px;">
                                        @if($swatchUrl)
                                            <img
                                                src="{{ $swatchUrl }}"
                                                alt="{{ $valueLabel }}"
                                                class="img-fluid"
                                                style="max-width: 100%; max-height: 100%; object-fit: cover;"
                                            >
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $attributeLabel }}</div>
                                    <div class="text-muted small">{{ $value->attribute?->code }}</div>
                                </td>
                                <td class="fw-semibold">{{ $value->value_code }}</td>
                                <td>{{ $valueLabel }}</td>
                                <td>{{ number_format((int) ($value->products_count ?? 0), 0, ',', '.') }}</td>
                                <td>{{ $value->sort_order }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.attribute-values.show', $value) }}" class="btn btn-sm btn-outline-primary">
                                        Apri
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($values->hasPages())
        <div class="card-footer bg-white">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="text-muted small">
                    Mostrati {{ $values->firstItem() ?: 0 }}–{{ $values->lastItem() ?: 0 }} di {{ number_format($values->total(), 0, ',', '.') }} valori
                </div>
                <div>
                    {{ $values->links() }}
                </div>
            </div>
        </div>
    @endif
</div>
@endsection