<?php

namespace App\Services\Storefront\Documents;

use App\Models\Erp\DocumentHeader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentFulfillmentResolver
{
    private const DOCUMENT_REFERENCES_TABLE = 'dbo.DETTRIGHECORPORIF_TOT';

    private const CARRIERS_TABLE = 'dbo.VETTORI_VTA14';

    /**
     * Arricchisce il documento visualizzato con i dati di fulfillment.
     *
     * $sourceDocument è opzionale.
     *
     * Caso normale:
     * - documento visualizzato = documento sorgente DO33.
     *
     * Caso ordine ufficiale Alyante:
     * - documento visualizzato = /00;
     * - documento sorgente DO33 = precedente ordine INTERNET /3M.
     *
     * Il documento /00 rimane sempre quello customer-facing.
     */
    public function attach(
        DocumentHeader $document,
        ?DocumentHeader $sourceDocument = null
    ): DocumentHeader {
        $source = $sourceDocument ?? $document;

        $fulfillment = $this->resolve($source);

        /*
         * document_type deve rappresentare il documento visualizzato,
         * non l'eventuale documento tecnico utilizzato come sorgente.
         */
        $fulfillment['document_type'] = strtoupper(
            trim(
                (string) (
                    $document->TIPODOCDECOD_MG36
                    ?? ''
                )
            )
        );

        /*
         * Manteniamo esplicitamente traccia della sorgente tecnica.
         * Sono informazioni diagnostiche/runtime e non modificano
         * l'identità del documento visualizzato.
         */
        $fulfillment['source_numreg'] = $this->trimOrNull(
            $source->NUMREG_CO99 ?? null
        );

        $fulfillment['source_document_number'] = $this->trimOrNull(
            $source->NUMSEZDOC_DO11 ?? null
        );

        $fulfillment['uses_external_source'] =
            $sourceDocument instanceof DocumentHeader
            && $this->trimOrNull(
                $sourceDocument->NUMREG_CO99 ?? null
            ) !== $this->trimOrNull(
                $document->NUMREG_CO99 ?? null
            );

        $document->setAttribute(
            'document_fulfillment',
            $fulfillment
        );

        $this->attachRows(
            $document,
            $fulfillment['rows'] ?? collect(),
            $fulfillment['uses_external_source']
        );

        return $document;
    }

    public function resolve(DocumentHeader $document): array
    {
        $numreg = $this->trimOrNull(
            $document->NUMREG_CO99 ?? null
        );

        $ditta = (int) ($document->DITTA_CG18 ?? 0);
        $clifor = (int) ($document->CLIFOR_CG44 ?? 0);

        $documentType = strtoupper(
            trim(
                (string) (
                    $document->TIPODOCDECOD_MG36
                    ?? ''
                )
            )
        );

        if (
            $numreg === null
            || $ditta <= 0
            || $clifor <= 0
        ) {
            return $this->emptyResult($documentType);
        }

        try {
            $references = $this->loadReferences(
                $document,
                $documentType,
                $numreg,
                $ditta,
                $clifor
            );
        } catch (Throwable $exception) {
            Log::warning(
                'Unable to resolve ERP document fulfillment',
                [
                    'numreg_co99' => $numreg,
                    'ditta_cg18' => $ditta,
                    'clifor_cg44' => $clifor,
                    'document_type' => $documentType,
                    'message' => $exception->getMessage(),
                ]
            );

            return $this->emptyResult($documentType);
        }

        if ($references->isEmpty()) {
            return $this->emptyResult($documentType);
        }

        /*
         * Normalizziamo i riferimenti ERP e rimuoviamo esclusivamente
         * i duplicati completamente identici.
         *
         * Caso reale verificato:
         *
         * ordine 202600020187
         *
         * ditta 3 / riga 12 / BIC12
         * ditta 3 / riga 13 / AR121
         *
         * DETTRIGHECORPORIF_TOT restituisce due record identici per la
         * stessa riga.
         *
         * Non utilizziamo semplicemente first(): se due riferimenti hanno
         * anche una sola informazione differente devono rimanere distinti.
         */
        $rows = $references
            ->map(
                fn ($reference) => $this->normalizeReference(
                    $reference
                )
            )
            ->unique(
                fn (array $row) => $this->referenceIdentity($row)
            )
            ->values();

        /*
         * Risolviamo i nomi dei vettori con una singola query ERP.
         *
         * I codici vengono mantenuti come stringhe:
         *
         * 1  = BRT S.P.A.
         * 05 = LOGIT SERVICE SRL
         * 5  = CORRIERE CASINI MARCO
         *
         * È quindi fondamentale NON convertire CODVETTORE_MG14
         * in intero.
         */
        $carrierNames = $this->loadCarrierNames(
            $document,
            $rows
                ->pluck('carrier_code')
                ->filter()
                ->unique()
                ->values()
        );

        $rows = $rows
            ->map(function (array $row) use ($carrierNames) {
                $carrierCode = $row['carrier_code'];

                $row['carrier_name'] = $carrierCode !== null
                    ? $carrierNames->get($carrierCode)
                    : null;

                return $row;
            })
            ->values();

        return [
            'document_type' => $documentType,

            'rows' => $rows,

            'states' => $rows
                ->pluck('state')
                ->filter()
                ->unique()
                ->values(),

            'orders' => $rows
                ->pluck('order_numreg')
                ->filter()
                ->unique()
                ->values(),

            'ddts' => $rows
                ->pluck('ddt_numreg')
                ->filter()
                ->unique()
                ->values(),

            'invoices' => $rows
                ->pluck('invoice_numreg')
                ->filter()
                ->unique()
                ->values(),

            'shipments' => $this->buildShipments($rows),
        ];
    }

    private function loadReferences(
        DocumentHeader $document,
        string $documentType,
        string $numreg,
        int $ditta,
        int $clifor
    ): Collection {
        $connection = $document->getConnection();

        $query = $connection
            ->table(self::DOCUMENT_REFERENCES_TABLE);

        /*
         * ORDINE
         *
         * Un ordine cliente può contenere righe provenienti da più ditte ERP.
         *
         * Caso reale verificato:
         *
         * NUMREG ordine 202600011663
         *
         * Ditta 1 / cliente 31880:
         * - riga 1  7090CFC
         * - riga 3  INTSTAMPA
         *
         * Ditta 3 / cliente 3477:
         * - riga 1  16547
         * - riga 2  FV200LM
         * - ...
         * - riga 38 VALR44
         *
         * Tutte le righe condividono lo stesso NUMREG_CO99 /
         * NUMREG_CO99_ORD, ma non necessariamente la stessa DITTA_CG18
         * o lo stesso CLIFOR_CG44.
         *
         * Per questo motivo sugli ORDINI NON filtriamo per ditta/cliente
         * della testata.
         */
        if ($documentType === 'ORDINE') {
            $query->where(function ($query) use ($numreg) {
                $query
                    ->whereRaw(
                        'LTRIM(RTRIM(CONVERT(varchar(50), NUMREG_CO99))) = ?',
                        [$numreg]
                    )
                    ->orWhereRaw(
                        'LTRIM(RTRIM(CONVERT(varchar(50), NUMREG_CO99_ORD))) = ?',
                        [$numreg]
                    );
            });

        /*
         * DDT
         *
         * Per i DDT manteniamo il vincolo della testata.
         *
         * Non usiamo PROGRIGA_DO30 per collegare le righe fisiche del DDT:
         * abbiamo verificato che il progressivo della vista DO33 rappresenta
         * la riga ordine e può essere diverso dalla riga fisica del DDT.
         */
        } elseif (str_starts_with($documentType, 'DDT')) {
            $query
                ->where('DITTA_CG18', $ditta)
                ->where('CLIFOR_CG44', $clifor)
                ->whereRaw(
                    'LTRIM(RTRIM(CONVERT(varchar(50), NUMREG_CO99_DDT))) = ?',
                    [$numreg]
                );

        /*
         * FATTURE
         *
         * Anche per le fatture manteniamo il vincolo della testata finché
         * non viene verificato un caso ERP che richieda una correlazione
         * multi-ditta analoga a quella degli ordini.
         */
        } elseif (
            $documentType === 'FATTURA'
            || $documentType === 'FATTURA RIEP'
            || $documentType === 'FATT DDT'
        ) {
            $query
                ->where('DITTA_CG18', $ditta)
                ->where('CLIFOR_CG44', $clifor)
                ->whereRaw(
                    'LTRIM(RTRIM(CONVERT(varchar(50), NUMREG_CO99_FAT))) = ?',
                    [$numreg]
                );
        } else {
            return collect();
        }

        return $query->get([
            'DITTA_CG18',
            'CLIFOR_CG44',

            'NUMREG_CO99',
            'PROGRIGA_DO30',
            'CODART_MG66',
            'STATO_VWEBDO31',

            'QTA1ORDINE_DO30',
            'QTAPROCES_CALDO30',
            'QTASALDO_CALDO30',

            'DATASPEDIZ_DO11',
            'CODVETTORE_MG14',

            'NUMREG_CO99_ORD',
            'NUMREG_CO99_DDT',
            'NUMREG_CO99_FAT',

            'DOCCORRIERE_VWEBDO32',
            'SEZCORRIERE_VWEBDO32',
            'NUMREGCORRIERE_VWEBDO32',

            'TOTCOLLI_VWEBDO32',
            'IDCOLLICLI_VWEBDO32',
        ]);
    }

    private function loadCarrierNames(
        DocumentHeader $document,
        Collection $carrierCodes
    ): Collection {
        $carrierCodes = $carrierCodes
            ->map(
                fn ($code) => $this->trimOrNull($code)
            )
            ->filter()
            ->unique()
            ->values();

        if ($carrierCodes->isEmpty()) {
            return collect();
        }

        try {
            /*
             * CODICE_MG14_VWEBMG14 è un codice ERP testuale.
             *
             * Non viene convertito in INT perché:
             *
             * 05 = LOGIT SERVICE SRL
             * 5  = CORRIERE CASINI MARCO
             *
             * Sono due vettori distinti.
             */
            $rows = $document
                ->getConnection()
                ->table(self::CARRIERS_TABLE)
                ->whereIn(
                    'CODICE_MG14_VWEBMG14',
                    $carrierCodes->all()
                )
                ->get([
                    'CODICE_MG14_VWEBMG14',
                    'RAGSOANAG_CG16_VWEBMG14',
                ]);

            return $rows
                ->mapWithKeys(function ($carrier) {
                    $code = $this->trimOrNull(
                        $carrier->CODICE_MG14_VWEBMG14
                        ?? null
                    );

                    $name = $this->trimOrNull(
                        $carrier->RAGSOANAG_CG16_VWEBMG14
                        ?? null
                    );

                    if ($code === null) {
                        return [];
                    }

                    return [
                        $code => $name,
                    ];
                });
        } catch (Throwable $exception) {
            Log::warning(
                'Unable to resolve ERP carrier names',
                [
                    'carrier_codes' => $carrierCodes->all(),
                    'message' => $exception->getMessage(),
                ]
            );

            return collect();
        }
    }

    private function normalizeReference(object $reference): array
    {
        return [
            'ditta' => $this->integerOrNull(
                $reference->DITTA_CG18 ?? null
            ),

            'clifor' => $this->integerOrNull(
                $reference->CLIFOR_CG44 ?? null
            ),

            'source_numreg' => $this->trimOrNull(
                $reference->NUMREG_CO99 ?? null
            ),

            'source_row' => $this->integerOrNull(
                $reference->PROGRIGA_DO30 ?? null
            ),

            'sku' => $this->trimOrNull(
                $reference->CODART_MG66 ?? null
            ),

            'state' => $this->trimOrNull(
                $reference->STATO_VWEBDO31 ?? null
            ),

            'ordered_quantity' => $this->floatOrNull(
                $reference->QTA1ORDINE_DO30 ?? null
            ),

            'processed_quantity' => $this->floatOrNull(
                $reference->QTAPROCES_CALDO30 ?? null
            ),

            'remaining_quantity' => $this->floatOrNull(
                $reference->QTASALDO_CALDO30 ?? null
            ),

            'shipping_date' => $this->trimOrNull(
                $reference->DATASPEDIZ_DO11 ?? null
            ),

            /*
             * Il codice vettore resta volutamente una stringa.
             * "05" e "5" identificano vettori differenti.
             */
            'carrier_code' => $this->trimOrNull(
                $reference->CODVETTORE_MG14 ?? null
            ),

            'carrier_name' => null,

            'order_numreg' => $this->trimOrNull(
                $reference->NUMREG_CO99_ORD ?? null
            ),

            'ddt_numreg' => $this->trimOrNull(
                $reference->NUMREG_CO99_DDT ?? null
            ),

            'invoice_numreg' => $this->trimOrNull(
                $reference->NUMREG_CO99_FAT ?? null
            ),

            'courier_document' => $this->trimOrNull(
                $reference->DOCCORRIERE_VWEBDO32 ?? null
            ),

            'courier_section' => $this->trimOrNull(
                $reference->SEZCORRIERE_VWEBDO32 ?? null
            ),

            'courier_numreg' => $this->trimOrNull(
                $reference->NUMREGCORRIERE_VWEBDO32 ?? null
            ),

            'parcels' => $this->floatOrNull(
                $reference->TOTCOLLI_VWEBDO32 ?? null
            ),

            /*
             * IDCOLLICLI viene mantenuto volutamente come parcel_id.
             *
             * Per BRT:
             * https://services.brt.it/it/tracking?OP=N&CD={parcel_id}
             */
            'parcel_id' => $this->trimOrNull(
                $reference->IDCOLLICLI_VWEBDO32 ?? null
            ),
        ];
    }

    private function referenceIdentity(array $row): string
    {
        return json_encode(
            $row,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
        ) ?: '';
    }

    private function buildShipments(Collection $rows): Collection
    {
        return $rows
            ->filter(function (array $row) {
                /*
                 * DATASPEDIZ_DO11 da solo non prova l'esistenza
                 * di una spedizione.
                 *
                 * Può contenere valori operativi come TELEFONARE.
                 */
                return $row['ddt_numreg'] !== null
                    || $row['carrier_code'] !== null
                    || $row['parcel_id'] !== null;
            })
            ->groupBy(function (array $row) {
                return implode('|', [
                    (string) ($row['ditta'] ?? ''),
                    (string) ($row['ddt_numreg'] ?? ''),
                    (string) ($row['shipping_date'] ?? ''),
                    (string) ($row['carrier_code'] ?? ''),
                    (string) ($row['courier_document'] ?? ''),
                    (string) ($row['courier_section'] ?? ''),
                    (string) ($row['courier_numreg'] ?? ''),
                    (string) ($row['parcel_id'] ?? ''),
                ]);
            })
            ->map(function (Collection $shipmentRows) {
                $first = $shipmentRows->first();

                return [
                    'ditta' => $first['ditta'],
                    'clifor' => $first['clifor'],

                    'ddt_numreg' => $first['ddt_numreg'],
                    'shipping_date' => $first['shipping_date'],

                    'carrier_code' => $first['carrier_code'],
                    'carrier_name' => $first['carrier_name'] ?? null,

                    'courier_document' => $first['courier_document'],
                    'courier_section' => $first['courier_section'],
                    'courier_numreg' => $first['courier_numreg'],

                    'parcels' => $shipmentRows
                        ->pluck('parcels')
                        ->filter(
                            fn ($value) => $value !== null
                        )
                        ->unique()
                        ->values()
                        ->first(),

                    'parcel_id' => $first['parcel_id'],

                    'orders' => $shipmentRows
                        ->pluck('order_numreg')
                        ->filter()
                        ->unique()
                        ->values(),

                    'invoices' => $shipmentRows
                        ->pluck('invoice_numreg')
                        ->filter()
                        ->unique()
                        ->values(),
                ];
            })
            ->values();
    }

    private function attachRows(
        DocumentHeader $document,
        Collection $references,
        bool $externalSource = false
    ): void {
        $documentRows = collect($document->rows ?? []);

        if (
            $documentRows->isEmpty()
            || $references->isEmpty()
        ) {
            return;
        }

        $documentType = strtoupper(
            trim(
                (string) (
                    $document->TIPODOCDECOD_MG36
                    ?? ''
                )
            )
        );

        /*
         * Per ora arricchiamo le singole righe solamente sugli ORDINI.
         *
         * Sui DDT abbiamo verificato che PROGRIGA_DO30 della vista
         * rappresenta la riga ordine e non necessariamente la riga
         * fisica del DDT.
         */
        if ($documentType !== 'ORDINE') {
            return;
        }

        if ($externalSource) {
            /*
             * ORDINE UFFICIALE /00
             * --------------------
             *
             * I riferimenti DO33 appartengono al precedente ordine
             * tecnico INTERNET /3M.
             *
             * I progressivi delle righe NON sono affidabili tra i due
             * documenti perché durante l'elaborazione Alyante:
             *
             * - viene aggiunta la riga descrittiva "Ordine Cl. num...";
             * - prodotti possono essere rimossi;
             * - i progressivi possono quindi divergere.
             *
             * Per questo motivo il collegamento viene effettuato
             * esclusivamente per SKU.
             */
            $this->attachRowsBySku(
                $documentRows,
                $references
            );

            return;
        }

        /*
         * ORDINE NORMALE / SORGENTE DIRETTA
         * ---------------------------------
         *
         * Manteniamo il comportamento già verificato:
         * PROGRIGA_DO30 + CODART_MG66.
         */
        $this->attachRowsByProgressiveAndSku(
            $documentRows,
            $references
        );
    }

    private function attachRowsBySku(
        Collection $documentRows,
        Collection $references
    ): void {
        $referencesBySku = $references
            ->filter(
                fn (array $reference) =>
                    $this->trimOrNull(
                        $reference['sku'] ?? null
                    ) !== null
            )
            ->groupBy(
                fn (array $reference) =>
                    $this->normalizeSkuKey(
                        $reference['sku'] ?? null
                    )
            );

        foreach ($documentRows as $row) {
            $sku = $this->trimOrNull(
                $row->CODART_MG66 ?? null
            );

            /*
             * Le righe descrittive/tecniche del /00 non possiedono SKU
             * e non devono ricevere un fulfillment.
             */
            if ($sku === null) {
                continue;
            }

            $matches = collect(
                $referencesBySku->get(
                    $this->normalizeSkuKey($sku),
                    collect()
                )
            )
                ->unique(
                    fn (array $reference) =>
                        $this->referenceIdentity($reference)
                )
                ->values();

            if ($matches->isEmpty()) {
                continue;
            }

            /*
             * Se lo stesso SKU compare più volte in DO33 con riferimenti
             * realmente differenti, non scegliamo arbitrariamente.
             *
             * Conserviamo la Collection: la view può trattare il caso come
             * fulfillment multiplo/ambiguo senza attribuire alla riga uno
             * stato potenzialmente errato.
             */
            $row->setAttribute(
                'document_fulfillment',
                $matches->count() === 1
                    ? $matches->first()
                    : $matches
            );
        }
    }

    private function attachRowsByProgressiveAndSku(
        Collection $documentRows,
        Collection $references
    ): void {
        $referencesByRow = $references
            ->filter(
                fn (array $reference) =>
                    $reference['source_row'] !== null
            )
            ->groupBy(
                fn (array $reference) =>
                    (string) $reference['source_row']
            );

        foreach ($documentRows as $row) {
            $rowNumber = $this->integerOrNull(
                $row->PROGRIGA_DO30 ?? null
            );

            if ($rowNumber === null) {
                continue;
            }

            $matches = collect(
                $referencesByRow->get(
                    (string) $rowNumber,
                    collect()
                )
            );

            if ($matches->isEmpty()) {
                continue;
            }

            /*
             * Il progressivo da solo non è sufficiente.
             *
             * Caso reale:
             *
             * NUMREG 202600011663
             *
             * PROGRIGA 1:
             * - ditta 1 / 7090CFC
             * - ditta 3 / 16547
             *
             * PROGRIGA 3:
             * - ditta 1 / INTSTAMPA
             * - ditta 3 / FV200LV
             */
            $sku = $this->trimOrNull(
                $row->CODART_MG66 ?? null
            );

            if ($sku !== null) {
                $matches = $matches
                    ->filter(
                        fn (array $reference) =>
                            $this->sameSku(
                                $reference['sku'] ?? null,
                                $sku
                            )
                    )
                    ->values();

                if ($matches->isEmpty()) {
                    continue;
                }
            }

            $matches = $matches
                ->unique(
                    fn (array $reference) =>
                        $this->referenceIdentity($reference)
                )
                ->values();

            /*
             * Se la riga fisica non possiede uno SKU non proviamo a
             * indovinare il collegamento quando il progressivo identifica
             * più riferimenti ERP.
             */
            if (
                $sku === null
                && $matches->count() !== 1
            ) {
                continue;
            }

            $row->setAttribute(
                'document_fulfillment',
                $matches->count() === 1
                    ? $matches->first()
                    : $matches->values()
            );
        }
    }

    private function normalizeSkuKey(
        mixed $sku
    ): string {
        return mb_strtolower(
            trim(
                (string) ($sku ?? '')
            )
        );
    }

    private function sameSku(
        mixed $left,
        mixed $right
    ): bool {
        $left = $this->trimOrNull($left);
        $right = $this->trimOrNull($right);

        if (
            $left === null
            || $right === null
        ) {
            return false;
        }

        return strcasecmp($left, $right) === 0;
    }

    private function emptyResult(string $documentType): array
    {
        return [
            'document_type' => $documentType,
            'rows' => collect(),
            'states' => collect(),
            'orders' => collect(),
            'ddts' => collect(),
            'invoices' => collect(),
            'shipments' => collect(),
        ];
    }

    private function trimOrNull(mixed $value): ?string
    {
        $value = trim(
            (string) ($value ?? '')
        );

        return $value !== ''
            ? $value
            : null;
    }

    private function integerOrNull(mixed $value): ?int
    {
        if (
            $value === null
            || trim((string) $value) === ''
            || ! is_numeric($value)
        ) {
            return null;
        }

        return (int) $value;
    }

    private function floatOrNull(mixed $value): ?float
    {
        if (
            $value === null
            || trim((string) $value) === ''
            || ! is_numeric($value)
        ) {
            return null;
        }

        return (float) $value;
    }
}