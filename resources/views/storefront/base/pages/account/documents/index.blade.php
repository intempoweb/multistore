@extends($storefrontLayout ?? 'storefront.base.layouts.app')

@section('title', 'Documenti')

@section('content')
@php
    $documents = $documents ?? collect();
    $filters = $filters ?? [];
    $documentTypes = collect($documentTypes ?? []);
    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];
    $indexUrl = route('storefront.account.documents.index', $contextParams);

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
    $paymentCode = trim((string) ($customer->codpag_cg62 ?? ''));
    $activeFiltersCount = (int) (
        ((trim((string) ($filters['document_number'] ?? '')) !== '') ? 1 : 0)
        + ((trim((string) ($filters['document_type'] ?? '')) !== '') ? 1 : 0)
        + ((trim((string) ($filters['date_from'] ?? '')) !== '') ? 1 : 0)
        + ((trim((string) ($filters['date_to'] ?? '')) !== '') ? 1 : 0)
    );
    $documentsCount = $documents->count();
@endphp

<div class="container-fluid py-4 py-lg-5">
    <div class="d-flex flex-column gap-4">
        <section class="border rounded-3 bg-white p-4 p-lg-5">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-4">
                <div class="flex-grow-1">
                    <nav aria-label="breadcrumb" class="mb-3">
                        <ol class="breadcrumb small mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ route('storefront.account.index', $contextParams) }}">Account</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Documenti</li>
                        </ol>
                    </nav>

                    <div class="text-uppercase small fw-bold text-primary mb-2">Area documentale</div>
                    <h1 class="display-6 fw-bold mb-2">Documenti cliente</h1>
                    <p class="text-muted mb-0">
                        Consulta documenti ERP, DDT, fatture e note collegate alla tua anagrafica.
                    </p>
                </div>

                <div class="row g-3 flex-xl-nowrap">
                    <div class="col-6 col-xl-auto">
                        <div class="storefront-document-stat border rounded-3 px-3 py-2 h-100">
                            <div class="small text-muted">In pagina</div>
                            <div class="fs-4 fw-bold">{{ $documentsCount }}</div>
                        </div>
                    </div>

                    <div class="col-6 col-xl-auto">
                        <div class="storefront-document-stat border rounded-3 px-3 py-2 h-100">
                            <div class="small text-muted">Filtri attivi</div>
                            <div class="fs-4 fw-bold">{{ $activeFiltersCount }}</div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-auto">
                        <div class="storefront-document-stat-xwide border rounded-3 px-3 py-2 h-100">
                            <div class="small text-muted">Cliente</div>
                            <div class="fw-bold text-truncate">{{ $customerCode }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-12 col-xl-4">
                <div class="border rounded-3 bg-white p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="fa-regular fa-building text-primary"></i>
                        <h2 class="h6 mb-0">Cliente</h2>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        <div>
                            <div class="small text-muted">Ragione sociale</div>
                            <div class="fw-semibold">{{ $customer->ragsoanag_cg16 ?: '-' }}</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <div class="small text-muted">Codice</div>
                                <div class="fw-semibold">{{ $customerCode }}</div>
                            </div>

                            <div class="col-6">
                                <div class="small text-muted">Ditta</div>
                                <div class="fw-semibold">{{ $customer->ditta_cg18 ?: '-' }}</div>
                            </div>
                        </div>

                        <div>
                            <div class="small text-muted">Partita IVA</div>
                            <div class="fw-semibold">{{ $customerVat !== '' ? $customerVat : '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="border rounded-3 bg-white p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="fa-regular fa-user text-primary"></i>
                        <h2 class="h6 mb-0">Agente</h2>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        <div>
                            <div class="small text-muted">Ragione sociale</div>
                            <div class="fw-semibold">{{ $customer->ragsoanag_vwebdcg44 ?: '-' }}</div>
                        </div>

                        <div>
                            <div class="small text-muted">Email</div>
                            <div class="fw-semibold text-break">
                                @if($customer->indeemail_vwebdcg44)
                                    <a href="mailto:{{ $customer->indeemail_vwebdcg44 }}" class="text-decoration-none">
                                        {{ $customer->indeemail_vwebdcg44 }}
                                    </a>
                                @else
                                    -
                                @endif
                            </div>
                        </div>

                        <div>
                            <div class="small text-muted">Codice agente</div>
                            <div class="fw-semibold">{{ $customer->agente_mg17 ?: '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="border rounded-3 bg-white p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="fa-regular fa-credit-card text-primary"></i>
                        <h2 class="h6 mb-0">Pagamento</h2>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        <div>
                            <div class="small text-muted">Condizione</div>
                            <div class="fw-semibold">{{ $customer->descrizpag_cg62 ?: '-' }}</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <div class="small text-muted">Codice</div>
                                <div class="fw-semibold">{{ $paymentCode !== '' ? $paymentCode : '-' }}</div>
                            </div>

                            <div class="col-6">
                                <div class="small text-muted">Banca</div>
                                <div class="fw-semibold text-truncate">{{ $customer->desbanca_cg12_cg13 ?: '-' }}</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <div class="small text-muted">ABI</div>
                                <div class="fw-semibold">{{ $customer->ccabi_mg35 ?: '-' }}</div>
                            </div>

                            <div class="col-6">
                                <div class="small text-muted">CAB</div>
                                <div class="fw-semibold">{{ $customer->cccab_mg35 ?: '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border rounded-3 bg-white p-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="h5 fw-bold mb-1">Filtri</h2>
                    <div class="text-muted small">Cerca per numero, tipo documento o intervallo date.</div>
                </div>

                @if($activeFiltersCount > 0)
                    <a href="{{ $indexUrl }}" class="btn btn-sm btn-outline-secondary align-self-start">
                        <i class="fa-solid fa-rotate-left me-1"></i>
                        Reset filtri
                    </a>
                @endif
            </div>

            <form method="GET" action="{{ $indexUrl }}" class="row g-3 align-items-end">
                @if($agentContextId !== '')
                    <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                @endif

                <div class="col-12 col-lg-3">
                    <label for="document_number" class="form-label small fw-semibold text-muted">Numero documento</label>
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
                            placeholder="Numero o sezione"
                        >
                    </div>
                </div>

                <div class="col-12 col-lg-3">
                    <label for="document_type" class="form-label small fw-semibold text-muted">Tipo documento</label>
                    <select id="document_type" name="document_type" class="form-select">
                        <option value="">Tutti i documenti</option>
                        @foreach($documentTypes as $type)
                            @php
                                $typeValue = trim((string) $type);
                            @endphp

                            <option value="{{ $typeValue }}" @selected(($filters['document_type'] ?? '') === $typeValue)>
                                {{ $typeValue }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-6 col-lg-2">
                    <label for="date_from" class="form-label small fw-semibold text-muted">Dal</label>
                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="{{ $filters['date_from'] ?? '' }}"
                        class="form-control"
                    >
                </div>

                <div class="col-12 col-md-6 col-lg-2">
                    <label for="date_to" class="form-label small fw-semibold text-muted">Al</label>
                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="{{ $filters['date_to'] ?? '' }}"
                        class="form-control"
                    >
                </div>

                <div class="col-12 col-lg-2 d-grid">
                    <button type="submit" class="btn btn-dark">
                        <i class="fa-solid fa-magnifying-glass me-1"></i>
                        Filtra
                    </button>
                </div>
            </form>
        </section>

        <section class="border rounded-3 bg-white overflow-hidden">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 p-4 border-bottom">
                <div>
                    <h2 class="h5 fw-bold mb-1">Archivio documenti</h2>
                    <div class="text-muted small">Storico ERP disponibile per il cliente selezionato.</div>
                </div>

                <span class="badge text-bg-light border align-self-start align-self-lg-center">
                    {{ $documentsCount }} documenti in pagina
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="small text-muted">
                            <th class="ps-4">Documento</th>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th class="text-end">NUMREG</th>
                            <th class="text-center">Ditta</th>
                            <th class="text-end pe-4">Azioni</th>
                        </tr>
                    </thead>

                    <tbody>
                        @if($documents->count() === 0)
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="mb-3 text-muted">
                                        <i class="fa-regular fa-folder-open fa-3x"></i>
                                    </div>
                                    <div class="fw-semibold">Nessun documento trovato</div>
                                    <div class="text-muted small">Modifica i filtri o riprova piu tardi.</div>
                                </td>
                            </tr>
                        @else
                            @foreach($documents as $document)
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
                                    <td class="ps-4">
                                        <div class="fw-semibold">{{ $documentNumber }}</div>
                                        <div class="small text-muted">Cliente {{ $document->CLIFOR_CG44 ?? $customerCode }}</div>
                                    </td>

                                    <td class="text-nowrap">
                                        {{ $formatDate($document->DATADOC_DO11 ?? null) }}
                                    </td>

                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis border">
                                            {{ $documentType }}
                                        </span>
                                    </td>

                                    <td class="text-end">
                                        <code>{{ $numreg ?: '-' }}</code>
                                    </td>

                                    <td class="text-center">
                                        <span class="badge text-bg-light border">
                                            {{ $document->DITTA_CG18 ?? '-' }}
                                        </span>
                                    </td>

                                    <td class="text-end text-nowrap pe-4">
                                        @if($numreg && Route::has('storefront.account.documents.show'))
                                            <a
                                                href="{{ route('storefront.account.documents.show', array_merge(['document' => $numreg], $contextParams)) }}"
                                                class="btn btn-sm btn-dark"
                                            >
                                                <i class="fa-regular fa-eye me-1"></i>
                                                Apri
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>

            @if(method_exists($documents, 'links'))
                <div class="p-4 border-top">
                    {{ $documents->appends($contextParams)->links() }}
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
