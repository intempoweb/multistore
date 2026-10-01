@extends($storefrontLayout ?? 'storefront.base.layouts.app')

@section('title', 'Dettaglio documento')

@section('content')
@php
    $agentContextId = (string) request('agent_context', '');

    $contextParams = $agentContextId !== ''
        ? ['agent_context' => $agentContextId]
        : [];

    $indexUrl = route(
        'storefront.account.documents.index',
        $contextParams
    );

    $rows = collect($document->rows ?? []);

    $formatDate = function ($value) {
        if (blank($value)) {
            return '-';
        }

        $date = trim((string) $value);

        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
                return \Carbon\Carbon::createFromFormat(
                    'd/m/Y',
                    $date
                )->format('d/m/Y');
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
                return \Carbon\Carbon::parse($date)
                    ->format('d/m/Y');
            }

            return $date;
        } catch (Throwable) {
            return $date;
        }
    };

    $formatNumber = fn (
        $value,
        int $decimals = 0
    ) => number_format(
        (float) ($value ?? 0),
        $decimals,
        ',',
        '.'
    );

    $formatQuantity = function ($value) {
        if ($value === null || $value === '') {
            return '-';
        }

        $number = (float) $value;
        $decimals = floor($number) == $number ? 0 : 2;

        return number_format(
            $number,
            $decimals,
            ',',
            '.'
        );
    };

    $priceDecimals = $store?->priceDecimals() ?? 2;

    $formatMoney = fn ($value) => '€ ' . number_format(
        (float) ($value ?? 0),
        $priceDecimals,
        ',',
        '.'
    );

    $documentType = method_exists(
        $document,
        'documentTypeForDisplay'
    )
        ? $document->documentTypeForDisplay()
        : (
            trim(
                (string) (
                    $document->TIPODOCDECOD_MG36
                    ?? 'Documento'
                )
            ) ?: 'Documento'
        );

    $documentTypeNormalized = strtoupper(
        trim(
            (string) (
                $document->TIPODOCDECOD_MG36
                ?? $documentType
            )
        )
    );

    $isOrder = $documentTypeNormalized === 'ORDINE';

    $documentNumber = method_exists(
        $document,
        'documentNumberForDisplay'
    )
        ? $document->documentNumberForDisplay()
        : (
            trim(
                (string) (
                    $document->NUMSEZDOC_DO11
                    ?? ''
                )
            ) ?: '-'
        );

    $rowsTotal = $rows->sum(
        fn ($row) => (float) ($row->IMPNETSCP_DO30 ?? 0)
    );

    $quantityTotal = $rows->sum(
        fn ($row) => (float) ($row->QTA1_DO30 ?? 0)
    );

    $productRows = $rows->filter(
        fn ($row) => filled(
            trim((string) ($row->CODART_MG66 ?? ''))
        )
    );

    $documentReturns = collect($documentReturns ?? []);
    $supportTickets = collect($supportTickets ?? []);

    /*
     * Fulfillment ERP
     */
    $fulfillment = $document->getAttribute(
        'document_fulfillment'
    );

    $fulfillment = is_array($fulfillment)
        ? $fulfillment
        : [];

    $fulfillmentRows = collect(
        $fulfillment['rows'] ?? []
    )->values();

    $fulfillmentStates = collect(
        $fulfillment['states'] ?? []
    )
        ->filter()
        ->unique()
        ->values();

    $fulfillmentDdts = collect(
        $fulfillment['ddts'] ?? []
    )
        ->filter()
        ->unique()
        ->values();

    $fulfillmentInvoices = collect(
        $fulfillment['invoices'] ?? []
    )
        ->filter()
        ->unique()
        ->values();

    /*
     * Spedizioni realmente visibili al cliente.
     *
     * Il resolver può caricare riferimenti DO33 appartenenti a più ditte
     * perché uno stesso ordine può contenere righe provenienti da società
     * ERP differenti.
     *
     * Questo è necessario per ricostruire correttamente lo stato delle
     * singole righe dell'ordine.
     *
     * Nel riepilogo logistico del documento, invece, mostriamo solamente
     * le spedizioni appartenenti alla ditta della testata visualizzata.
     */
    $documentDitta = (int) (
        $document->DITTA_CG18
        ?? 0
    );

    $shipments = collect(
        $fulfillment['shipments'] ?? []
    )
        ->filter(function ($shipment) use ($documentDitta) {
            $shipmentDitta = (int) (
                $shipment['ditta']
                ?? 0
            );

            if (
                $documentDitta > 0
                && $shipmentDitta !== $documentDitta
            ) {
                return false;
            }

            $carrierCode = trim(
                (string) (
                    $shipment['carrier_code']
                    ?? ''
                )
            );

            $courierDocument = trim(
                (string) (
                    $shipment['courier_document']
                    ?? ''
                )
            );

            $courierSection = trim(
                (string) (
                    $shipment['courier_section']
                    ?? ''
                )
            );

            $parcelId = trim(
                (string) (
                    $shipment['parcel_id']
                    ?? ''
                )
            );

            $parcels = $shipment['parcels']
                ?? null;

            $hasCarrier = $carrierCode !== '';

            $hasCourierReference = $courierDocument !== ''
                && $courierDocument !== '0'
                && $courierSection !== ''
                && $courierSection !== '-';

            $hasParcelId = $parcelId !== '';

            $hasParcels = $parcels !== null
                && (float) $parcels > 0;

            return $hasCarrier
                || $hasCourierReference
                || $hasParcelId
                || $hasParcels;
        })
        ->values();

    $hasFulfillment = $fulfillmentRows->isNotEmpty()
        || $fulfillmentStates->isNotEmpty()
        || $fulfillmentDdts->isNotEmpty()
        || $fulfillmentInvoices->isNotEmpty()
        || $shipments->isNotEmpty();

    /*
     * Copertura fulfillment delle righe prodotto.
     */
    $fulfilledProductRows = $isOrder
        ? $productRows->filter(
            fn ($row) => $row->getAttribute(
                'document_fulfillment'
            ) !== null
        )
        : collect();

    $productRowsCount = $productRows->count();
    $fulfilledProductRowsCount = $fulfilledProductRows->count();

    $hasCompleteOrderFulfillment = $isOrder
        && $productRowsCount > 0
        && $fulfilledProductRowsCount === $productRowsCount;

    /*
     * Mostriamo lo stato complessivo in testata solamente quando:
     *
     * - è un ordine;
     * - tutte le righe prodotto hanno una correlazione ERP;
     * - tutte le correlazioni espongono lo stesso stato.
     */
    $showHeaderFulfillmentState = $isOrder
        && $hasCompleteOrderFulfillment
        && $fulfillmentStates->count() === 1;

    $erpStateLabel = function ($state) {
        $state = strtoupper(
            trim((string) $state)
        );

        return match ($state) {
            'ORDINE' => 'Da evadere',
            'IN PREPARAZIONE' => 'In preparazione',
            'SPEDITO' => 'Spedito',
            'FATTURATO' => 'Fatturato',
            'NOTA VARIAZIONE' => 'Nota variazione',
            'TELEFONARE' => 'Telefonare',
            default => $state !== '' ? $state : '-',
        };
    };

    $erpStateBadgeClass = function ($state) {
        $state = strtoupper(
            trim((string) $state)
        );

        return match ($state) {
            'ORDINE' => 'text-bg-light border',
            'IN PREPARAZIONE' => 'text-bg-warning',
            'SPEDITO' => 'text-bg-primary',
            'FATTURATO' => 'text-bg-success',
            'NOTA VARIAZIONE' => 'text-bg-secondary',
            'TELEFONARE' => 'text-bg-info',
            default => 'text-bg-light border',
        };
    };

    $customerName = method_exists(
        $document,
        'customerNameForDisplay'
    )
        ? $document->customerNameForDisplay()
        : '-';

    $customerAddress = method_exists(
        $document,
        'customerAddressForDisplay'
    )
        ? $document->customerAddressForDisplay()
        : '-';

    $customerCity = method_exists(
        $document,
        'customerCityForDisplay'
    )
        ? $document->customerCityForDisplay()
        : '-';

    $customerVatNumber = method_exists(
        $document,
        'customerVatNumberForDisplay'
    )
        ? $document->customerVatNumberForDisplay()
        : '-';

    $customerTaxCode = method_exists(
        $document,
        'customerTaxCodeForDisplay'
    )
        ? $document->customerTaxCodeForDisplay()
        : '-';

    $customerEmail = method_exists(
        $document,
        'customerEmailForDisplay'
    )
        ? $document->customerEmailForDisplay()
        : '-';

    $customerPhone = method_exists(
        $document,
        'customerPhoneForDisplay'
    )
        ? $document->customerPhoneForDisplay()
        : '-';

    $shippingAddress = method_exists(
        $document,
        'shippingAddressForDisplay'
    )
        ? $document->shippingAddressForDisplay()
        : '-';

    $legalProfile = app(
        \App\Services\Storefront\LegalProfileResolver::class
    )->resolve($store ?? null);

    $storeProvenance = trim(
        (string) ($legalProfile['company'] ?? '')
    );

    $storeDitta = (int) (
        $store?->ditta_cg18
        ?? $document->DITTA_CG18
        ?? 0
    );

    $storeSite = (int) (
        $store?->erp_site_code
        ?? 0
    );

    $provenance = $storeProvenance !== ''
        ? $storeProvenance
        : (
            trim((string) ($store?->name ?? ''))
                ?: trim(
                    'Ditta '
                    . ($storeDitta ?: '-')
                    . ' / Sito '
                    . ($storeSite ?: '-')
                )
        );

    $legalAddress = trim(
        (string) ($legalProfile['address'] ?? '')
    );

    $legalCity = trim(
        (string) ($legalProfile['city'] ?? '')
    );

    $legalCountry = trim(
        (string) ($legalProfile['country'] ?? '')
    );

    $legalProfileDetails = collect([
        $legalAddress,

        trim(
            implode(
                ' ',
                array_filter([
                    $legalCity,
                    $legalCountry,
                ])
            )
        ),

        filled($legalProfile['vat'] ?? null)
            ? 'P. IVA ' . trim(
                (string) $legalProfile['vat']
            )
            : null,

        filled($legalProfile['tax_code'] ?? null)
            ? 'C.F. ' . trim(
                (string) $legalProfile['tax_code']
            )
            : null,

        filled($legalProfile['sdi'] ?? null)
            ? 'SDI ' . trim(
                (string) $legalProfile['sdi']
            )
            : null,

        filled($legalProfile['email'] ?? null)
            ? 'Email ' . trim(
                (string) $legalProfile['email']
            )
            : null,

        filled($legalProfile['pec'] ?? null)
            ? 'PEC ' . trim(
                (string) $legalProfile['pec']
            )
            : null,

        filled($legalProfile['phone'] ?? null)
            ? 'Tel. ' . trim(
                (string) $legalProfile['phone']
            )
            : null,
    ])
        ->filter(
            fn ($value) => filled($value)
        )
        ->values();

    $paymentDescription = trim(
        (string) (
            $document->DESCRPAG_CG62
            ?? ''
        )
    ) ?: '-';

    $paymentCode = trim(
        (string) (
            $document->CODPAG_CG62
            ?? ''
        )
    );
@endphp

<div class="container-fluid py-4 py-lg-5">
    <div class="d-flex flex-column gap-4">

        <div>
            <a
                href="{{ $indexUrl }}"
                class="btn btn-sm btn-outline-secondary"
            >
                <i class="fa-solid fa-arrow-left me-1"></i>
                Torna ai documenti
            </a>
        </div>

        <section class="border rounded-3 bg-white p-4 p-lg-5">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-4">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="badge text-bg-dark">
                            {{ $documentType }}
                        </span>

                        <span class="badge text-bg-light border">
                            NUMREG {{ $document->NUMREG_CO99 ?? '-' }}
                        </span>

                        @if($showHeaderFulfillmentState)
                            @php
                                $headerState = $fulfillmentStates->first();
                            @endphp

                            <span class="badge {{ $erpStateBadgeClass($headerState) }}">
                                {{ $erpStateLabel($headerState) }}
                            </span>
                        @endif
                    </div>

                    <h1 class="display-6 fw-bold mb-2">
                        Documento {{ $documentNumber }}
                    </h1>

                    <p class="text-muted mb-0">
                        Dettaglio righe e valori del documento ERP
                        collegato alla tua anagrafica cliente.
                    </p>
                </div>

                <div class="row g-3 flex-xl-nowrap">
                    <div class="col-6 col-xl-auto">
                        <div class="storefront-document-stat border rounded-3 px-3 py-2 h-100">
                            <div class="small text-muted">
                                Righe
                            </div>

                            <div class="fs-4 fw-bold">
                                {{ $rows->count() }}
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-xl-auto">
                        <div class="storefront-document-stat border rounded-3 px-3 py-2 h-100">
                            <div class="small text-muted">
                                Quantità
                            </div>

                            <div class="fs-4 fw-bold">
                                {{ $formatNumber($quantityTotal) }}
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-auto">
                        <div class="storefront-document-stat-wide border rounded-3 px-3 py-2 h-100">
                            <div class="small text-muted">
                                Netto righe
                            </div>

                            <div class="fs-4 fw-bold">
                                {{ $formatMoney($rowsTotal) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-12 col-lg-3">
                <div class="border rounded-3 bg-white p-4 h-100">
                    <div class="small text-muted mb-1">
                        Ditta
                    </div>

                    <div class="fw-semibold">
                        {{ $document->DITTA_CG18 ?? '-' }}
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-3">
                <div class="border rounded-3 bg-white p-4 h-100">
                    <div class="small text-muted mb-1">
                        Cliente
                    </div>

                    <div class="fw-semibold">
                        {{ $document->CLIFOR_CG44 ?? '-' }}
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-3">
                <div class="border rounded-3 bg-white p-4 h-100">
                    <div class="small text-muted mb-1">
                        Data documento
                    </div>

                    <div class="fw-semibold">
                        {{ $formatDate($document->DATADOC_DO11 ?? null) }}
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-3">
                <div class="border rounded-3 bg-white p-4 h-100">
                    <div class="small text-muted mb-1">
                        Numero ERP
                    </div>

                    <div class="fw-semibold">
                        {{ $document->NUMREG_CO99 ?? '-' }}
                    </div>
                </div>
            </div>
        </section>

        @if($hasFulfillment)
            <section class="border rounded-3 bg-white overflow-hidden">
                <div class="p-4 border-bottom">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="fa-solid fa-truck-fast text-muted"></i>

                                <h2 class="h5 fw-bold mb-0">
                                    Evasione e spedizioni
                                </h2>
                            </div>

                            <div class="text-muted small">
                                Stato di avanzamento e riferimenti logistici disponibili nel gestionale.
                            </div>
                        </div>

                        @if($isOrder)
                            <div class="d-flex flex-wrap gap-2 align-items-start">
                                @if($showHeaderFulfillmentState)
                                    @php
                                        $headerState = $fulfillmentStates->first();
                                    @endphp

                                    <span class="badge {{ $erpStateBadgeClass($headerState) }}">
                                        {{ $erpStateLabel($headerState) }}
                                    </span>
                                @elseif($fulfillmentStates->isNotEmpty())
                                    @foreach($fulfillmentStates as $state)
                                        <span class="badge {{ $erpStateBadgeClass($state) }}">
                                            {{ $erpStateLabel($state) }}
                                        </span>
                                    @endforeach
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                @if($shipments->isNotEmpty())
                    <div class="p-4">
                        <div class="row g-3">
                            @foreach($shipments as $shipment)
                                @php
                                    $shipmentOrders = collect(
                                        $shipment['orders'] ?? []
                                    )
                                        ->filter()
                                        ->unique()
                                        ->values();

                                    $shipmentInvoices = collect(
                                        $shipment['invoices'] ?? []
                                    )
                                        ->filter()
                                        ->unique()
                                        ->values();

                                    $courierDocument = trim(
                                        (string) (
                                            $shipment['courier_document']
                                            ?? ''
                                        )
                                    );

                                    $courierSection = trim(
                                        (string) (
                                            $shipment['courier_section']
                                            ?? ''
                                        )
                                    );

                                    $courierReference = collect([
                                        $courierDocument !== ''
                                            && $courierDocument !== '0'
                                                ? $courierDocument
                                                : null,

                                        $courierSection !== ''
                                            && $courierSection !== '-'
                                                ? $courierSection
                                                : null,
                                    ])
                                        ->filter()
                                        ->implode(' / ');

                                    $shipmentCarrierCode = trim(
                                        (string) (
                                            $shipment['carrier_code']
                                            ?? ''
                                        )
                                    );

                                    /*
                                     * Il nome vettore proviene direttamente
                                     * dall'anagrafica ERP VETTORI_VTA14.
                                     *
                                     * Il codice resta come fallback nel caso
                                     * in cui la descrizione non sia disponibile.
                                     */
                                    $shipmentCarrierName = trim(
                                        (string) (
                                            $shipment['carrier_name']
                                            ?? ''
                                        )
                                    );

                                    $shipmentCarrierLabel = $shipmentCarrierName !== ''
                                        ? $shipmentCarrierName
                                        : (
                                            $shipmentCarrierCode !== ''
                                                ? $shipmentCarrierCode
                                                : '-'
                                        );

                                    $shipmentParcelId = trim(
                                        (string) (
                                            $shipment['parcel_id']
                                            ?? ''
                                        )
                                    );

                                    $parcelLabel = $shipmentCarrierCode === '1'
                                        ? 'ID collo BRT'
                                        : 'ID collo';

                                    /*
                                     * Tracking BRT.
                                     *
                                     * Il valore ERP IDCOLLICLI_VWEBDO32
                                     * viene passato a BRT nel parametro CD.
                                     *
                                     * Il collegamento viene generato solamente
                                     * per il codice vettore ERP "1", verificato
                                     * nell'anagrafica come BRT S.P.A.
                                     */
                                    $shipmentTrackingUrl = $shipmentCarrierCode === '1'
                                        && $shipmentParcelId !== ''
                                            ? 'https://services.brt.it/it/tracking?OP=N&CD='
                                                . urlencode($shipmentParcelId)
                                            : null;
                                @endphp

                                <div class="col-12">
                                    <div class="border rounded-3 p-4">
                                        <div class="d-flex flex-column flex-xl-row justify-content-between gap-4">
                                            <div class="flex-shrink-0">
                                                <div class="small text-muted mb-1">
                                                    Spedizione
                                                </div>

                                                <div class="h5 fw-bold mb-0">
                                                    @if(filled($shipment['ddt_numreg'] ?? null))
                                                        DDT {{ $shipment['ddt_numreg'] }}
                                                    @else
                                                        Riferimento logistico
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="row g-3 flex-grow-1">
                                                <div class="col-6 col-md-4 col-xl">
                                                    <div class="small text-muted mb-1">
                                                        Data
                                                    </div>

                                                    <div class="fw-semibold">
                                                        {{ $formatDate($shipment['shipping_date'] ?? null) }}
                                                    </div>
                                                </div>

                                                <div class="col-6 col-md-4 col-xl">
                                                    <div class="small text-muted mb-1">
                                                        Vettore
                                                    </div>

                                                    <div class="fw-semibold">
                                                        {{ $shipmentCarrierLabel }}
                                                    </div>
                                                </div>

                                                <div class="col-6 col-md-4 col-xl">
                                                    <div class="small text-muted mb-1">
                                                        Documento corriere
                                                    </div>

                                                    <div class="fw-semibold">
                                                        {{ $courierReference !== '' ? $courierReference : '-' }}
                                                    </div>
                                                </div>

                                                <div class="col-6 col-md-4 col-xl">
                                                    <div class="small text-muted mb-1">
                                                        Colli
                                                    </div>

                                                    <div class="fw-semibold">
                                                        {{ $formatQuantity($shipment['parcels'] ?? null) }}
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-8 col-xl">
                                                    <div class="small text-muted mb-1">
                                                        {{ $parcelLabel }}
                                                    </div>

                                                    <div class="fw-semibold text-break">
                                                        @if($shipmentParcelId !== '')
                                                            @if($shipmentTrackingUrl)
                                                                <a
                                                                    href="{{ $shipmentTrackingUrl }}"
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    class="link-primary text-decoration-none"
                                                                    title="Traccia la spedizione BRT"
                                                                >
                                                                    {{ $shipmentParcelId }}

                                                                    <i class="fa-solid fa-arrow-up-right-from-square ms-1 small"></i>
                                                                </a>
                                                            @else
                                                                {{ $shipmentParcelId }}
                                                            @endif
                                                        @else
                                                            -
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        @if(
                                            $shipmentOrders->isNotEmpty()
                                            || $shipmentInvoices->isNotEmpty()
                                        )
                                            <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                                                @foreach($shipmentOrders as $orderNumreg)
                                                    <span class="badge text-bg-light border">
                                                        Ordine {{ $orderNumreg }}
                                                    </span>
                                                @endforeach

                                                @foreach($shipmentInvoices as $invoiceNumreg)
                                                    <span class="badge text-bg-light border">
                                                        Fattura {{ $invoiceNumreg }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @elseif($isOrder)
                    <div class="p-4">
                        <div class="text-muted small">
                            Non risultano ancora riferimenti di spedizione associati a questo ordine.
                        </div>
                    </div>
                @endif
            </section>
        @endif

        <section class="row g-3">
            <div class="col-12 col-xl-6">
                <div class="border rounded-3 bg-white p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="fa-regular fa-building text-muted"></i>

                        <h2 class="h5 fw-bold mb-0">
                            Intestazione cliente
                        </h2>
                    </div>

                    <div class="fw-bold mb-2">
                        {{ $customerName }}
                    </div>

                    <div>
                        {{ $customerAddress }}
                    </div>

                    <div class="mb-3">
                        {{ $customerCity }}
                    </div>

                    <div class="small">
                        <span class="text-muted">
                            P. IVA:
                        </span>

                        <span class="fw-semibold">
                            {{ $customerVatNumber }}
                        </span>
                    </div>

                    <div class="small">
                        <span class="text-muted">
                            Codice fiscale:
                        </span>

                        <span class="fw-semibold">
                            {{ $customerTaxCode }}
                        </span>
                    </div>

                    @if($customerEmail !== '-')
                        <div class="small mt-2">
                            <span class="text-muted">
                                Email:
                            </span>

                            {{ $customerEmail }}
                        </div>
                    @endif

                    @if($customerPhone !== '-')
                        <div class="small">
                            <span class="text-muted">
                                Telefono:
                            </span>

                            {{ $customerPhone }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="border rounded-3 bg-white p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="fa-solid fa-truck text-muted"></i>

                        <h2 class="h5 fw-bold mb-0">
                            Destinazione merce
                        </h2>
                    </div>

                    <div class="fw-semibold mb-4">
                        {{ $shippingAddress }}
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="small text-muted mb-1">
                                Provenienza
                            </div>

                            <div class="fw-semibold">
                                {{ $provenance }}
                            </div>

                            @if($legalProfileDetails->isNotEmpty())
                                <div class="small text-muted mt-1">
                                    {{ $legalProfileDetails->implode(' · ') }}
                                </div>
                            @endif
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="small text-muted mb-1">
                                Pagamento
                            </div>

                            <div class="fw-semibold">
                                @if($paymentCode !== '')
                                    {{ $paymentCode }} -
                                @endif

                                {{ $paymentDescription }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border rounded-3 bg-white p-4">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">
                        Azioni documento
                    </h2>

                    <div class="text-muted small">
                        Apri una richiesta collegata a questo documento o scarica i materiali.
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a
                        href="{{ route('storefront.account.documents.return.create', array_merge(['document' => $document->NUMREG_CO99], $contextParams)) }}"
                        class="btn btn-outline-dark"
                    >
                        <i class="fa-solid fa-rotate-left me-1"></i>
                        Richiedi reso
                    </a>

                    <a
                        href="{{ route('storefront.account.documents.support.create', array_merge(['document' => $document->NUMREG_CO99], $contextParams)) }}"
                        class="btn btn-outline-dark"
                    >
                        <i class="fa-regular fa-life-ring me-1"></i>
                        Apri ticket assistenza
                    </a>

                    <a
                        href="{{ route('storefront.account.documents.excel', array_merge(['document' => $document->NUMREG_CO99], $contextParams)) }}"
                        class="btn btn-outline-secondary"
                    >
                        <i class="fa-regular fa-file-excel me-1"></i>
                        Excel
                    </a>

                    <a
                        href="{{ route('storefront.account.documents.images', array_merge(['document' => $document->NUMREG_CO99], $contextParams)) }}"
                        class="btn btn-outline-secondary"
                    >
                        <i class="fa-regular fa-images me-1"></i>
                        Immagini ZIP
                    </a>
                </div>
            </div>
        </section>

        @if(
            $documentReturns->isNotEmpty()
            || $supportTickets->isNotEmpty()
        )
            <section class="row g-3">
                @if($documentReturns->isNotEmpty())
                    <div class="col-12 col-xl-6">
                        <div class="border rounded-3 bg-white h-100 overflow-hidden">
                            <div class="p-4 border-bottom">
                                <h2 class="h5 fw-bold mb-1">
                                    Storico resi
                                </h2>

                                <div class="text-muted small">
                                    Richieste aperte da questa area documentale.
                                </div>
                            </div>

                            <div class="list-group list-group-flush">
                                @foreach($documentReturns as $returnRequest)
                                    <div class="list-group-item p-4">
                                        <div class="d-flex justify-content-between gap-3">
                                            <div>
                                                <div class="fw-semibold">
                                                    {{ $returnRequest->request_number }}
                                                </div>

                                                <div class="small text-muted">
                                                    {{ optional($returnRequest->created_at)->format('d/m/Y H:i') }}
                                                    · {{ $returnRequest->items_count }} righe
                                                    · {{ $returnRequest->attachments_count }} allegati
                                                </div>
                                            </div>

                                            <span class="badge text-bg-light border align-self-start">
                                                {{ $returnRequest->statusLabel() }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                @if($supportTickets->isNotEmpty())
                    <div class="col-12 col-xl-6">
                        <div class="border rounded-3 bg-white h-100 overflow-hidden">
                            <div class="p-4 border-bottom">
                                <h2 class="h5 fw-bold mb-1">
                                    Storico ticket
                                </h2>

                                <div class="text-muted small">
                                    Ticket assistenza collegati a questo documento.
                                </div>
                            </div>

                            <div class="list-group list-group-flush">
                                @foreach($supportTickets as $ticket)
                                    <div class="list-group-item p-4">
                                        <div class="d-flex justify-content-between gap-3">
                                            <div>
                                                <div class="fw-semibold">
                                                    {{ $ticket->ticket_number }}
                                                </div>

                                                <div class="small text-muted">
                                                    {{ $ticket->subject }}
                                                </div>

                                                <div class="small text-muted">
                                                    {{ optional($ticket->created_at)->format('d/m/Y H:i') }}
                                                    · {{ $ticket->items_count }} righe
                                                    · {{ $ticket->attachments_count }} allegati
                                                </div>
                                            </div>

                                            <span class="badge text-bg-light border align-self-start">
                                                {{ $ticket->statusLabel() }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </section>
        @endif

        <section class="border rounded-3 bg-white overflow-hidden">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 p-4 border-bottom">
                <div>
                    <h2 class="h5 fw-bold mb-1">
                        Righe documento
                    </h2>

                    <div class="text-muted small">
                        @if($isOrder)
                            Articoli, quantità, stato di evasione, prezzo e netto riga.
                        @else
                            Articoli, quantità, prezzo e netto riga.
                        @endif
                    </div>
                </div>

                <span class="badge text-bg-light border align-self-start align-self-lg-center">
                    {{ $rows->count() }} righe
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="small text-muted">
                            <th class="ps-4">
                                Immagine
                            </th>

                            <th>
                                Riga
                            </th>

                            <th>
                                Codice
                            </th>

                            <th>
                                Descrizione
                            </th>

                            <th>
                                UM
                            </th>

                            <th class="text-end">
                                Q.tà
                            </th>

                            @if($isOrder)
                                <th>
                                    Evasione
                                </th>
                            @endif

                            <th class="text-end">
                                Prezzo
                            </th>

                            <th class="text-end pe-4">
                                Netto
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @if($rows->isEmpty())
                            <tr>
                                <td
                                    colspan="{{ $isOrder ? 9 : 8 }}"
                                    class="text-center py-5"
                                >
                                    <div class="mb-3 text-muted">
                                        <i class="fa-regular fa-file-lines fa-3x"></i>
                                    </div>

                                    <div class="fw-semibold">
                                        Nessuna riga trovata
                                    </div>

                                    <div class="text-muted small">
                                        Il documento non contiene righe disponibili.
                                    </div>
                                </td>
                            </tr>
                        @else
                            @foreach($rows as $row)
                                @php
                                    $thumbnailUrl = method_exists(
                                        $row,
                                        'thumbnailUrl'
                                    )
                                        ? $row->thumbnailUrl()
                                        : null;

                                    $rowSku = trim(
                                        (string) (
                                            $row->CODART_MG66
                                            ?? ''
                                        )
                                    );

                                    $isProductRow = $rowSku !== '';

                                    $rowFulfillment = $row->getAttribute(
                                        'document_fulfillment'
                                    );

                                    $rowFulfillmentData = is_array(
                                        $rowFulfillment
                                    )
                                        ? $rowFulfillment
                                        : null;

                                    $rowState = $rowFulfillmentData['state']
                                        ?? null;

                                    $rowProcessedQuantity = $rowFulfillmentData[
                                        'processed_quantity'
                                    ] ?? null;

                                    $rowOrderedQuantity = $rowFulfillmentData[
                                        'ordered_quantity'
                                    ] ?? null;

                                    $rowRemainingQuantity = $rowFulfillmentData[
                                        'remaining_quantity'
                                    ] ?? null;

                                    $rowDdt = $rowFulfillmentData[
                                        'ddt_numreg'
                                    ] ?? null;

                                    $rowInvoice = $rowFulfillmentData[
                                        'invoice_numreg'
                                    ] ?? null;
                                @endphp

                                <tr>
                                    <td class="ps-4">
                                        @if($thumbnailUrl)
                                            <img
                                                src="{{ $thumbnailUrl }}"
                                                alt="{{ trim((string) ($row->DESCART_DO30 ?? '')) ?: 'Prodotto' }}"
                                                class="rounded border bg-light object-fit-contain"
                                                style="width: 64px; height: 64px;"
                                                loading="lazy"
                                            >
                                        @else
                                            <div
                                                class="d-inline-flex align-items-center justify-content-center rounded border bg-light text-muted"
                                                style="width: 64px; height: 64px;"
                                                aria-label="Immagine non disponibile"
                                            >
                                                <i class="fa-regular fa-image"></i>
                                            </div>
                                        @endif
                                    </td>

                                    <td>
                                        <span class="badge text-bg-light border">
                                            {{ $row->PROGRIGA_DO30 ?? '-' }}
                                        </span>
                                    </td>

                                    <td>
                                        <code>
                                            {{ $rowSku !== '' ? $rowSku : '-' }}
                                        </code>
                                    </td>

                                    <td>
                                        <div class="fw-semibold">
                                            {{ trim((string) ($row->DESCART_DO30 ?? '')) ?: '-' }}
                                        </div>
                                    </td>

                                    <td>
                                        {{ trim((string) ($row->UM1_DO30 ?? '')) ?: '-' }}
                                    </td>

                                    <td class="text-end">
                                        {{ $formatNumber($row->QTA1_DO30 ?? 0) }}
                                    </td>

                                    @if($isOrder)
                                        <td>
                                            @if(!$isProductRow)
                                                <span class="text-muted">
                                                    -
                                                </span>
                                            @elseif($rowFulfillmentData)
                                                <div class="d-flex flex-column align-items-start gap-1">
                                                    <span class="badge {{ $erpStateBadgeClass($rowState) }}">
                                                        {{ $erpStateLabel($rowState) }}
                                                    </span>

                                                    @if(
                                                        $rowProcessedQuantity !== null
                                                        || $rowOrderedQuantity !== null
                                                    )
                                                        <div class="small text-muted text-nowrap">
                                                            Processata:

                                                            <span class="fw-semibold text-body">
                                                                {{ $formatQuantity($rowProcessedQuantity) }}
                                                            </span>

                                                            /

                                                            Ordinata:

                                                            <span class="fw-semibold text-body">
                                                                {{ $formatQuantity($rowOrderedQuantity) }}
                                                            </span>
                                                        </div>
                                                    @endif

                                                    @if(
                                                        $rowRemainingQuantity !== null
                                                        && (float) $rowRemainingQuantity > 0
                                                    )
                                                        <div class="small text-muted text-nowrap">
                                                            Residua:

                                                            <span class="fw-semibold text-body">
                                                                {{ $formatQuantity($rowRemainingQuantity) }}
                                                            </span>
                                                        </div>
                                                    @endif

                                                    @if(filled($rowDdt))
                                                        <div class="small text-muted text-nowrap">
                                                            DDT

                                                            <span class="fw-semibold text-body">
                                                                {{ $rowDdt }}
                                                            </span>
                                                        </div>
                                                    @endif

                                                    @if(filled($rowInvoice))
                                                        <div class="small text-muted text-nowrap">
                                                            Fattura

                                                            <span class="fw-semibold text-body">
                                                                {{ $rowInvoice }}
                                                            </span>
                                                        </div>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="small text-muted">
                                                    In attesa di aggiornamento
                                                </div>
                                            @endif
                                        </td>
                                    @endif

                                    <td class="text-end">
                                        {{ $formatMoney($row->PREZZO1_DO30 ?? 0) }}
                                    </td>

                                    <td class="text-end fw-semibold pe-4">
                                        {{ $formatMoney($row->IMPNETSCP_DO30 ?? 0) }}
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>

                    @if($rows->isNotEmpty())
                        <tfoot>
                            <tr>
                                <th
                                    colspan="5"
                                    class="ps-4"
                                >
                                    Totale
                                </th>

                                <th class="text-end">
                                    {{ $formatNumber($quantityTotal) }}
                                </th>

                                @if($isOrder)
                                    <th></th>
                                @endif

                                <th></th>

                                <th class="text-end pe-4">
                                    {{ $formatMoney($rowsTotal) }}
                                </th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

    </div>
</div>
@endsection