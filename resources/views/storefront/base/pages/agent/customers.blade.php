@extends($storefrontLayout)

@section('content')
@php
    $search = trim((string) request('q', ''));
    $agentDisplayName = collect([
        $agentName ?: null,
        $agentEmail ?: null,
    ])->filter()->implode(' · ');

    if ($agentDisplayName === '') {
        $agentDisplayName = $agentCode !== '' ? 'Cod. agente ' . $agentCode : 'Agente';
    }
@endphp

<div class="container py-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Clienti agente</h1>
            <p class="text-muted mb-0">
                Sei in modalità agente. Seleziona un cliente per entrare nel suo account.
            </p>
        </div>

        <div class="text-muted small text-lg-end">
            <div>{{ $customers->total() }} clienti trovati</div>
            <div>
                Agente: <span class="fw-semibold">{{ $agentDisplayName }}</span>
            </div>
        </div>
    </div>

    <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex gap-3 align-items-start">
            <div class="flex-shrink-0">
                <i class="fa-solid fa-user-tie"></i>
            </div>
            <div>
                <div class="fw-semibold">Accesso agente attivo</div>
                <div class="small mb-0">
                    In questa pagina non stai operando come cliente. L’area account sarà disponibile solo dopo aver cliccato “Entra come cliente”.
                </div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('storefront.agent.customers') }}" class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg">
                    <label for="agent_customer_search" class="form-label small fw-semibold">
                        Cerca cliente
                    </label>
                    <input
                        type="search"
                        id="agent_customer_search"
                        name="q"
                        value="{{ $search }}"
                        class="form-control"
                        placeholder="Ragione sociale, email o codice cliente"
                    >
                </div>

                <div class="col-12 col-lg-auto d-flex gap-2">
                    <button type="submit" class="btn btn-dark">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>
                        Cerca
                    </button>

                    @if($search !== '')
                        <a href="{{ route('storefront.agent.customers') }}" class="btn btn-outline-secondary">
                            Reset
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </form>

    @if($customers->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <h2 class="h5 mb-2">Nessun cliente trovato</h2>
                <p class="text-muted mb-0">
                    @if($search !== '')
                        Non ci sono clienti collegati al tuo agente per “{{ $search }}”.
                    @else
                        Non ci sono clienti collegati al tuo agente.
                    @endif
                </p>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach($customers as $customer)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <div class="mb-3">
                                <h2 class="h6 mb-2">
                                    {{ $customer->ragsoanag_cg16 ?: 'Cliente '.$customer->clifor_cg44 }}
                                </h2>

                                <div class="small text-muted">
                                    <div>
                                        <span class="fw-semibold">Codice:</span>
                                        {{ $customer->clifor_cg44 }}
                                    </div>
                                    <div>
                                        <span class="fw-semibold">Email:</span>
                                        {{ $customer->indemail_cg16 ?: '—' }}
                                    </div>
                                    @if(!empty($customer->partiva_cg16))
                                        <div>
                                            <span class="fw-semibold">P. IVA:</span>
                                            {{ $customer->partiva_cg16 }}
                                        </div>
                                    @endif
                                    @if(!empty($customer->citta_cg16) || !empty($customer->prov_cg16))
                                        <div>
                                            <span class="fw-semibold">Località:</span>
                                            {{ collect([$customer->citta_cg16, $customer->prov_cg16])->filter()->implode(' ') }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <a
                                href="{{ route('storefront.agent.customers.open', $customer) }}"
                                class="btn btn-dark btn-sm w-100 mt-auto"
                                target="_blank"
                                rel="noopener"
                            >
                                <i class="fa-solid fa-up-right-from-square me-1"></i>
                                Entra come cliente
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $customers->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection