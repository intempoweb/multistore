<?php

namespace App\Services\Shipping\Import;

use App\Models\ShippingRule;
use RuntimeException;

class ShippingTableImportService
{
    public function importFromCsv(string $path, int $storeId): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('File CSV non trovato o non leggibile.');
        }

        $delimiter = $this->detectDelimiter($path);
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Impossibile aprire il file CSV.');
        }

        $rowIndex = 0;

        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowIndex++;

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $row = $this->normalizeRow($row);

                if ($rowIndex === 1 && $this->looksLikeHeader($row)) {
                    continue;
                }

                if (count($row) < 5) {
                    throw new RuntimeException(
                        "Riga {$rowIndex}: formato non valido. Attese 5 colonne: Nazione, Provincia, CAP, Peso, Prezzo."
                    );
                }

                $country = $this->normalizeWildcardString($row[0], true);
                $province = $this->normalizeWildcardString($row[1], true);
                $cap = $this->normalizeWildcardString($row[2], true);

                $weightFrom = $this->normalizeDecimal(
                    $row[3],
                    "Riga {$rowIndex}: peso non valido."
                );

                $amount = $this->normalizeDecimal(
                    $row[4],
                    "Riga {$rowIndex}: prezzo non valido."
                );

                ShippingRule::query()->create([
                    'store_id' => $storeId,
                    'type' => 'table',
                    'country' => $country,
                    'province' => $province,
                    'cap' => $cap,
                    'weight_from' => $weightFrom,
                    'amount' => $amount,
                    'priority' => 0,
                    'is_active' => true,
                ]);
            }
        } finally {
            fclose($handle);
        }
    }

    protected function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return ',';
        }

        try {
            $firstLine = (string) fgets($handle);
        } finally {
            fclose($handle);
        }

        $semicolonCount = substr_count($firstLine, ';');
        $commaCount = substr_count($firstLine, ',');

        return $semicolonCount > $commaCount ? ';' : ',';
    }

    protected function normalizeRow(array $row): array
    {
        if (isset($row[0])) {
            $row[0] = $this->stripUtf8Bom((string) $row[0]);
        }

        return $row;
    }

    protected function stripUtf8Bom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    protected function looksLikeHeader(array $row): bool
    {
        $first = strtolower(trim((string) ($row[0] ?? '')));

        return str_contains($first, 'naz')
            || str_contains($first, 'country');
    }

    protected function normalizeWildcardString(?string $value, bool $uppercase = false): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $normalized = $uppercase ? strtoupper($value) : $value;
        $upper = strtoupper($normalized);

        if (in_array($upper, ['ALL', '*', '•', '-', '--'], true)) {
            return null;
        }

        return $normalized;
    }

    protected function normalizeDecimal(mixed $value, string $errorMessage): float
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new RuntimeException($errorMessage);
        }

        $value = str_replace(',', '.', $value);

        if (!is_numeric($value)) {
            throw new RuntimeException($errorMessage);
        }

        return round((float) $value, 3);
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}