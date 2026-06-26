@extends('layouts.admin')

@section('title', 'Clienti')
@section('breadcrumb', 'Clienti')

@section('content')
<div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
    <div>
        <div class="text-muted small mb-1">Anagrafiche clienti ERP sincronizzate</div>
        <h1 class="h3 mb-1">Clienti</h1>
        <div class="text-muted small d-flex flex-wrap gap-2 align-items-center">
            <span><strong>{{ $store->name }}</strong></span>
            <span>•</span>
            <span>Ditta {{ $store->ditta_cg18 }}</span>
            <span>•</span>
            <span>{{ number_format($customers->total(), 0, ',', '.') }} clienti</span>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-chart-line me-1"></i>
            Dashboard
        </a>

        @if(Route::has('admin.customer-visible-groups.index'))
            <a href="{{ route('admin.customer-visible-groups.index') }}" class="btn btn-outline-dark">
                <i class="fa-solid fa-users-viewfinder me-1"></i>
                Gruppi visibili
            </a>
        @endif
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0">
        <div>
            <h2 class="h5 mb-1">Filtri</h2>
            <div class="text-muted small">Ricerca anagrafiche clienti ERP per lo store selezionato.</div>
        </div>
    </div>

    <div class="card-body">
        <form method="GET" action="{{ route('admin.customers.index') }}" class="row g-3">
            <div class="col-12 col-xl-4">
                <label class="form-label">Ricerca</label>
                <input
                    type="text"
                    name="search"
                    class="form-control"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Ragione sociale, cliente, P.IVA, CF, email"
                >
            </div>

            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label">Tipo CF</label>
                <input
                    type="number"
                    name="tipocf"
                    class="form-control"
                    value="{{ $filters['tipocf'] ?? '' }}"
                    placeholder="Es. 0"
                >
            </div>

            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label">Cliente</label>
                <input
                    type="number"
                    name="clifor"
                    class="form-control"
                    value="{{ $filters['clifor'] ?? '' }}"
                    placeholder="Es. 127"
                >
            </div>

            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label">Codice ERP</label>
                <input
                    type="number"
                    name="codice"
                    class="form-control"
                    value="{{ $filters['codice'] ?? '' }}"
                    placeholder="Es. 2542"
                >
            </div>

            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label">Listino</label>
                <input
                    type="number"
                    name="listino"
                    class="form-control"
                    value="{{ $filters['listino'] ?? '' }}"
                    placeholder="Es. 1"
                >
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">Email</label>
                <input
                    type="text"
                    name="email"
                    class="form-control"
                    value="{{ $filters['email'] ?? '' }}"
                    placeholder="Es. info@azienda.it"
                >
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">P.IVA / CF</label>
                <input
                    type="text"
                    name="vat"
                    class="form-control"
                    value="{{ $filters['vat'] ?? '' }}"
                    placeholder="Partita IVA o CF"
                >
            </div>

            <div class="col-6 col-md-3 col-xl-3">
                <label class="form-label">Web</label>
                <select name="web_enabled" class="form-select">
                    <option value="" @selected(($filters['web_enabled'] ?? '') === '')>Tutti</option>
                    <option value="PT" @selected(($filters['web_enabled'] ?? '') === 'PT')>Abilitato</option>
                    <option value="NO" @selected(($filters['web_enabled'] ?? '') === 'NO')>Non web</option>
                </select>
            </div>

            <div class="col-6 col-md-3 col-xl-3">
                <label class="form-label">Stato</label>
                <select name="active" class="form-select">
                    <option value="" @selected(($filters['active'] ?? '') === '')>Tutti</option>
                    <option value="1" @selected(($filters['active'] ?? '') === '1')>Attivi</option>
                    <option value="0" @selected(($filters['active'] ?? '') === '0')>Disattivi</option>
                </select>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 pt-1">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-magnifying-glass me-1"></i>
                    Filtra
                </button>

                <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-secondary">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
        <div>
            <h2 class="h5 mb-1">Elenco clienti</h2>
            <div class="text-muted small">Vista clienti responsive senza scroll orizzontale.</div>
        </div>

        <span class="badge rounded-pill text-bg-light border px-3 py-2">
            {{ number_format($customers->total(), 0, ',', '.') }} elementi
        </span>
    </div>

    <div class="card-body">
        @if($customers->isEmpty())
            <div class="text-muted">Nessun cliente trovato per i filtri selezionati.</div>
        @else
            <div class="row g-3">
                @foreach($customers as $customer)
                    @php
                        $customerEmail = trim((string) ($customer->indemail_cg16 ?? ''));
                        $pecEmail = trim((string) ($customer->email_pec_cg16 ?? ''));
                        $vatNumber = trim((string) ($customer->partiva_cg16 ?? ''));
                        $taxCode = trim((string) ($customer->codfiscale_cg16 ?? ''));
                        $agentCode = trim((string) ($customer->agente_mg17 ?? ''));
                        $agentCompany = trim((string) ($customer->ragsoanag_vwebdcg44 ?? ''));
                        $agentEmail = trim((string) ($customer->indeemail_vwebdcg44 ?? ''));

                        $addressParts = collect([
                            trim((string) ($customer->indirizzo_cg16 ?? '')),
                            trim(collect([
                                $customer->cap_cg16,
                                $customer->citta_cg16,
                                $customer->prov_cg16,
                            ])->filter()->implode(' ')),
                        ])->filter()->values();

                        $address = $addressParts->isNotEmpty() ? $addressParts->implode(', ') : '-';

                        $assignedListini = collect($customer->customer_listino_assignments ?? []);
                        $assignmentListinoIds = $assignedListini
                            ->map(fn ($assigned) => (int) ($assigned->listino_id ?? $assigned['listino_id'] ?? 0))
                            ->filter(fn ($value) => $value > 0)
                            ->unique()
                            ->values();

                        $effectiveListinoIds = collect($customer->customer_effective_listino_ids ?? [])
                            ->filter()
                            ->map(fn ($value) => (int) $value)
                            ->filter(fn ($value) => $value > 0)
                            ->unique()
                            ->values();

                        $defaultListinoId = $customer->customer_default_listino_id
                            ? (int) $customer->customer_default_listino_id
                            : null;

                        $primaryListinoId = $customer->primary_listino_id
                            ? (int) $customer->primary_listino_id
                            : null;

                        if ($primaryListinoId) {
                            $listinoIdsToShow = collect([$primaryListinoId]);
                        } elseif ($assignmentListinoIds->isNotEmpty()) {
                            $listinoIdsToShow = collect([(int) $assignmentListinoIds->first()]);
                        } elseif ($effectiveListinoIds->isNotEmpty()) {
                            $listinoIdsToShow = collect([(int) $effectiveListinoIds->first()]);
                        } elseif ($defaultListinoId) {
                            $listinoIdsToShow = collect([$defaultListinoId]);
                        } else {
                            $listinoIdsToShow = collect();
                        }
                    @endphp

                    <div class="col-12">
                        <div class="border rounded-3 p-3 h-100 bg-white">
                            <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-start gap-3 mb-3">
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                                        <h3 class="h6 mb-0">{{ $customer->ragsoanag_cg16 ?: '-' }}</h3>
                                        <span class="badge text-bg-light border text-dark">ID {{ $customer->clifor_cg44 }}</span>
                                        @if($customer->codice_cg16)
                                            <span class="badge text-bg-light border text-dark">Cod. ERP {{ $customer->codice_cg16 }}</span>
                                        @endif
                                    </div>
                                    <div class="text-muted small">{{ $address }}</div>
                                </div>

                                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-xl-end">
                                    <span class="badge {{ $customer->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $customer->is_active ? 'Attivo' : 'Disattivo' }}
                                    </span>
                                    <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-sm btn-primary">
                                        Apri
                                    </a>
                                </div>
                            </div>

                            <div class="row g-3 small align-items-start">
                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="text-muted">Email</div>
                                    <div class="fw-semibold text-break">{{ $customerEmail !== '' ? $customerEmail : '-' }}</div>
                                </div>

                                <div class="col-6 col-md-3 col-xl-2">
                                    <div class="text-muted">P.IVA</div>
                                    <div class="fw-semibold">{{ $vatNumber !== '' ? $vatNumber : '-' }}</div>
                                </div>

                                <div class="col-6 col-md-3 col-xl-2">
                                    <div class="text-muted">Cod. fiscale</div>
                                    <div class="fw-semibold">{{ $taxCode !== '' ? $taxCode : '-' }}</div>
                                </div>

                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="text-muted">PEC</div>
                                    <div class="fw-semibold text-break">{{ $pecEmail !== '' ? $pecEmail : '-' }}</div>
                                </div>

                                <div class="col-6 col-md-3 col-xl-2">
                                    <div class="text-muted">Agente</div>
                                    <div class="fw-semibold">{{ $agentCode !== '' ? $agentCode : '-' }}</div>
                                </div>

                                <div class="col-12 col-md-9 col-xl-5">
                                    <div class="text-muted">Ragione sociale agente</div>
                                    <div class="fw-semibold text-break">{{ $agentCompany !== '' ? $agentCompany : '-' }}</div>
                                </div>

                                <div class="col-12 col-md-6 col-xl-3">
                                    <div class="text-muted">Email agente</div>
                                    <div class="fw-semibold text-break">{{ $agentEmail !== '' ? $agentEmail : '-' }}</div>
                                </div>

                                <div class="col-12 col-md-6 col-xl-2">
                                    <div class="text-muted mb-1">Listino ID</div>
                                    @if($listinoIdsToShow->isNotEmpty())
                                        @php
                                            $listinoId = (int) $listinoIdsToShow->first();
                                            $isDefaultListino = $assignmentListinoIds->isEmpty()
                                                && $defaultListinoId !== null
                                                && $listinoId === $defaultListinoId;
                                        @endphp

                                        <span class="badge {{ $isDefaultListino ? 'text-bg-dark' : 'text-bg-light border text-dark' }}">
                                            {{ $listinoId }}
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card-footer bg-white py-3">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div class="text-muted small">
                Mostrati {{ $customers->firstItem() }}-{{ $customers->lastItem() }} di {{ number_format($customers->total(), 0, ',', '.') }} clienti
            </div>
            <div class="d-flex justify-content-lg-end overflow-auto">
                {{ $customers->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .pagination {
        margin-bottom: 0;
        flex-wrap: wrap;
    }

    .pagination svg {
        width: 1rem;
        height: 1rem;
    }
</style>
@endpush
