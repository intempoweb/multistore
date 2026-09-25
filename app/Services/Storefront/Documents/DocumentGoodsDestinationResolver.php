<?php

namespace App\Services\Storefront\Documents;

use App\Models\Erp\DocumentHeader;
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
                DB::connection('erp')->select($this->destinationSql($numreg))
            );
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve ERP document goods destination', [
                'numreg_co99' => $numreg,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $rows = $rows
            ->map(fn ($row) => [
                'code' => $this->trimOrNull($row->code ?? null),
                'name' => $this->trimOrNull($row->name ?? null),
                'address' => $this->trimOrNull($row->address ?? null),
                'postcode' => $this->trimOrNull($row->postcode ?? null),
                'city' => $this->trimOrNull($row->city ?? null),
                'province' => $this->trimOrNull($row->province ?? null),
            ])
            ->filter(fn (array $row) => $this->hasDestinationData($row))
            ->unique(fn (array $row) => implode('|', array_map(
                fn ($value) => (string) ($value ?? ''),
                $row
            )))
            ->values();

        if ($rows->isEmpty()) {
            return null;
        }

        if ($rows->count() === 1) {
            return $rows->first();
        }

        return [
            'code' => null,
            'name' => null,
            'address' => $rows
                ->map(fn (array $row) => self::format($row))
                ->filter()
                ->implode(' | '),
            'postcode' => null,
            'city' => null,
            'province' => null,
        ];
    }

    private function destinationSql(string $numreg): string
    {
        $quote = "'";
        $innerQuery = implode(' ', [
            'SELECT DISTINCT',
            'DO30_NUMREG_CO99,',
            'LTRIM(RTRIM(MG22_CODDESTIN)) AS code,',
            'LTRIM(RTRIM(MG22_DESTRAGSOC)) AS name,',
            'LTRIM(RTRIM(MG22_DESTIND)) AS address,',
            'LTRIM(RTRIM(MG22_DESTCAPCHAR)) AS postcode,',
            'LTRIM(RTRIM(MG22_DESTCITTA)) AS city,',
            'LTRIM(RTRIM(MG22_DESTPROV)) AS province',
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
            implode(' ', array_filter([$postcode, $city]))
            . ($province !== '' ? ' ' . $province : '')
        );

        return collect([$name, $address, $cityLine])
            ->filter(fn (string $part) => $part !== '')
            ->implode(' - ');
    }

    private function hasDestinationData(array $row): bool
    {
        return collect($row)
            ->except('code')
            ->filter(fn ($value) => trim((string) ($value ?? '')) !== '')
            ->isNotEmpty();
    }

    private function trimOrNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
