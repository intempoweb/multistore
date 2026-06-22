<?php

namespace App\Services\Shipping\Export;

use App\Models\ShippingRule;
use RuntimeException;

class ShippingTableExportService
{
    public function writeCsv(iterable $rules, mixed $handle): void
    {
        if (!is_resource($handle)) {
            throw new RuntimeException('Destinazione CSV non valida.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Nazione', 'Provincia', 'CAP', 'Peso (e superiore)', 'Prezzo di spedizione'], ';', '"', '');

        foreach ($rules as $rule) {
            if (!$rule instanceof ShippingRule) {
                continue;
            }

            fputcsv($handle, [
                $rule->country ?: '*',
                $rule->province ?: '*',
                $rule->cap ?: '*',
                $this->formatDecimal($rule->weight_from),
                $this->formatDecimal($rule->amount),
            ], ';', '"', '');
        }
    }

    private function formatDecimal(mixed $value): string
    {
        $formatted = number_format((float) $value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
