<?php

namespace App\Services\Storefront\Documents;

use App\Models\Erp\DocumentHeader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentGoodsDestinationResolver
{
    private const LINKED_SERVER = '[135.125.255.90,41433]';

    public function attach(DocumentHeader $document): DocumentHeader
    {
        $destination = $this->resolve($document);

        if ($destination !== null) {
            $document->setAttribute(
                'document_goods_destination',
                $destination
            );
        }

        return $document;
    }

    public function resolve(DocumentHeader $document): ?array
    {
        $numreg = trim((string) ($document->NUMREG_CO99 ?? ''));

        if ($numreg === '') {
            return null;
        }

        if (! preg_match('/^[A-Za-z0-9._ -]+$/', $numreg)) {
            return null;
        }

        try {
            $rows = collect(
                DB::connection('erp')->select(
                    $this->destinationSql($numreg)
                )
            );
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve ERP document goods destination', [
                'numreg_co99' => $numreg,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        /*
         * Priorità 1:
         * destinazione merce specifica del documento (MG22).
         *
         * Se almeno una riga contiene una destinazione MG22 valida,
         * utilizziamo esclusivamente queste informazioni.
         */
        $goodsDestinations = $rows
            ->map(fn ($row) => [
                'code' => $this->trimOrNull(
                    $row->goods_destination_code ?? null
                ),
                'name' => $this->trimOrNull(
                    $row->goods_destination_name ?? null
                ),
                'address' => $this->trimOrNull(
                    $row->goods_destination_address ?? null
                ),
                'postcode' => $this->trimOrNull(
                    $row->goods_destination_postcode ?? null
                ),
                'city' => $this->trimOrNull(
                    $row->goods_destination_city ?? null
                ),
                'province' => $this->trimOrNull(
                    $row->goods_destination_province ?? null
                ),
            ])
            ->filter(
                fn (array $destination) => $this->hasDestinationData(
                    $destination
                )
            )
            ->unique(
                fn (array $destination) => $this->destinationKey(
                    $destination
                )
            )
            ->values();

        if ($goodsDestinations->isNotEmpty()) {
            return $this->resolveDestinationCollection(
                $goodsDestinations
            );
        }

        /*
         * Priorità 2:
         * se il documento non possiede una destinazione merce MG22,
         * utilizziamo l'indirizzo anagrafico CG16 restituito dalla
         * stessa vista ERP.
         *
         * Alcuni documenti possono produrre più righe dalla vista:
         * quelle completamente vuote vengono ignorate.
         */
        $customerDestinations = $rows
            ->map(fn ($row) => [
                'code' => null,
                'name' => null,
                'address' => $this->trimOrNull(
                    $row->customer_address ?? null
                ),
                'postcode' => $this->trimOrNull(
                    $row->customer_postcode ?? null
                ),
                'city' => $this->trimOrNull(
                    $row->customer_city ?? null
                ),
                'province' => $this->trimOrNull(
                    $row->customer_province ?? null
                ),
            ])
            ->filter(
                fn (array $destination) => $this->hasDestinationData(
                    $destination
                )
            )
            ->unique(
                fn (array $destination) => $this->destinationKey(
                    $destination
                )
            )
            ->values();

        if ($customerDestinations->isEmpty()) {
            return null;
        }

        return $this->resolveDestinationCollection(
            $customerDestinations
        );
    }

    private function destinationSql(string $numreg): string
    {
        $quote = "'";

        $innerQuery = implode(' ', [
            'SELECT DISTINCT',
            'DO30_NUMREG_CO99,',

            /*
             * Destinazione merce specifica.
             */
            'LTRIM(RTRIM(MG22_CODDESTIN)) AS goods_destination_code,',
            'LTRIM(RTRIM(MG22_DESTRAGSOC)) AS goods_destination_name,',
            'LTRIM(RTRIM(MG22_DESTIND)) AS goods_destination_address,',
            'LTRIM(RTRIM(MG22_DESTCAPCHAR)) AS goods_destination_postcode,',
            'LTRIM(RTRIM(MG22_DESTCITTA)) AS goods_destination_city,',
            'LTRIM(RTRIM(MG22_DESTPROV)) AS goods_destination_province,',

            /*
             * Indirizzo anagrafico cliente, utilizzato esclusivamente
             * come fallback quando MG22 è completamente assente.
             */
            'LTRIM(RTRIM(CG16_INDIRIZZO)) AS customer_address,',
            'LTRIM(RTRIM(CG16_CAP)) AS customer_postcode,',
            'LTRIM(RTRIM(CG16_CITTA)) AS customer_city,',
            'LTRIM(RTRIM(CG16_PROV)) AS customer_province',

            'FROM GAMMA.dbo.VDO11_CLIFORFATT',

            'WHERE LTRIM(RTRIM(CONVERT(varchar(50), DO30_NUMREG_CO99))) =',
            $quote . $quote . $numreg . $quote . $quote,
        ]);

        return 'SELECT * FROM OPENQUERY('
            . self::LINKED_SERVER
            . ', '
            . $quote
            . $innerQuery
            . $quote
            . ')';
    }

    private function resolveDestinationCollection(Collection $destinations): ?array
    {
        if ($destinations->isEmpty()) {
            return null;
        }

        if ($destinations->count() === 1) {
            return $destinations->first();
        }

        return [
            'code' => null,
            'name' => null,
            'address' => $destinations
                ->map(fn (array $destination) => self::format($destination))
                ->filter()
                ->implode(' | '),
            'postcode' => null,
            'city' => null,
            'province' => null,
        ];
    }

    private function destinationKey(array $destination): string
    {
        return implode('|', array_map(
            fn ($value) => (string) ($value ?? ''),
            $destination
        ));
    }

    public static function format(?array $destination): string
    {
        if ($destination === null) {
            return '';
        }

        $name = trim((string) ($destination['name'] ?? ''));
        $address = trim((string) ($destination['address'] ?? ''));
        $postcode = trim((string) ($destination['postcode'] ?? ''));
        $city = trim((string) ($destination['city'] ?? ''));
        $province = trim((string) ($destination['province'] ?? ''));

        $cityLine = trim(
            implode(' ', array_filter([
                $postcode,
                $city,
            ]))
            . ($province !== '' ? ' ' . $province : '')
        );

        return collect([
            $name,
            $address,
            $cityLine,
        ])
            ->filter(fn (string $part) => $part !== '')
            ->implode(' - ');
    }

    private function hasDestinationData(array $row): bool
    {
        return collect($row)
            ->except('code')
            ->filter(
                fn ($value) => trim((string) ($value ?? '')) !== ''
            )
            ->isNotEmpty();
    }

    private function trimOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}