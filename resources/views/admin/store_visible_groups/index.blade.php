@extends('layouts.admin')

@section('title', 'Gruppi visibili store')
@section('breadcrumb', 'Store / Gruppi visibili')

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Gruppi visibili store</h1>
        <div class="text-muted small">
            <strong>{{ $store->name }}</strong>
            <span class="mx-1">•</span>
            Ditta {{ $store->ditta_cg18 }}
            <span class="mx-1">•</span>
            Site {{ $store->erp_site_code }}
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0">
        <h2 class="h5 mb-1">Filtri</h2>
        <div class="text-muted small">
            Ricerca gruppi commerciali visibili per lo store corrente.
        </div>
    </div>

    <div class="card-body">
        <form method="GET" action="{{ route('admin.store-visible-groups.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label">Codice</label>
                <input
                    type="text"
                    name="code"
                    class="form-control"
                    value="{{ request('code') }}"
                    placeholder="Es. GRP001"
                >
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label">Ricerca libera</label>
                <input
                    type="text"
                    name="search"
                    class="form-control"
                    value="{{ request('search') }}"
                    placeholder="Codice o descrizione"
                >
            </div>

            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fa-solid fa-magnifying-glass me-1"></i>
                    Filtra
                </button>
            </div>

            <div class="col-12">
                <a href="{{ route('admin.store-visible-groups.index') }}" class="btn btn-outline-secondary">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
        <div>
            <h2 class="h5 mb-1">Elenco gruppi</h2>
            <div class="text-muted small">
                Gruppi commerciali visibili per lo store corrente.
            </div>
        </div>

        <span class="badge rounded-pill text-bg-light border px-3 py-2">
            {{ number_format($groups->total(), 0, ',', '.') }} elementi
        </span>
    </div>

    <div class="card-body">
        @if($groups->isEmpty())
            <div class="text-muted">Nessun gruppo visibile trovato.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 180px;">Codice</th>
                            <th>Descrizione</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($groups as $group)
                            <tr>
                                <td class="fw-semibold">{{ $group->codice_xx32 }}</td>
                                <td>{{ $group->descrizione_xx32 ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($groups->hasPages())
        <div class="card-footer bg-white">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="text-muted small">
                    Mostrati {{ $groups->firstItem() ?: 0 }}–{{ $groups->lastItem() ?: 0 }} di {{ number_format($groups->total(), 0, ',', '.') }} gruppi
                </div>
                <div>
                    {{ $groups->links() }}
                </div>
            </div>
        </div>
    @endif
</div>
@endsection