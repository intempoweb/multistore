<?php

namespace App\Services\Storefront\Documents;

use App\Models\Erp\DocumentHeader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class OfficialOrderSourceResolver
{
    private const ORDER_VIEW = 'dbo.TESTATAVWEBDO30_TOT';

    private const WEB_ORDER_HEADER_TABLE = 'dbo.WDO11_DOCTESTATA_WEB';

    /**
     * Cerca il documento tecnico INTERNET (/3M) dal quale deriva
     * un ordine ufficiale Alyante (/00).
     *
     * Il documento restituito NON sostituisce il documento ufficiale
     * mostrato al cliente. Viene utilizzato esclusivamente come sorgente
     * tecnica per il fulfillment DO33.
     *
     * La relazione viene considerata valida solamente quando:
     *
     * - il documento visualizzato è un ORDINE;
     * - non è già un ordine INTERNET;
     * - la prima riga contiene "Ordine Cl. num. ...";
     * - il numero cliente/web esiste in WDO11 per la stessa ditta,
     *   lo stesso cliente e lo stesso anno;
     * - esiste esattamente un ORDINE INTERNET per stessa ditta,
     *   stesso cliente e stessa data;
     * - il candidato INTERNET possiede riferimenti DO33.
     *
     * In caso di ambiguità non viene effettuata alcuna associazione.
     */
    public function resolve(
        DocumentHeader $officialDocument
    ): ?DocumentHeader {
        $documentType = strtoupper(
            trim(
                (string) (
                    $officialDocument->TIPODOCDECOD_MG36
                    ?? ''
                )
            )
        );

        if ($documentType !== 'ORDINE') {
            return null;
        }

        /*
         * Se il documento è già quello proveniente da INTERNET,
         * non dobbiamo cercare un'altra sorgente.
         */
        $provenance = strtoupper(
            trim(
                (string) (
                    $officialDocument->PROVENORD
                    ?? ''
                )
            )
        );

        if ($provenance === 'INTERNET') {
            return null;
        }

        $officialNumreg = $this->trimOrNull(
            $officialDocument->NUMREG_CO99
            ?? null
        );

        $ditta = (int) (
            $officialDocument->DITTA_CG18
            ?? 0
        );

        $clifor = (int) (
            $officialDocument->CLIFOR_CG44
            ?? 0
        );

        $documentDate = $this->trimOrNull(
            $officialDocument->DATADOC_DO11
            ?? null
        );

        if (
            $officialNumreg === null
            || $ditta <= 0
            || $clifor <= 0
            || $documentDate === null
        ) {
            return null;
        }

        try {
            $webOrderNumber = $this->resolveWebOrderNumber(
                $officialDocument
            );

            if ($webOrderNumber === null) {
                return null;
            }

            if (
                ! $this->webOrderExists(
                    $officialDocument,
                    $webOrderNumber,
                    $ditta,
                    $clifor,
                    $documentDate
                )
            ) {
                return null;
            }

            $candidates = $this->loadInternetCandidates(
                $officialDocument,
                $ditta,
                $clifor,
                $documentDate,
                $officialNumreg
            );

            /*
             * Non scegliamo mai arbitrariamente tra più ordini INTERNET.
             */
            if ($candidates->count() !== 1) {
                if ($candidates->count() > 1) {
                    Log::warning(
                        'Ambiguous ERP internet order source for official order',
                        [
                            'official_numreg' => $officialNumreg,
                            'ditta_cg18' => $ditta,
                            'clifor_cg44' => $clifor,
                            'document_date' => $documentDate,
                            'web_order_number' => $webOrderNumber,
                            'candidate_numregs' => $candidates
                                ->pluck('NUMREG_CO99')
                                ->map(
                                    fn ($value) =>
                                        trim((string) $value)
                                )
                                ->values()
                                ->all(),
                        ]
                    );
                }

                return null;
            }

            $candidate = $candidates->first();

            if (! $candidate) {
                return null;
            }

            $sourceNumreg = $this->trimOrNull(
                $candidate->NUMREG_CO99
                ?? null
            );

            if ($sourceNumreg === null) {
                return null;
            }

            /*
             * La presenza in DO33 è l'ultima condizione necessaria:
             * il /3M deve essere effettivamente utilizzabile come
             * sorgente del fulfillment.
             */
            if (
                ! $this->hasFulfillmentReferences(
                    $officialDocument,
                    $sourceNumreg
                )
            ) {
                return null;
            }

            $source = new DocumentHeader();

            $source->setConnection(
                $officialDocument->getConnectionName()
            );

            $source->forceFill([
                'NUMREG_CO99' => $sourceNumreg,
                'DITTA_CG18' => $candidate->DITTA_CG18,
                'CLIFOR_CG44' => $candidate->CLIFOR_CG44,
                'DATADOC_DO11' => $candidate->DATADOC_DO11,
                'NUMSEZDOC_DO11' => $candidate->NUMSEZDOC_DO11,
                'TIPODOCDECOD_MG36' => $candidate->TIPODOCDECOD_MG36,
                'PROVENORD' => $candidate->PROVENORD,
            ]);

            return $source;
        } catch (Throwable $exception) {
            Log::warning(
                'Unable to resolve ERP internet source for official order',
                [
                    'official_numreg' => $officialNumreg,
                    'ditta_cg18' => $ditta,
                    'clifor_cg44' => $clifor,
                    'message' => $exception->getMessage(),
                ]
            );

            return null;
        }
    }

    /**
     * Restituisce i NUMREG degli ordini tecnici INTERNET (/3M)
     * che risultano già sostituiti da un ordine ufficiale (/00).
     *
     * Questo metodo è pensato per l'elenco documenti:
     *
     * - il /00 rimane visibile;
     * - il relativo /3M viene escluso;
     * - un /3M privo di /00 rimane visibile;
     * - in caso di relazione assente o ambigua non viene nascosto nulla.
     *
     * La validazione della relazione non viene duplicata:
     * per ogni documento ufficiale viene utilizzato resolve().
     */
    public function resolveReplacedTechnicalNumregs(
        Collection $officialDocuments
    ): Collection {
        return $officialDocuments
            ->filter(
                fn ($document) =>
                    $document instanceof DocumentHeader
            )
            ->map(
                function (DocumentHeader $officialDocument) {
                    $source = $this->resolve(
                        $officialDocument
                    );

                    if (!$source) {
                        return null;
                    }

                    return $this->trimOrNull(
                        $source->NUMREG_CO99
                        ?? null
                    );
                }
            )
            ->filter(
                fn ($numreg) =>
                    $numreg !== null
            )
            ->unique()
            ->values();
    }

    /**
     * Estrae dalla prima riga del documento ufficiale il numero
     * dell'ordine cliente/web.
     *
     * Esempi ERP verificati:
     *
     * Ordine Cl. num. 2000000118 del 30/09/2026 Vs. num. 2000000118
     * Ordine Cl. num. 2000000543 del 31/10/2025 Vs. num. 2000000543 ...
     */
    private function resolveWebOrderNumber(
        DocumentHeader $document
    ): ?string {
        $firstRow = collect(
            $document->rows ?? []
        )
            ->sortBy(
                fn ($row) =>
                    (int) ($row->PROGRIGA_DO30 ?? 0)
            )
            ->first();

        if (!$firstRow) {
            return null;
        }

        $description = trim(
            (string) (
                $firstRow->DESCART_DO30
                ?? ''
            )
        );

        if ($description === '') {
            return null;
        }

        if (
            !preg_match(
                '/Ordine\s+Cl\.\s*num\.\s*([0-9]+)/i',
                $description,
                $matches
            )
        ) {
            return null;
        }

        return $this->trimOrNull(
            $matches[1] ?? null
        );
    }

    /**
     * Verifica che il numero ricavato dalla riga dell'ordine ufficiale
     * corrisponda realmente a un ordine importato dal web per lo stesso
     * cliente ERP.
     *
     * NUMDOC_MAGE non è globalmente univoco: per questo vengono sempre
     * applicati anche ditta, cliente e anno.
     */
    private function webOrderExists(
        DocumentHeader $document,
        string $webOrderNumber,
        int $ditta,
        int $clifor,
        string $documentDate
    ): bool {
        $year = $this->extractYear(
            $documentDate
        );

        if ($year === null) {
            return false;
        }

        return $document
            ->getConnection()
            ->table(self::WEB_ORDER_HEADER_TABLE)
            ->where(
                'WDO11_DITTA_CG18',
                $ditta
            )
            ->where(
                'WDO11_CLIFOR_CG44',
                $clifor
            )
            ->where(
                'WDO11_NUMDOC_MAGE',
                $webOrderNumber
            )
            ->where(
                'WDO11_ANNODOC_MAGE',
                $year
            )
            ->exists();
    }

    /**
     * Cerca il documento tecnico INTERNET della stessa giornata.
     *
     * Non viene utilizzata alcuna euristica basata sulla "vicinanza"
     * dei NUMREG.
     */
    private function loadInternetCandidates(
        DocumentHeader $document,
        int $ditta,
        int $clifor,
        string $documentDate,
        string $officialNumreg
    ) {
        return $document
            ->getConnection()
            ->table(self::ORDER_VIEW)
            ->where(
                'DITTA_CG18',
                $ditta
            )
            ->where(
                'CLIFOR_CG44',
                $clifor
            )
            ->where(
                'TIPODOCDECOD_MG36',
                'ORDINE'
            )
            ->where(
                'PROVENORD',
                'INTERNET'
            )
            ->whereRaw(
                'TRY_CONVERT(date, DATADOC_DO11, 103) = ' .
                'TRY_CONVERT(date, ?, 103)',
                [$documentDate]
            )
            ->whereRaw(
                'LTRIM(RTRIM(CONVERT(varchar(50), NUMREG_CO99))) <> ?',
                [$officialNumreg]
            )
            ->get([
                'NUMREG_CO99',
                'DITTA_CG18',
                'CLIFOR_CG44',
                'DATADOC_DO11',
                'NUMSEZDOC_DO11',
                'TIPODOCDECOD_MG36',
                'PROVENORD',
            ])
            ->unique(
                fn ($row) =>
                    trim(
                        (string) (
                            $row->NUMREG_CO99
                            ?? ''
                        )
                    )
            )
            ->values();
    }

    private function hasFulfillmentReferences(
        DocumentHeader $document,
        string $sourceNumreg
    ): bool {
        return $document
            ->getConnection()
            ->table('dbo.DETTRIGHECORPORIF_TOT')
            ->where(function ($query) use ($sourceNumreg) {
                $query
                    ->whereRaw(
                        'LTRIM(RTRIM(CONVERT(varchar(50), NUMREG_CO99))) = ?',
                        [$sourceNumreg]
                    )
                    ->orWhereRaw(
                        'LTRIM(RTRIM(CONVERT(varchar(50), NUMREG_CO99_ORD))) = ?',
                        [$sourceNumreg]
                    );
            })
            ->exists();
    }

    private function extractYear(
        string $date
    ): ?int {
        $date = trim($date);

        if (
            preg_match(
                '/^(\d{2})\/(\d{2})\/(\d{4})/',
                $date,
                $matches
            )
        ) {
            return (int) $matches[3];
        }

        if (
            preg_match(
                '/^(\d{4})-(\d{2})-(\d{2})/',
                $date,
                $matches
            )
        ) {
            return (int) $matches[1];
        }

        return null;
    }

    private function trimOrNull(
        mixed $value
    ): ?string {
        $value = trim(
            (string) ($value ?? '')
        );

        return $value !== ''
            ? $value
            : null;
    }
}