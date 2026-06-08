@extends('layouts.admin')

@section('title', 'Attributi')
@section('breadcrumb', 'Catalogo / Attributi')

@section('content')
<div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
    <div>
        <div class="text-muted small mb-1">Catalogo attributi</div>
        <h1 class="h3 mb-1">Attributi</h1>
        <div class="text-muted small">
            {{ number_format($attributes->total(), 0, ',', '.') }} attributi disponibili
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0">
        <h2 class="h5 mb-1">Filtri</h2>
        <div class="text-muted small">Ricerca e segmentazione degli attributi catalogo.</div>
    </div>

    <div class="card-body">
        <form method="GET" action="{{ route('admin.attributes.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label">Codice</label>
                <input type="text" name="code" class="form-control" value="{{ $filters['code'] }}" placeholder="Es. color">
            </div>

            <div class="col-12 col-md-3">
                <label class="form-label">Tipo</label>
                <input type="text" name="type" class="form-control" value="{{ $filters['type'] }}" placeholder="Es. select">
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label">Filtrabile</label>
                <select name="is_filterable" class="form-select">
                    <option value="">Tutti</option>
                    <option value="1" @selected($filters['is_filterable'] === '1')>Sì</option>
                    <option value="0" @selected($filters['is_filterable'] === '0')>No</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label">Variante</label>
                <select name="is_variant" class="form-select">
                    <option value="">Tutti</option>
                    <option value="1" @selected($filters['is_variant'] === '1')>Sì</option>
                    <option value="0" @selected($filters['is_variant'] === '0')>No</option>
                </select>
            </div>

            <div class="col-12 col-md-1 d-flex">
                <button type="submit" class="btn btn-primary w-100">Filtra</button>
            </div>

            <div class="col-12">
                <a href="{{ route('admin.attributes.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h5 mb-1">Elenco attributi</h2>
            <div class="text-muted small">Attributi con traduzioni e numero valori associati.</div>
        </div>
        <span class="badge rounded-pill text-bg-light border px-3 py-2">
            {{ number_format($attributes->total(), 0, ',', '.') }} elementi
        </span>
    </div>

    <div class="card-body">
        @if($attributes->isEmpty())
            <div class="text-muted">Nessun attributo trovato.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Codice</th>
                            <th>Label</th>
                            <th>Tipo</th>
                            <th>Valori</th>
                            <th>Filtrabile</th>
                            <th>Variante</th>
                            <th>Ordine</th>
                            <th class="text-end">Apri</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attributes as $attribute)
                            @php
                                $translation = method_exists($attribute, 'translationOrFallback')
                                    ? $attribute->translationOrFallback(app()->getLocale())
                                    : null;

                                $label = trim((string) ($translation?->label ?? ''));
                                if ($label === '') {
                                    $label = $attribute->code;
                                }
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $attribute->code }}</td>
                                <td>{{ $label }}</td>
                                <td>{{ $attribute->type ?: '-' }}</td>
                                <td>{{ number_format((int) ($attribute->values_count ?? 0), 0, ',', '.') }}</td>
                                <td>
                                    <span class="badge {{ $attribute->is_filterable ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $attribute->is_filterable ? 'Sì' : 'No' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $attribute->is_variant ? 'text-bg-primary' : 'text-bg-light border text-dark' }}">
                                        {{ $attribute->is_variant ? 'Sì' : 'No' }}
                                    </span>
                                </td>
                                <td>{{ $attribute->sort_order }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.attributes.show', $attribute) }}" class="btn btn-sm btn-outline-primary">
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

    @if($attributes->hasPages())
        <div class="card-footer bg-white">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="text-muted small">
                    Mostrati {{ $attributes->firstItem() ?: 0 }}–{{ $attributes->lastItem() ?: 0 }} di {{ number_format($attributes->total(), 0, ',', '.') }} attributi
                </div>
                <div>
                    {{ $attributes->links() }}
                </div>
            </div>
        </div>
    @endif
</div>
@endsection