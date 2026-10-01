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

    public function attach(DocumentHeader $document): DocumentHeader
    {
        $fulfillment = $this->resolve($document);

        $document->setAttribute(
            'document_fulfillment',
            $fulfillment
        );

        $this->attachRows(
            $document,
            $fulfillment['rows'] ?? collect()
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
         * Senza questa deduplicazione attachRows() riceverebbe due match
         * identici e assegnerebbe una Collection alla riga documento
         * anziché il singolo fulfillment.
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
         *
         * Il collegamento alla riga fisica del documento viene effettuato
         * successivamente tramite:
         *
         * - PROGRIGA_DO30
         * - CODART_MG66
         *
         * Il solo progressivo non è sufficiente perché ditte differenti
         * possono avere lo stesso PROGRIGA_DO30 sul medesimo NUMREG.
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
            /*
             * Il mancato caricamento dell'anagrafica vettori non deve
             * impedire la visualizzazione del fulfillment.
             *
             * In questo caso conserveremo comunque carrier_code e il
             * frontend potrà mostrare il codice ERP.
             */
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
            /*
             * Ditta e cliente della specifica riga DO33.
             *
             * Non coincidono necessariamente con ditta/cliente della testata
             * dell'ordine visualizzato.
             */
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

            /*
             * Stato ERP reale:
             * ORDINE / IN PREPARAZIONE / SPEDITO / FATTURATO / ...
             *
             * Non viene trasformato automaticamente in uno stato
             * logistico sintetico.
             */
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
             *
             * Non usare integerOrNull():
             * "05" e "5" identificano vettori differenti.
             */
            'carrier_code' => $this->trimOrNull(
                $reference->CODVETTORE_MG14 ?? null
            ),

            /*
             * Viene valorizzato successivamente tramite VETTORI_VTA14.
             */
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
             * Per BRT abbiamo verificato che questo valore può essere
             * utilizzato con:
             *
             * https://services.brt.it/it/tracking?OP=N&CD={parcel_id}
             *
             * Non lo rinominiamo tracking_number perché il significato
             * può dipendere dal vettore.
             */
            'parcel_id' => $this->trimOrNull(
                $reference->IDCOLLICLI_VWEBDO32 ?? null
            ),
        ];
    }

    private function referenceIdentity(array $row): string
    {
        /*
         * La chiave comprende l'intero riferimento normalizzato.
         *
         * In questo modo eliminiamo solamente record DO33 realmente
         * duplicati.
         *
         * Se cambia stato, quantità, ditta, SKU, documento collegato,
         * spedizione, vettore o qualsiasi altro dato significativo,
         * il riferimento rimane distinto.
         */
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
                 * Non consideriamo DATASPEDIZ_DO11 da solo come prova
                 * dell'esistenza di una spedizione.
                 *
                 * Abbiamo verificato casi ERP in cui DATASPEDIZ_DO11 contiene
                 * valori operativi come "TELEFONARE" pur senza DDT, vettore
                 * o identificativo collo.
                 */
                return $row['ddt_numreg'] !== null
                    || $row['carrier_code'] !== null
                    || $row['parcel_id'] !== null;
            })
            ->groupBy(function (array $row) {
                /*
                 * La ditta fa parte della chiave.
                 *
                 * Due società ERP possono utilizzare riferimenti documentali
                 * uguali o comunque produrre flussi distinti sullo stesso
                 * ordine cliente. Non devono essere accorpati accidentalmente.
                 */
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

                    /*
                     * Codice e descrizione vettore ERP.
                     */
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
        Collection $references
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
             *
             * Per questo motivo, quando la riga documento possiede uno SKU,
             * devono coincidere ENTRAMBI:
             *
             * - PROGRIGA_DO30
             * - CODART_MG66
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

            /*
             * Difesa aggiuntiva.
             *
             * I riferimenti sono già deduplicati in resolve(), ma
             * deduplichiamo nuovamente i match prima dell'assegnazione
             * della riga per evitare che eventuali chiamate future a
             * attachRows() con una Collection non normalizzata producano
             * una Collection per duplicati identici.
             */
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