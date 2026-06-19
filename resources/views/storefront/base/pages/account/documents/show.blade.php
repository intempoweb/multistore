@extends($storefrontLayout ?? 'storefront.base.layouts.app')

@section('title', 'Dettaglio documento')

@section('content')
@php
    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];
    $indexUrl = route('storefront.account.documents.index', $contextParams);
    $rows = collect($document->rows ?? []);

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

    $formatNumber = fn ($value, int $decimals = 0) => number_format((float) ($value ?? 0), $decimals, ',', '.');
    $formatMoney = fn ($value) => '€ ' . number_format((float) ($value ?? 0), 3, ',', '.');

    $documentType = method_exists($document, 'documentTypeForDisplay')
        ? $document->documentTypeForDisplay()
        : (trim((string) ($document->TIPODOCDECOD_MG36 ?? 'Documento')) ?: 'Documento');

    $documentNumber = method_exists($document, 'documentNumberForDisplay')
        ? $document->documentNumberForDisplay()
        : (trim((string) ($document->NUMSEZDOC_DO11 ?? '')) ?: '-');

    $rowsTotal = $rows->sum(fn ($row) => (float) ($row->IMPNETSCP_DO30 ?? 0));
    $quantityTotal = $rows->sum(fn ($row) => (float) ($row->QTA1_DO30 ?? 0));
@endphp

<div class="container-fluid py-4 py-lg-5">
    <div class="d-flex flex-column gap-4">
        <div>
            <a href="{{ $indexUrl }}" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i>
                Torna ai documenti
            </a>
        </div>

        <section class="border rounded-3 bg-white p-4 p-lg-5">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-4">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="badge text-bg-dark">{{ $documentType }}</span>
                        <span class="badge text-bg-light border">NUMREG {{ $document->NUMREG_CO99 ?? '-' }}</span>
                    </div>

                    <h1 class="display-6 fw-bold mb-2">
                        Documento {{ $documentNumber }}
                    </h1>

                    <p class="text-muted mb-0">
                        Dettaglio righe e valori del documento ERP collegato alla tua anagrafica cliente.
                    </p>
                </div>

                <div class="row g-3 flex-xl-nowrap">
                    <div class="col-6 col-xl-auto">
                        <div class="border rounded-3 px-3 py-2 h-100" style="min-width: 150px;">
                            <div class="small text-muted">Righe</div>
                            <div class="fs-4 fw-bold">{{ $rows->count() }}</div>
                        </div>
                    </div>

                    <div class="col-6 col-xl-auto">
                        <div class="border rounded-3 px-3 py-2 h-100" style="min-width: 150px;">
                            <div class="small text-muted">Quantita</div>
                            <div class="fs-4 fw-bold">{{ $formatNumber($quantityTotal) }}</div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-auto">
                        <div class="border rounded-3 px-3 py-2 h-100" style="min-width: 180px;">
                            <div class="small text-muted">Netto righe</div>
                            <div class="fs-4 fw-bold">{{ $formatMoney($rowsTotal) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-12 col-lg-3">
                <div class="border rounded-3 bg-white p-4 h-100">
                    <div class="small text-muted mb-1">Ditta</div>
                    <div class="fw-semibold">{{ $document->DITTA_CG18 ?? '-' }}</div>
                </div>
            </div>

            <div class="col-12 col-lg-3">
                <div class="border rounded-3 bg-white p-4 h-100">
                    <div class="small text-muted mb-1">Cliente</div>
                    <div class="fw-semibold">{{ $document->CLIFOR_CG44 ?? '-' }}</div>
                </div>
            </div>

            <div class="col-12 col-lg-3">
                <div class="border rounded-3 bg-white p-4 h-100">
                    <div class="small text-muted mb-1">Data documento</div>
                    <div class="fw-semibold">{{ $formatDate($document->DATADOC_DO11 ?? null) }}</div>
                </div>
            </div>

            <div class="col-12 col-lg-3">
                <div class="border rounded-3 bg-white p-4 h-100">
                    <div class="small text-muted mb-1">Numero ERP</div>
                    <div class="fw-semibold">{{ $document->NUMREG_CO99 ?? '-' }}</div>
                </div>
            </div>
        </section>

        <section class="border rounded-3 bg-white overflow-hidden">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 p-4 border-bottom">
                <div>
                    <h2 class="h5 fw-bold mb-1">Righe documento</h2>
                    <div class="text-muted small">Articoli, quantita, prezzo e netto riga.</div>
                </div>

                <span class="badge text-bg-light border align-self-start align-self-lg-center">
                    {{ $rows->count() }} righe
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="small text-muted">
                            <th class="ps-4">Riga</th>
                            <th>Codice</th>
                            <th>Descrizione</th>
                            <th>UM</th>
                            <th class="text-end">Q.ta</th>
                            <th class="text-end">Prezzo</th>
                            <th class="text-end pe-4">Netto</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td class="ps-4">
                                    <span class="badge text-bg-light border">
                                        {{ $row->PROGRIGA_DO30 ?? '-' }}
                                    </span>
                                </td>

                                <td>
                                    <code>{{ trim((string) ($row->CODART_MG66 ?? '')) ?: '-' }}</code>
                                </td>

                                <td>
                                    <div class="fw-semibold">
                                        {{ trim((string) ($row->DESCART_DO30 ?? '')) ?: '-' }}
                                    </div>
                                </td>

                                <td>{{ trim((string) ($row->UM1_DO30 ?? '')) ?: '-' }}</td>

                                <td class="text-end">
                                    {{ $formatNumber($row->QTA1_DO30 ?? 0) }}
                                </td>

                                <td class="text-end">
                                    {{ $formatMoney($row->PREZZO1_DO30 ?? 0) }}
                                </td>

                                <td class="text-end fw-semibold pe-4">
                                    {{ $formatMoney($row->IMPNETSCP_DO30 ?? 0) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="mb-3 text-muted">
                                        <i class="fa-regular fa-file-lines fa-3x"></i>
                                    </div>
                                    <div class="fw-semibold">Nessuna riga trovata</div>
                                    <div class="text-muted small">Il documento non contiene righe disponibili.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if($rows->isNotEmpty())
                        <tfoot>
                            <tr>
                                <th colspan="4" class="ps-4">Totale</th>
                                <th class="text-end">{{ $formatNumber($quantityTotal) }}</th>
                                <th></th>
                                <th class="text-end pe-4">{{ $formatMoney($rowsTotal) }}</th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
