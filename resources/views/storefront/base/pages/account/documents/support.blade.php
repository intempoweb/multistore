@extends($storefrontLayout ?? 'storefront.base.layouts.app')

@section('title', 'Ticket assistenza documento')

@section('content')
@php
    $rows = collect($rows ?? []);
    $documentNumber = method_exists($document, 'documentNumberForDisplay')
        ? $document->documentNumberForDisplay()
        : (trim((string) ($document->NUMSEZDOC_DO11 ?? '')) ?: '-');
    $documentType = method_exists($document, 'documentTypeForDisplay')
        ? $document->documentTypeForDisplay()
        : (trim((string) ($document->TIPODOCDECOD_MG36 ?? '')) ?: 'Documento');
    $showUrl = route('storefront.account.documents.show', array_merge(['document' => $document->NUMREG_CO99], $contextParams ?? []));
    $storeUrl = route('storefront.account.documents.support.store', array_merge(['document' => $document->NUMREG_CO99], $contextParams ?? []));
@endphp

<div class="container-fluid py-4 py-lg-5">
    <form
        action="{{ $storeUrl }}"
        method="POST"
        enctype="multipart/form-data"
        class="d-flex flex-column gap-4"
    >
        @csrf

        <input
            type="hidden"
            name="request_token"
            value="{{ $requestToken }}"
        >

        @if(!empty($agentContext))
            <input
                type="hidden"
                name="agent_context"
                value="{{ $agentContext }}"
            >
        @endif

        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <a
                    href="{{ $showUrl }}"
                    class="btn btn-sm btn-outline-secondary mb-3"
                >
                    <i class="fa-solid fa-arrow-left me-1"></i>
                    Torna al documento
                </a>

                <div class="text-uppercase small fw-bold text-muted">
                    Area documentale
                </div>

                <h1 class="h2 fw-bold mb-1">
                    Ticket assistenza
                </h1>

                <div class="text-muted">
                    {{ $documentType }} {{ $documentNumber }} · NUMREG {{ $document->NUMREG_CO99 }}
                </div>
            </div>

            <div class="align-self-lg-end">
                <button
                    type="submit"
                    class="btn btn-dark btn-lg"
                >
                    <i class="fa-regular fa-paper-plane me-1"></i>
                    Invia ticket
                </button>
            </div>
        </div>

        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1">
                    Controlla i campi evidenziati.
                </div>
                {{ $errors->first() }}
            </div>
        @endif

        <section class="row g-4">
            <div class="col-12 col-xl-8">
                <div class="border rounded-3 bg-white p-4">
                    <h2 class="h5 fw-bold mb-3">
                        Dettagli ticket
                    </h2>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="subject">
                                Oggetto
                            </label>
                            <input
                                id="subject"
                                name="subject"
                                value="{{ old('subject') }}"
                                class="form-control @error('subject') is-invalid @enderror"
                                required
                            >
                            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-semibold" for="contact_name">
                                Referente
                            </label>
                            <input
                                id="contact_name"
                                name="contact_name"
                                value="{{ old('contact_name', $contactDefaults['contact_name'] ?? '') }}"
                                class="form-control @error('contact_name') is-invalid @enderror"
                                required
                            >
                            @error('contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-semibold" for="contact_email">
                                Email
                            </label>
                            <input
                                id="contact_email"
                                type="email"
                                name="contact_email"
                                value="{{ old('contact_email', $contactDefaults['contact_email'] ?? '') }}"
                                class="form-control @error('contact_email') is-invalid @enderror"
                                required
                            >
                            @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-lg-6">
                            <label class="form-label fw-semibold" for="contact_phone">
                                Telefono
                            </label>
                            <input
                                id="contact_phone"
                                name="contact_phone"
                                value="{{ old('contact_phone', $contactDefaults['contact_phone'] ?? '') }}"
                                class="form-control"
                            >
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold" for="message">
                                Messaggio
                            </label>
                            <textarea
                                id="message"
                                name="message"
                                rows="6"
                                class="form-control @error('message') is-invalid @enderror"
                                required
                            >{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold" for="attachments">
                                Allegati
                            </label>
                            <input
                                id="attachments"
                                type="file"
                                name="attachments[]"
                                class="form-control @error('attachments') is-invalid @enderror"
                                multiple
                            >
                            <div class="form-text">
                                Puoi allegare fino a 5 file: PDF, immagini o documenti.
                            </div>
                            @error('attachments')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="border rounded-3 bg-white p-4 position-sticky" style="top: 1rem;">
                    <h2 class="h5 fw-bold mb-3">
                        Documento
                    </h2>

                    <dl class="row mb-4">
                        <dt class="col-5 text-muted">Tipo</dt>
                        <dd class="col-7 fw-semibold">{{ $documentType }}</dd>
                        <dt class="col-5 text-muted">Numero</dt>
                        <dd class="col-7 fw-semibold">{{ $documentNumber }}</dd>
                        <dt class="col-5 text-muted">Righe</dt>
                        <dd class="col-7 fw-semibold">{{ $rows->count() }}</dd>
                    </dl>

                    <div class="form-check">
                        <input
                            id="terms"
                            type="checkbox"
                            name="terms"
                            value="1"
                            class="form-check-input @error('terms') is-invalid @enderror"
                            required
                            @checked(old('terms'))
                        >
                        <label class="form-check-label" for="terms">
                            Confermo che la richiesta è collegata al documento selezionato.
                        </label>
                        @error('terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="border rounded-3 bg-white overflow-hidden">
            <div class="p-4 border-bottom">
                <h2 class="h5 fw-bold mb-1">
                    Righe collegate
                </h2>
                <div class="text-muted small">
                    Se utile, seleziona una o più righe documento a cui riferire il ticket.
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr class="small text-muted">
                            <th class="ps-4">Collega</th>
                            <th>Immagine</th>
                            <th>Articolo</th>
                            <th class="text-end pe-4">Q.tà doc.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            @php
                                $rowNumber = (string) ($row->PROGRIGA_DO30 ?? $loop->iteration);
                                $thumbnailUrl = method_exists($row, 'thumbnailUrl') ? $row->thumbnailUrl() : null;
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <input
                                        type="checkbox"
                                        name="items[{{ $rowNumber }}][selected]"
                                        value="1"
                                        class="form-check-input"
                                        @checked(old("items.$rowNumber.selected"))
                                    >
                                </td>
                                <td>
                                    @if($thumbnailUrl)
                                        <img src="{{ $thumbnailUrl }}" alt="{{ trim((string) ($row->DESCART_DO30 ?? '')) ?: 'Prodotto' }}" class="rounded border bg-light object-fit-contain" style="width: 56px; height: 56px;" loading="lazy">
                                    @else
                                        <div class="d-inline-flex align-items-center justify-content-center rounded border bg-light text-muted" style="width: 56px; height: 56px;">
                                            <i class="fa-regular fa-image"></i>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ trim((string) ($row->DESCART_DO30 ?? '')) ?: '-' }}</div>
                                    <code>{{ trim((string) ($row->CODART_MG66 ?? '')) ?: '-' }}</code>
                                </td>
                                <td class="text-end pe-4">
                                    {{ number_format((float) ($row->QTA1_DO30 ?? 0), 0, ',', '.') }} {{ trim((string) ($row->UM1_DO30 ?? '')) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </form>
</div>
@endsection
