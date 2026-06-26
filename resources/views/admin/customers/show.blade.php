@extends('layouts.admin')

@section('title', 'Cliente')
@section('breadcrumb', 'Clienti / Dettaglio')

@section('content')
@php
    $customerName = trim((string) ($customer->ragsoanag_cg16 ?? ''));
    if ($customerName === '') {
        $customerName = 'Cliente ' . $customer->clifor_cg44;
    }

    $webContact = trim(collect([
        $customer->nomeconnweb,
        $customer->cognomeconnweb,
    ])->filter()->implode(' '));

    $storeNames = collect($customer->store_names ?? [])->filter()->values();
    $shippingAddresses = collect($shippingAddresses ?? [])->values();

    $defaultListinoId = $customer->customer_default_listino_id
        ? (int) $customer->customer_default_listino_id
        : ($customer->codlistinoded ? (int) $customer->codlistinoded : null);

    $listinoAssignments = collect($customerListinoAssignments ?? []);
    $listinoSummariesById = collect($listinoSummaries ?? [])->keyBy(function ($row) {
        return (int) ($row->listino_id ?? 0);
    });

    $customerAssignedListinoIds = collect($customer->customer_effective_listino_ids ?? [])
        ->filter()
        ->map(fn ($value) => (int) $value)
        ->filter(fn ($value) => $value > 0)
        ->unique()
        ->values();


    $agentLabel = trim((string) ($customer->agente_mg17 ?? ''));
    $agentWebName = trim((string) ($customer->ragsoanag_vwebdcg44 ?? ''));
    $agentWebEmail = trim((string) ($customer->indeemail_vwebdcg44 ?? ''));
    $primaryEmail = $customer->indemail_cg16 ?: ($customer->email_pec_cg16 ?: '-');
    $vatLabel = $customer->partiva_cg16 ?: ($customer->codfiscale_cg16 ?: '-');

    $listinoIdsToShow = $listinoAssignments
        ->map(fn ($row) => (int) ($row->listino_id ?? 0))
        ->filter(fn ($value) => $value > 0)
        ->unique()
        ->values();

    if ($listinoIdsToShow->isEmpty() && $customerAssignedListinoIds->isNotEmpty()) {
        $listinoIdsToShow = $customerAssignedListinoIds;
    }

    if ($listinoIdsToShow->isEmpty() && $defaultListinoId) {
        $listinoIdsToShow = collect([$defaultListinoId]);
    }

    $legalAddress = collect([
        trim((string) ($customer->indirizzo_cg16 ?? '')),
        trim(collect([
            $customer->cap_cg16,
            $customer->citta_cg16,
            $customer->prov_cg16,
        ])->filter()->implode(' ')),
    ])->filter()->implode(', ');
