@extends($storefrontLayout)

@section('title', 'Il mio account')

@section('content')
@php
    /** @var \App\Models\Customer|null $customer */
    $customer = auth('customer')->user();
    $agentContextId = (string) request('agent_context', '');
    $agentContext = $agentContextId !== '' ? session("agent_contexts.$agentContextId") : null;
    $isAgentContext = session('agent_mode') === true && is_array($agentContext) && !empty($agentContext['customer_id']);

    if ($isAgentContext) {
        $contextCustomer = \App\Models\Customer::query()
            ->active()
            ->webEnabled()
            ->where('id', (int) $agentContext['customer_id'])
            ->where('ditta_cg18', (int) ($store->ditta_cg18 ?? 0))
            ->first();

        if ($contextCustomer instanceof \App\Models\Customer) {
            $customer = $contextCustomer;
        }
    }

    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];
@endphp

<div class="row g-4">

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="text-muted small mb-1">
                    {{ $isAgentContext ? 'Modalità agente · cliente aperto' : 'Area riservata cliente' }}
                </div>

                <h1 class="h3 fw-bold mb-2">
                    Benvenuto {{ $customer?->ragsoanag_cg16 ?? 'Cliente' }}
                </h1>

                <div class="text-muted small">
                    Qui puoi consultare i tuoi dati, gestire il tuo account e ritrovare rapidamente i prodotti salvati.
                </div>

               @if($isAgentContext)
                    <div class="alert alert-warning border-0 mt-3 mb-0">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-user-tie mt-1"></i>

                            <div class="small">
                                <div class="fw-semibold">
                                    Accesso agente attivo
                                </div>

                                <div>
                                    <strong>Agente:</strong>
                                    {{ $agentContext['agent_name'] ?? $agentContext['agent_email'] ?? '-' }}
                                </div>

                                <div>
                                    <strong>Cliente:</strong>
                                    {{ $customer?->ragsoanag_cg16 ?? '-' }}
                                    @if($customer?->clifor_cg44)
                                        ({{ $customer->clifor_cg44 }})
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <a
            href="{{ route('storefront.wishlist.index', $contextParams) }}"
            class="card border-0 shadow-sm h-100 text-body text-decoration-none account-action-card"
        >
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                    <i class="fa-regular fa-heart text-danger"></i>
                </div>

                <div class="min-w-0">
                    <h2 class="h6 fw-bold mb-1">
                        I miei preferiti
                    </h2>

                    <div class="text-muted small">
                        Visualizza e gestisci i prodotti che hai salvato.
                    </div>
                </div>

                <i class="fa-solid fa-chevron-right ms-auto text-muted small"></i>
            </div>
        </a>
    </div>

    <div class="col-12 col-lg-4">
        <a
            href="{{ route('storefront.catalog.index', $contextParams) }}"
            class="card border-0 shadow-sm h-100 text-body text-decoration-none account-action-card"
        >
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                    <i class="fa-solid fa-store"></i>
                </div>

                <div class="min-w-0">
                    <h2 class="h6 fw-bold mb-1">
                        Catalogo
                    </h2>

                    <div class="text-muted small">
                        Continua gli acquisti e consulta i prodotti disponibili.
                    </div>
                </div>

                <i class="fa-solid fa-chevron-right ms-auto text-muted small"></i>
            </div>
        </a>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4 d-flex align-items-center gap-3">
                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                    <i class="fa-regular fa-clock"></i>
                </div>

                <div class="min-w-0">
                    <h2 class="h6 fw-bold mb-1">
                        Ultimo accesso
                    </h2>

                    <div class="text-muted small">
                        {{ optional($customer?->last_login_at)->format('d/m/Y H:i') ?? '—' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h2 class="h6 mb-0">Dati azienda</h2>
            </div>

            <div class="card-body small">
                <div class="mb-2">
                    <strong>Ragione sociale:</strong><br>
                    {{ $customer?->ragsoanag_cg16 ?? '—' }}
                </div>

                @if($customer?->partiva_cg16)
                    <div class="mb-2">
                        <strong>Partita IVA:</strong><br>
                        {{ $customer->partiva_cg16 }}
                    </div>
                @endif

                @if($customer?->codfiscale_cg16)
                    <div class="mb-2">
                        <strong>Codice fiscale:</strong><br>
                        {{ $customer->codfiscale_cg16 }}
                    </div>
                @endif

                <div class="mb-2">
                    <strong>Codice cliente:</strong><br>
                    {{ $customer?->clifor_cg44 ?? '—' }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <h2 class="h6 mb-0">Contatti</h2>
            </div>

            <div class="card-body small">
                <div class="mb-2">
                    <strong>Email:</strong><br>
                    {{ $customer?->indemail_cg16 ?? '—' }}
                </div>

                @if($customer?->tel1num_cg16)
                    <div class="mb-2">
                        <strong>Telefono:</strong><br>
                        {{ $customer->tel1num_cg16 }}
                    </div>
                @endif

                @if($customer?->cellnum_cg16)
                    <div class="mb-2">
                        <strong>Cellulare:</strong><br>
                        {{ $customer->cellnum_cg16 }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <div class="text-muted small">
                    {{ $isAgentContext ? 'Contesto cliente attivo in questa tab.' : 'Sessione account cliente attiva.' }}
                </div>

                @if($isAgentContext)
                    <a
                        href="{{ route('storefront.agent.context.clear', $contextParams) }}"
                        class="btn btn-outline-secondary btn-sm"
                    >
                        <i class="fa-solid fa-arrow-left me-1"></i>
                        Torna all’agente
                    </a>
                @else
                    <form method="POST" action="{{ route('storefront.logout') }}">
                        @csrf
                        <button class="btn btn-outline-danger btn-sm">
                            <i class="fa-solid fa-right-from-bracket me-1"></i>
                            Logout
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection