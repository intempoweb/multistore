<?php

namespace App\Services\Storefront\Documents;

use App\Models\Erp\DocumentHeader;
use App\Models\Erp\DocumentRow;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DocumentExcelExportService
{
    private const DISCOUNT_COLUMNS = [
        1 => ['SCPER1_DO30', 'SCONTOPERC1_DO30', 'SCONTO1_DO30'],
        2 => ['SCPER2_DO30', 'SCONTOPERC2_DO30', 'SCONTO2_DO30'],
        3 => ['SCPER3_DO30', 'SCONTOPERC3_DO30', 'SCONTO3_DO30'],
    ];

    private const VAT_CODE_COLUMNS = [
        'CODIVA_CG28',
        'CODIVA_DO30',
        'CODIVA',
    ];

    private const HEADERS = [
        'NOME IMMAGINE',
        'PROGRESSIVO',
        'CODICE ARTICOLO',
        'DESCRIZIONE ARTICOLO',
        'UNITÀ DI MISURA',
        'QUANTITÀ',
        'PREZZO UNITARIO',
        'SCONTO 1',
        'SCONTO 2',
        'SCONTO 3',
        'TOTALE',
        'CODICE IVA',
        'BARCODE',
    ];

    public function build(DocumentHeader $document): string
    {
        $rows = collect($document->rows ?? []);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Documento');
        $sheet->fromArray(self::HEADERS, null, 'A1');

        $rowNumber = 2;

        foreach ($rows as $row) {
            if (!$row instanceof DocumentRow) {
                continue;
            }

            $product = $row->attachedProduct();

            $sheet->fromArray([
                $row->mainMediaFilename() ?? '',
                $this->value($row, 'PROGRIGA_DO30'),
                $this->value($row, 'CODART_MG66'),
                $this->value($row, 'DESCART_DO30'),
                $this->value($row, 'UM1_DO30'),
                $this->numberValue($row, 'QTA1_DO30'),
                $this->numberValue($row, 'PREZZO1_DO30'),
                $this->firstAvailableValue($row, self::DISCOUNT_COLUMNS[1]),
                $this->firstAvailableValue($row, self::DISCOUNT_COLUMNS[2]),
                $this->firstAvailableValue($row, self::DISCOUNT_COLUMNS[3]),
                $this->numberValue($row, 'IMPNETSCP_DO30'),
                $this->firstAvailableValue($row, self::VAT_CODE_COLUMNS),
                $product?->barcode ?? '',
            ], null, 'A' . $rowNumber);

            $rowNumber++;
        }

        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getStyle('A1:M1')->getFont()->setBold(true);

        $directory = storage_path('app/tmp/document-exports');

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory . '/'
            . $this->safeFilename('documento-' . (string) $document->NUMREG_CO99)
            . '.xlsx';

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public static function unresolvedColumnMappings(): array
    {
        return [
            'discounts' => self::DISCOUNT_COLUMNS,
            'vat_code' => self::VAT_CODE_COLUMNS,
        ];
    }

    private function firstAvailableValue(DocumentRow $row, array $columns): mixed
    {
        foreach ($columns as $column) {
            $value = $row->getAttribute($column);

            if ($value !== null && trim((string) $value) !== '') {
                return $value;
            }
        }

        return '';
    }

    private function value(DocumentRow $row, string $column): mixed
    {
        return $row->getAttribute($column) ?? '';
    }

    private function numberValue(DocumentRow $row, string $column): float|string
    {
        $value = $row->getAttribute($column);

        if ($value === null || trim((string) $value) === '') {
            return '';
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : (string) $value;
    }

    private function safeFilename(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?: 'documento';
        $value = trim($value, '-_.');

        return $value !== '' ? $value : 'documento';
    }
}
