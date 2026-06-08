@extends($storefrontLayout ?? 'storefront.base.layouts.app')

@section('title', 'Dettaglio documento')

@section('content')
<div class="container py-4 py-lg-5">

    <div class="mb-4">
        <a href="{{ route('storefront.account.documents.index') }}" class="btn btn-outline-secondary btn-sm">
            ← Torna ai documenti
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h1 class="h4 mb-0">
                {{ trim((string) ($document->TIPODOCDECOD_MG36 ?? 'Documento')) }}
                {{ trim((string) ($document->NUMSEZDOC_DO11 ?? '')) }}
            </h1>
        </div>

        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <strong>Ditta</strong><br>
                    {{ $document->DITTA_CG18 ?? '-' }}
                </div>

                <div class="col-md-3">
                    <strong>Cliente</strong><br>
                    {{ $document->CLIFOR_CG44 ?? '-' }}
                </div>

                <div class="col-md-3">
                    <strong>Data documento</strong><br>
                    {{ $document->DATADOC_DO11 ?? '-' }}
                </div>

                <div class="col-md-3">
                    <strong>Numero ERP</strong><br>
                    {{ $document->NUMREG_CO99 ?? '-' }}
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Righe documento</h2>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Riga</th>
                        <th>Codice</th>
                        <th>Descrizione</th>
                        <th>UM</th>
                        <th class="text-end">Q.tà</th>
                        <th class="text-end">Prezzo</th>
                        <th class="text-end">Netto</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($document->rows as $row)
                        <tr>
                            <td>{{ $row->PROGRIGA_DO30 ?? '-' }}</td>
                            <td>
                                <code>{{ trim((string) ($row->CODART_MG66 ?? '')) ?: '-' }}</code>
                            </td>
                            <td>{{ trim((string) ($row->DESCART_DO30 ?? '')) ?: '-' }}</td>
                            <td>{{ trim((string) ($row->UM1_DO30 ?? '')) ?: '-' }}</td>
                            <td class="text-end">
                                {{ number_format((float) ($row->QTA1_DO30 ?? 0), 0, ',', '.') }}
                            </td>
                            <td class="text-end">
                                € {{ number_format((float) ($row->PREZZO1_DO30 ?? 0), 3, ',', '.') }}
                            </td>
                            <td class="text-end fw-semibold">
                                € {{ number_format((float) ($row->IMPNETSCP_DO30 ?? 0), 3, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                Nessuna riga trovata.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection