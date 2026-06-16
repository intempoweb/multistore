@extends($storefrontLayout ?? 'storefront.base.layouts.app')

@section('title', 'Documenti')

@section('content')
@php
    $documents = $documents ?? collect();
    $filters = $filters ?? [];
    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];

    $formatDate = function ($value) {
        if (blank($value)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse(str_replace('/', '-', (string) $value))->format('d/m/Y');
        } catch (Throwable) {
            return (string) $value;
        }
    };

    $customerCode = $customer->clifor_cg44 ?: $customer->codice_cg16 ?: '-';
    $customerVat = trim((string) ($customer->partiva_cg16 ?? ''));
    $customerTaxCode = trim((string) ($customer->codfiscale_cg16 ?? ''));
    $paymentCode = trim((string) ($customer->codpag_cg62 ?? ''));
@endphp

<div class="container py-4 py-lg-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb" class="mb-2">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item">
                        <a href="{{ route('storefront.account.index', $contextParams) }}">Account</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Documenti</li>
                </ol>
            </nav>

            <h1 class="h3 mb-1">Area documentale</h1>
            <p class="text-muted mb-0">
                Consulta ordini, DDT, fatture, note e documenti collegati alla tua anagrafica cliente.
            </p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="p-4 p-lg-5 bg-light border-bottom">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
                    <div class="d-flex gap-3">
                        <div class="rounded-4 bg-dark text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:58px;height:58px;">
                            <i class="fa-solid fa-building fa-lg"></i>
                        </div>

                        <div>
                            <div class="text-muted small text-uppercase fw-semibold mb-1">Cliente B2B</div>
                            <h2 class="h4 mb-2">{{ $customer->ragsoanag_cg16 ?: '-' }}</h2>

                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge text-bg-dark">
                                    <i class="fa-solid fa-hashtag me-1"></i>
                                    Cliente {{ $customerCode }}
                                </span>

                                <span class="badge text-bg-light border">
                                    <i class="fa-solid fa-database me-1"></i>
                                    Ditta {{ $customer->ditta_cg18 ?: '-' }}
                                </span>

                                @if($customerVat !== '')
                                    <span class="badge text-bg-light border">
                                        <i class="fa-solid fa-receipt me-1"></i>
                                        P. IVA {{ $customerVat }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="text-lg-end">
                        <div class="text-muted small text-uppercase fw-semibold mb-1">Documenti ERP</div>
                        <div class="fs-4 fw-bold text-dark">
                            {{ $documents->count() }}
                        </div>
                        <div class="small text-muted">documenti in pagina</div>
                    </div>
                </div>
            </div>

            <div class="p-4 p-lg-5">
                <div class="row g-4">
                    <div class="col-12 col-xl-4">
                        <div class="h-100 rounded-4 border bg-white p-4">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="rounded-circle bg-dark text-white d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                                    <i class="fa-solid fa-id-card"></i>
                                </span>
                                <h3 class="h6 mb-0">Dati cliente</h3>
                            </div>

                            <div class="vstack gap-3">
                                <div>
                                    <div class="text-muted small">Ragione sociale</div>
                                    <div class="fw-semibold">{{ $customer->ragsoanag_cg16 ?: '-' }}</div>
                                </div>

                                <div>
                                    <div class="text-muted small">Codice cliente</div>
                                    <div class="fw-semibold">{{ $customerCode }}</div>
                                </div>

                                <div>
                                    <div class="text-muted small">Email</div>
                                    <div class="fw-semibold text-break">
                                        @if($customer->indemail_cg16)
                                            <a href="mailto:{{ $customer->indemail_cg16 }}" class="text-decoration-none text-dark">
                                                {{ $customer->indemail_cg16 }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12 col-md-6 col-xl-12">
                                        <div class="text-muted small">Partita IVA</div>
                                        <div class="fw-semibold">{{ $customerVat !== '' ? $customerVat : '-' }}</div>
                                    </div>

                                    <div class="col-12 col-md-6 col-xl-12">
                                        <div class="text-muted small">Codice fiscale</div>
                                        <div class="fw-semibold">{{ $customerTaxCode !== '' ? $customerTaxCode : '-' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-4">
                        <div class="h-100 rounded-4 border bg-white p-4">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="rounded-circle bg-dark text-white d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                                    <i class="fa-solid fa-user-tie"></i>
                                </span>
                                <h3 class="h6 mb-0">Agente di riferimento</h3>
                            </div>

                            <div class="vstack gap-3">
                                <div>
                                    <div class="text-muted small">Ragione sociale agente</div>
                                    <div class="fw-semibold">{{ $customer->ragsoanag_vwebdcg44 ?: '-' }}</div>
                                </div>

                                <div>
                                    <div class="text-muted small">Email agente</div>
                                    <div class="fw-semibold text-break">
                                        @if($customer->indeemail_vwebdcg44)
                                            <a href="mailto:{{ $customer->indeemail_vwebdcg44 }}" class="text-decoration-none text-dark">
                                                {{ $customer->indeemail_vwebdcg44 }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </div>
                                </div>

                                <div>
                                    <div class="text-muted small">Codice agente</div>
                                    <div class="fw-semibold">{{ $customer->agente_mg17 ?: '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-4">
                        <div class="h-100 rounded-4 border bg-white p-4">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="rounded-circle bg-dark text-white d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                                    <i class="fa-solid fa-credit-card"></i>
                                </span>
                                <h3 class="h6 mb-0">Pagamento e banca</h3>
                            </div>

                            <div class="vstack gap-3">
                                <div>
                                    <div class="text-muted small">Descrizione pagamento</div>
                                    <div class="fw-semibold">{{ $customer->descrizpag_cg62 ?: '-' }}</div>
                                </div>

                                <div>
                                    <div class="text-muted small">Codice pagamento</div>
                                    <div class="fw-semibold">{{ $paymentCode !== '' ? $paymentCode : '-' }}</div>
                                </div>

                                <div>
                                    <div class="text-muted small">Banca riferimento</div>
                                    <div class="fw-semibold">{{ $customer->desbanca_cg12_cg13 ?: '-' }}</div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="rounded-3 bg-light border p-3">
                                            <div class="text-muted small">ABI</div>
                                            <div class="fw-bold">{{ $customer->ccabi_mg35 ?: '-' }}</div>
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <div class="rounded-3 bg-light border p-3">
                                            <div class="text-muted small">CAB</div>
                                            <div class="fw-bold">{{ $customer->cccab_mg35 ?: '-' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pb-0">
            <div class="d-flex align-items-center gap-2">
                <span class="rounded-circle bg-light border d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                    <i class="fa-solid fa-filter text-muted"></i>
                </span>
                <div>
                    <h2 class="h6 mb-0">Filtra documenti</h2>
                    <div class="text-muted small">Cerca per numero, tipo o intervallo date</div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('storefront.account.documents.index', $contextParams) }}" class="row g-3 align-items-end">
                @if($agentContextId !== '')
                    <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                @endif
                <div class="col-12 col-md-3">
                    <label for="document_number" class="form-label small text-muted">Numero documento</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="fa-solid fa-hashtag text-muted"></i>
                        </span>
                        <input
                            type="text"
                            id="document_number"
                            name="document_number"
                            value="{{ $filters['document_number'] ?? '' }}"
                            class="form-control"
                            placeholder="Es. 14 / 3M"
                        >
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <label for="document_type" class="form-label small text-muted">Tipo documento</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="fa-solid fa-file-lines text-muted"></i>
                        </span>
                        <select id="document_type" name="document_type" class="form-select">
                            <option value="">Tutti</option>
                            @foreach(($documentTypes ?? []) as $type)
                                @php
                                    $typeValue = trim((string) $type);
                                @endphp
                                <option value="{{ $typeValue }}" @selected(($filters['document_type'] ?? '') === $typeValue)>
                                    {{ $typeValue }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-12 col-md-2">
                    <label for="date_from" class="form-label small text-muted">Dal</label>
                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="{{ $filters['date_from'] ?? '' }}"
                        class="form-control"
                    >
                </div>

                <div class="col-12 col-md-2">
                    <label for="date_to" class="form-label small text-muted">Al</label>
                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="{{ $filters['date_to'] ?? '' }}"
                        class="form-control"
                    >
                </div>

                <div class="col-12 col-md-2 d-grid gap-2">
                    <button type="submit" class="btn btn-dark">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>
                        Filtra
                    </button>

                    <a href="{{ route('storefront.account.documents.index', $contextParams) }}" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-rotate-left me-1"></i>
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex flex-column flex-md-row justify-content-between gap-2 p-4">
            <div class="d-flex align-items-center gap-2">
                <span class="rounded-circle bg-dark text-white d-inline-flex align-items-center justify-content-center" style="width:38px;height:38px;">
                    <i class="fa-solid fa-folder-open"></i>
                </span>
                <div>
                    <h2 class="h5 mb-1">Documenti</h2>
                    <div class="text-muted small">Storico documenti ERP per cliente B2B</div>
                </div>
            </div>

            <div class="text-muted small">
                In pagina: <span class="fw-semibold text-dark">{{ $documents->count() }}</span>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Numero</th>
                            <th class="text-end">NUMREG</th>
                            <th class="text-center">Ditta</th>
                            <th class="text-end">Azioni</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($documents as $document)
                            @php
                                $numreg = $document->NUMREG_CO99 ?? null;
                                $documentNumber = method_exists($document, 'documentNumberForDisplay')
                                    ? $document->documentNumberForDisplay()
                                    : (trim((string) ($document->NUMSEZDOC_DO11 ?? '')) ?: '-');
                                $documentType = method_exists($document, 'documentTypeForDisplay')
                                    ? $document->documentTypeForDisplay()
                                    : (trim((string) ($document->TIPODOCDECOD_MG36 ?? '')) ?: '-');
                            @endphp

                            <tr>
                                <td class="text-nowrap">
                                    <i class="fa-regular fa-calendar me-1 text-muted"></i>
                                    {{ $formatDate($document->DATADOC_DO11 ?? null) }}
                                </td>

                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis border">
                                        <i class="fa-regular fa-file-lines me-1"></i>
                                        {{ $documentType }}
                                    </span>
                                </td>

                                <td class="fw-semibold">
                                    {{ $documentNumber }}
                                </td>

                                <td class="text-end">
                                    <code>{{ $numreg ?: '-' }}</code>
                                </td>

                                <td class="text-center">
                                    <span class="badge text-bg-light border">
                                        {{ $document->DITTA_CG18 ?? '-' }}
                                    </span>
                                </td>

                                <td class="text-end text-nowrap">
                                    @if($numreg && Route::has('storefront.account.documents.show'))
                                        <a href="{{ route('storefront.account.documents.show', array_merge(['document' => $numreg], $contextParams)) }}" class="btn btn-sm btn-dark">
                                            <i class="fa-solid fa-eye me-1"></i>
                                            Dettaglio
                                        </a>
                                    @endif

                                    @if($numreg && Route::has('storefront.account.documents.rows'))
                                        <a href="{{ route('storefront.account.documents.rows', array_merge(['document' => $numreg], $contextParams)) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="fa-solid fa-list me-1"></i>
                                            Righe
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="mb-3 text-muted">
                                        <i class="fa-regular fa-folder-open fa-3x"></i>
                                    </div>
                                    <div class="fw-semibold">Nessun documento trovato</div>
                                    <div class="text-muted small">Modifica i filtri o riprova più tardi.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if(method_exists($documents, 'links'))
            <div class="card-footer bg-white border-0 p-4">
                {{ $documents->appends($contextParams)->links() }}
            </div>
        @endif
    </div>
</div>
@endsection