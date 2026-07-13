@extends('layouts.admin')

@section('title', 'Gruppi visibili clienti')
@section('breadcrumb', 'Clienti / Gruppi visibili')

@section('content')
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Gruppi visibili clienti</h1>
        <div class="text-muted small">
            <strong>{{ $store->name }}</strong>
            <span class="mx-1">•</span>
            Ditta {{ $store->ditta_cg18 }}
            <span class="mx-1">•</span>
            {{ number_format($rows->total(), 0, ',', '.') }} associazioni
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Clienti
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0">
        <h2 class="h5 mb-1">Filtri</h2>
        <div class="text-muted small">
            Ricerca le associazioni tra clienti ERP e gruppi commerciali visibili.
        </div>
    </div>

    <div class="card-body">
        <form method="GET" action="{{ route('admin.customer-visible-groups.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label" for="tipocf">Tipo cliente</label>
                <input
                    type="number"
                    name="tipocf"
                    id="tipocf"
                    class="form-control"
                    value="{{ $filters['tipocf'] ?? '' }}"
                    min="0"
                    placeholder="Es. 0"
                >
            </div>

            <div class="col-12 col-md-3">
                <label class="form-label" for="clifor">Codice cliente</label>
                <input
                    type="number"
                    name="clifor"
                    id="clifor"
                    class="form-control"
                    value="{{ $filters['clifor'] ?? '' }}"
                    min="0"
                    placeholder="Es. 12345"
                >
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label" for="site_flag">Canale</label>
                <select name="site_flag" id="site_flag" class="form-select">
                    <option value="">Tutti</option>
                    <option value="1" @selected(($filters['site_flag'] ?? '') === '1')>B2B</option>
                    <option value="0" @selected(($filters['site_flag'] ?? '') === '0')>B2C</option>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <label class="form-label" for="active">Stato</label>
                <select name="active" id="active" class="form-select">
                    <option value="">Tutti</option>
                    <option value="1" @selected(($filters['active'] ?? '') === '1')>Attivi</option>
                    <option value="0" @selected(($filters['active'] ?? '') === '0')>Disattivati</option>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label class="form-label" for="group">Gruppo</label>
                <input
                    type="text"
                    name="group"
                    id="group"
                    class="form-control"
                    value="{{ $filters['group'] ?? '' }}"
                    placeholder="Codice o descrizione"
                >
            </div>

            <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-magnifying-glass me-1"></i>
                    Filtra
                </button>

                <a href="{{ route('admin.customer-visible-groups.index') }}" class="btn btn-outline-secondary">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
        <div>
            <h2 class="h5 mb-1">Associazioni cliente-gruppo</h2>
            <div class="text-muted small">
                Questi dati arrivano dalla sincronizzazione ERP e determinano quali gruppi prodotto risultano visibili ai clienti.
            </div>
        </div>

        <span class="badge rounded-pill text-bg-light border px-3 py-2">
            {{ number_format($rows->total(), 0, ',', '.') }} elementi
        </span>
    </div>

    <div class="card-body">
        @if($rows->isEmpty())
            <div class="text-muted">Nessuna associazione trovata.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Cliente</th>
                            <th>Canale</th>
                            <th>Gruppo</th>
                            <th>Descrizione</th>
                            <th>Stato ERP</th>
                            <th>Stato locale</th>
                            <th>Ultimo aggiornamento ERP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $row->clifor_cg44 }}</div>
                                    <div class="small text-muted">Tipo {{ $row->tipocf_cg44 }}</div>
                                </td>
                                <td>
                                    @if((string) $row->flg_b2b_b2c_webt81 === '1')
                                        <span class="badge text-bg-dark">B2B</span>
                                    @elseif((string) $row->flg_b2b_b2c_webt81 === '0')
                                        <span class="badge text-bg-secondary">B2C</span>
                                    @else
                                        <span class="badge text-bg-light border text-dark">{{ $row->flg_b2b_b2c_webt81 }}</span>
                                    @endif
                                </td>
                                <td class="fw-semibold">{{ $row->codice_xx32 }}</td>
                                <td>{{ $row->descrizione_xx32 ?: '-' }}</td>
                                <td>
                                    @if((int) $row->flgattivo_xx32 === 1)
                                        <span class="badge text-bg-success">Attivo</span>
                                    @else
                                        <span class="badge text-bg-secondary">Non attivo</span>
                                    @endif
                                </td>
                                <td>
                                    @if($row->is_active)
                                        <span class="badge text-bg-success">Sincronizzato</span>
                                    @else
                                        <span class="badge text-bg-secondary">Disattivato</span>
                                    @endif
                                </td>
                                <td>
                                    {{ optional($row->dataultimoagg_xx32)->format('d/m/Y') ?: '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($rows->hasPages())
        <div class="card-footer bg-white">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="text-muted small">
                    Mostrati {{ $rows->firstItem() ?: 0 }}-{{ $rows->lastItem() ?: 0 }} di {{ number_format($rows->total(), 0, ',', '.') }} elementi
                </div>
                <div>
                    {{ $rows->links() }}
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