@endphp

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
    <div>
        <div class="text-muted small mb-1">
            <a href="{{ route('admin.customers.index') }}" class="text-decoration-none">Clienti</a>
            <span class="mx-1">/</span>
            {{ $customer->clifor_cg44 }}
        </div>

        <h1 class="h3 mb-1">{{ $customerName }}</h1>

        <div class="text-muted small d-flex flex-wrap gap-2 align-items-center mb-2">
            <span><strong>{{ $store->name }}</strong></span>
            <span>•</span>
            <span>Ditta {{ $customer->ditta_cg18 }}</span>
            <span>•</span>
            <span>Cliente {{ $customer->clifor_cg44 }}</span>
            @if($customer->codice_cg16)
                <span>•</span>
                <span>Cod. ERP {{ $customer->codice_cg16 }}</span>
            @endif
            @if($customer->tipocf_cg44 !== null)
                <span>•</span>
                <span>Tipo CF {{ $customer->tipocf_cg44 }}</span>
            @endif
        </div>

        <div class="d-flex flex-wrap gap-2">
           @if($listinoIdsToShow->isNotEmpty())
                @foreach($listinoIdsToShow as $headerListinoId)
                    <span class="badge {{ $listinoAssignments->isEmpty() ? 'text-bg-dark' : 'text-bg-light border text-dark' }}">
                        {{ $listinoAssignments->isEmpty() ? 'Listino default' : 'Listino associato' }} {{ $headerListinoId }}
                    </span>
                @endforeach
            @endif

            <span class="badge {{ $customer->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                {{ $customer->is_active ? 'Attivo' : 'Disattivo' }}
            </span>

            <span class="badge {{ $customer->codrifalf_mg19 === 'PT' ? 'text-bg-success' : 'text-bg-secondary' }}">
                {{ $customer->codrifalf_mg19 === 'PT' ? 'Web abilitato' : 'Non web' }}
            </span>

            <span class="badge text-bg-light border text-dark">
                {{ number_format($shippingAddresses->count(), 0, ',', '.') }} indirizzi spedizione
            </span>

            <span class="badge text-bg-light border text-dark">
                {{ number_format($listinoIdsToShow->count(), 0, ',', '.') }} listini
            </span>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        @if($customer->canReceiveMagicLink())
            <form method="POST" action="{{ route('admin.customers.login-as', $customer) }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-right-to-bracket me-1"></i>
                    Accedi come cliente
                </button>
            </form>
        @else
            <button type="button" class="btn btn-outline-secondary" disabled title="Cliente non abilitato al login web">
                <i class="fa-solid fa-right-to-bracket me-1"></i>
                Accedi come cliente
            </button>
        @endif

        <a href="{{ route('admin.customer-visible-groups.index', ['tipocf' => $customer->tipocf_cg44, 'clifor' => $customer->clifor_cg44]) }}"
           class="btn btn-outline-secondary">
            <i class="fa-solid fa-users-viewfinder me-1"></i>
            Gruppi visibili
        </a>

        <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-dark">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Torna alla lista
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xxl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <strong>Riepilogo cliente</strong>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="text-muted small">Agente</div>
                        <div class="fw-semibold">{{ $agentLabel !== '' ? $agentLabel : '-' }}</div>
                    </div>

                    <div class="col-6">
                        <div class="text-muted small">Listino effettivo</div>
                        <div class="fw-semibold">{{ $listinoIdsToShow->isNotEmpty() ? $listinoIdsToShow->implode(', ') : '-' }}</div>
                    </div>

                    <div class="col-6">
                        <div class="text-muted small">Email principale</div>
                        <div class="fw-semibold text-break">{{ $primaryEmail }}</div>
                    </div>

                    <div class="col-6">
                        <div class="text-muted small">P.IVA / CF</div>
                        <div class="fw-semibold">{{ $vatLabel }}</div>
                    </div>

                    <div class="col-6">
                        <div class="text-muted small">Agente web</div>
                        <div class="fw-semibold text-break">{{ $agentWebName !== '' ? $agentWebName : '-' }}</div>
                    </div>

                    <div class="col-6">
                        <div class="text-muted small">Email agente web</div>
                        <div class="fw-semibold text-break">{{ $agentWebEmail !== '' ? $agentWebEmail : '-' }}</div>
                    </div>

                    <div class="col-12">
                        <div class="text-muted small">Store associati</div>
                        <div class="fw-semibold">{{ $storeNames->isNotEmpty() ? $storeNames->implode(', ') : '-' }}</div>
                    </div>

                    <div class="col-6">
                        <div class="text-muted small">Ultima sync ERP</div>
                        <div class="fw-semibold">{{ optional($customer->erp_last_seen_at)->format('d/m/Y H:i') ?: '-' }}</div>
                    </div>

                    <div class="col-6">
                        <div class="text-muted small">ERP lastchange</div>
                        <div class="fw-semibold">{{ optional($customer->erp_lastchange)->format('d/m/Y') ?: '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xxl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <strong>Anagrafica</strong>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Ragione sociale</div>
                        <div class="fw-semibold">{{ $customer->ragsoanag_cg16 ?: '-' }}</div>
                    </div>

                    <div class="col-md-3">
                        <div class="text-muted small">Partita IVA</div>
                        <div class="fw-semibold">{{ $customer->partiva_cg16 ?: '-' }}</div>
                    </div>

                    <div class="col-md-3">
                        <div class="text-muted small">Codice fiscale</div>
                        <div class="fw-semibold">{{ $customer->codfiscale_cg16 ?: '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Email</div>
                        <div class="fw-semibold text-break">{{ $customer->indemail_cg16 ?: '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">PEC</div>
                        <div class="fw-semibold text-break">{{ $customer->email_pec_cg16 ?: '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Sito web</div>
                        <div class="fw-semibold text-break">{{ $customer->indweb_cg16 ?: '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Telefono 1</div>
                        <div class="fw-semibold">{{ $customer->tel1num_cg16 ?: '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Telefono 2</div>
                        <div class="fw-semibold">{{ $customer->tel2num_cg16 ?: '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Cellulare</div>
                        <div class="fw-semibold">{{ $customer->cellnum_cg16 ?: '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Contatto web</div>
                        <div class="fw-semibold">{{ $webContact !== '' ? $webContact : '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Codice pagamento</div>
                        <div class="fw-semibold">{{ $customer->codpag_cg62 ?: '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Descrizione pagamento</div>
                        <div class="fw-semibold">{{ $customer->descrizpag_cg62 ?: '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Codice IVA</div>
                        <div class="fw-semibold">{{ $customer->codice_cg28 ?: '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Descrizione IVA</div>
                        <div class="fw-semibold">{{ $customer->descr_cg28 ?: '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">% IVA</div>
                        <div class="fw-semibold">
                            {{ $customer->perciva_cg28 !== null ? number_format((float) $customer->perciva_cg28, 2, ',', '.') : '-' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0">
                <strong>Indirizzo legale</strong>
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="text-muted small">Ragione sociale</div>
                        <div class="fw-semibold">{{ $customer->ragsoanag_cg16 ?: '-' }}</div>
                    </div>

                    <div class="col-12">
                        <div class="text-muted small">Indirizzo completo</div>
                        <div class="fw-semibold">{{ $legalAddress !== '' ? $legalAddress : '-' }}</div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">CAP</div>
                        <div class="fw-semibold">{{ $customer->cap_cg16 ?: '-' }}</div>
                    </div>

                    <div class="col-md-5">
                        <div class="text-muted small">Città</div>
                        <div class="fw-semibold">{{ $customer->citta_cg16 ?: '-' }}</div>
                    </div>

                    <div class="col-md-3">
                        <div class="text-muted small">Provincia</div>
                        <div class="fw-semibold">{{ $customer->prov_cg16 ?: '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                <div>
                    <strong>Listini associati</strong>
                    <div class="text-muted small">Mostra i listini ERP associati; il default viene mostrato solo se non ci sono associazioni.</div>
                </div>

                <span class="badge rounded-pill text-bg-light border px-3 py-2">
                    {{ number_format($listinoIdsToShow->count(), 0, ',', '.') }} listini
                </span>
            </div>

            <div class="card-body p-0">
                @if($listinoIdsToShow->isEmpty())
                    <div class="p-4 text-muted">Nessun listino associato a questo cliente.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Listino</th>
                                    <th>Tipo</th>
                                    <th>Righe</th>
                                    <th>Prodotti</th>
                                    <th>Range prezzi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($listinoIdsToShow as $assignedListinoId)
                                    @php
                                        $summary = $listinoSummariesById->get($assignedListinoId);
                                        $isDefault = $listinoAssignments->isEmpty() && $defaultListinoId !== null && $assignedListinoId === $defaultListinoId;
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $assignedListinoId }}</td>
                                        <td>
                                            @if($isDefault)
                                                <span class="badge text-bg-dark">Default</span>
                                            @else
                                                <span class="badge text-bg-light border text-dark">Associato</span>
                                            @endif
                                        </td>
                                        <td>{{ number_format((int) ($summary->rows_count ?? 0), 0, ',', '.') }}</td>
                                        <td>{{ number_format((int) ($summary->products_count ?? 0), 0, ',', '.') }}</td>
                                        <td>
                                            @if(isset($summary->min_price) || isset($summary->max_price))
                                                {{ isset($summary->min_price) ? number_format((float) $summary->min_price, 2, ',', '.') . ' €' : 'N/D' }}
                                                <span class="mx-1">—</span>
                                                {{ isset($summary->max_price) ? number_format((float) $summary->max_price, 2, ',', '.') . ' €' : 'N/D' }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
        <div>
            <strong>Indirizzi di spedizione</strong>
            <div class="text-muted small">Destinazioni ERP disponibili per il checkout del cliente.</div>
        </div>

        <span class="badge rounded-pill text-bg-light border px-3 py-2">
            {{ number_format($shippingAddresses->count(), 0, ',', '.') }} indirizzi
        </span>
    </div>

    <div class="card-body">
        @if($shippingAddresses->isEmpty())
            <div class="text-muted">Nessun indirizzo di spedizione sincronizzato.</div>
        @else
            <div class="row g-3">
                @foreach($shippingAddresses as $address)
                    @php
                        $shippingPhone = $address->desttel_mg22 ?: ($address->destcell_mg22 ?: '-');
                        $shippingFullAddress = collect([
                            trim((string) ($address->destind_mg22 ?? '')),
                            trim(collect([
                                $address->destcap_mg22,
                                $address->destcitta_mg22,
                                $address->destprov_mg22,
                            ])->filter()->implode(' ')),
                        ])->filter()->implode(', ');
                    @endphp
                    <div class="col-12 col-xl-6">
                        <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <div class="fw-semibold">{{ $address->destragsoc_mg22 ?: 'Destinazione ' . $address->coddestin_mg22 }}</div>
                                    <div class="text-muted small">Codice destinazione {{ $address->coddestin_mg22 }}</div>
                                </div>
                            </div>

                            <div class="small">
                                <div class="mb-2">
                                    <div class="text-muted">Indirizzo</div>
                                    <div class="fw-semibold">{{ $shippingFullAddress !== '' ? $shippingFullAddress : '-' }}</div>
                                </div>

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="text-muted">Email</div>
                                        <div class="fw-semibold text-break">{{ $address->destemail_mg22 ?: '-' }}</div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="text-muted">Telefono</div>
                                        <div class="fw-semibold">{{ $shippingPhone }}</div>
                                    </div>
                                </div>

                                @if($address->destnote_mg22)
                                    <div class="mt-2">
                                        <div class="text-muted">Note</div>
                                        <div class="fw-semibold">{{ $address->destnote_mg22 }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                <div>
                    <strong>Gruppi ACL cliente</strong>
                    <div class="text-muted small">Gruppi commerciali e visibilità associati.</div>
                </div>
                <div class="small mt-2 d-flex flex-wrap gap-3 text-muted">
    <span class="d-inline-flex align-items-center gap-2">
        <span class="rounded-circle d-inline-block bg-success" style="width: 10px; height: 10px;"></span>
        Attivo
    </span>
    <span class="d-inline-flex align-items-center gap-2">
        <span class="rounded-circle d-inline-block bg-secondary" style="width: 10px; height: 10px;"></span>
        Non attivo
    </span>
</div>

                <span class="badge rounded-pill text-bg-light border px-3 py-2">
                    {{ number_format($visibleGroups->count(), 0, ',', '.') }} gruppi
                </span>
            </div>

            <div class="card-body">
                @if($visibleGroups->isEmpty())
                    <div class="text-muted">Nessun gruppo ACL associato.</div>
                @else
                   <div class="d-flex flex-wrap gap-2">
    @foreach($visibleGroups as $group)
        @php
            $groupIsActive = (bool) ($group->is_active ?? false);
        @endphp

        <span class="badge text-bg-light border text-dark d-inline-flex align-items-center gap-2 px-3 py-2">
            <span
                class="rounded-circle d-inline-block {{ $groupIsActive ? 'bg-success' : 'bg-secondary' }}"
                style="width: 10px; height: 10px;"
            ></span>

            <span>
                {{ $group->codice_xx32 }} - {{ $group->descrizione_xx32 }}
                <span class="ms-1 text-muted">({{ $group->site_flag_label ?? ($group->flg_b2b_b2c_webt81 ?? '-') }})</span>
            </span>
        </span>
    @endforeach
</div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                <div>
                    <strong>Clienti con listini in comune</strong>
                    <div class="text-muted small">Anagrafiche che condividono almeno un listino del cliente corrente.</div>
                </div>

                <span class="badge rounded-pill text-bg-light border px-3 py-2">
                    {{ number_format($listinoCustomers->count(), 0, ',', '.') }} clienti
                </span>
            </div>

            <div class="card-body">
                @if($listinoCustomers->isEmpty())
                    <div class="text-muted">Nessun altro cliente associato a questi listini.</div>
                @else
                    <div class="row g-3">
                        @foreach($listinoCustomers as $listinoCustomer)
                            @php
                                $otherAssignmentIds = collect($listinoCustomer->customer_listino_assignments ?? [])
                                    ->map(fn ($row) => (int) ($row->listino_id ?? $row['listino_id'] ?? 0))
                                    ->filter(fn ($value) => $value > 0)
                                    ->unique()
                                    ->values();

                                $otherListinoIds = $otherAssignmentIds->isNotEmpty()
                                    ? $otherAssignmentIds
                                    : collect($listinoCustomer->customer_effective_listino_ids ?? [])
                                        ->map(fn ($value) => (int) $value)
                                        ->filter(fn ($value) => $value > 0)
                                        ->unique()
                                        ->values();

                                if ($otherAssignmentIds->isNotEmpty()) {
                                    $otherListinoIds = $otherAssignmentIds;
                                }
                            @endphp
                            <div class="col-12">
                                <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3">
                                        <div>
                                            <div class="fw-semibold">{{ $listinoCustomer->ragsoanag_cg16 ?: '-' }}</div>
                                            <div class="text-muted small">Cliente {{ $listinoCustomer->clifor_cg44 }}</div>
                                            @if($listinoCustomer->codice_cg16)
                                                <div class="text-muted small">Cod. ERP {{ $listinoCustomer->codice_cg16 }}</div>
                                            @endif
                                            <div class="text-muted small mt-1">{{ $listinoCustomer->store_names_text ?: '-' }}</div>
                                        </div>

                                        <div class="d-flex flex-column align-items-lg-end gap-2">
                                            <div class="d-flex flex-wrap gap-1 justify-content-lg-end">
                                                @if($otherListinoIds->isNotEmpty())
                                                    @foreach($otherListinoIds as $otherListinoId)
                                                        <span class="badge text-bg-light border text-dark">{{ $otherListinoId }}</span>
                                                    @endforeach
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </div>

                                            @if($listinoCustomer->is_active)
                                                <span class="badge text-bg-success">Attivo</span>
                                            @else
                                                <span class="badge text-bg-secondary">Disattivo</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
