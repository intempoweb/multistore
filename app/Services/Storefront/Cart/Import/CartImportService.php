<?php

namespace App\Services\Storefront\Cart\Import;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Store;
use App\Services\Storefront\Cart\CartService;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class CartImportService
{
    public function __construct(
        protected CartService $cartService,
    ) {
    }

    public function import(Store $store, UploadedFile $file, ?Customer $customer = null): array
    {
        if (!$store->is_b2b) {
            throw new InvalidArgumentException('Import carrello disponibile solo per store B2B.');
        }

        $customer = $this->resolveCustomer($customer, $store);

        $rows = $this->readRows($file);

        if (empty($rows)) {
            throw new InvalidArgumentException('File vuoto o non leggibile.');
        }

        $mappedRows = $this->mapRows($rows);

        if (empty($mappedRows)) {
            throw new InvalidArgumentException('Nessuna riga valida trovata. Usa colonne SKU e quantità.');
        }

        $result = [
            'total_rows' => count($mappedRows),
            'imported' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($mappedRows as $index => $row) {
            $line = $row['_line'] ?? ($index + 1);
            $sku = trim((string) ($row['sku'] ?? ''));
            $qty = (float) str_replace(',', '.', (string) ($row['qty'] ?? 0));

            if ($sku === '') {
                $result['failed']++;
                $result['errors'][] = "Riga {$line}: SKU mancante.";
                continue;
            }

            if ($qty <= 0) {
                $result['failed']++;
                $result['errors'][] = "Riga {$line}: quantità non valida per SKU {$sku}.";
                continue;
            }

            $product = Product::query()
                ->where('ditta_cg18', (int) $store->ditta_cg18)
                ->where('site_type', (int) $store->erp_site_code)
                ->where('sku', $sku)
                ->where('is_active', true)
                ->first();

            if (!$product instanceof Product) {
                $result['failed']++;
                $result['errors'][] = "Riga {$line}: prodotto {$sku} non trovato o non attivo.";
                continue;
            }

            try {
                $this->cartService->addProduct(
                    store: $store,
                    product: $product,
                    quantity: $qty,
                    customer: $customer,
                );

                $result['imported']++;
            } catch (Throwable $exception) {
                $result['failed']++;
                $result['errors'][] = "Riga {$line} / SKU {$sku}: {$exception->getMessage()}";
            }
        }

        return $result;
    }

    protected function resolveCustomer(?Customer $customer, Store $store): ?Customer
    {
        if ($customer instanceof Customer) {
            return $customer;
        }

        $contextId = (string) request()->query('agent_context', '');

        if ($contextId !== '' && session()->get('agent_mode') === true) {
            $context = session()->get("agent_contexts.$contextId");

            if (is_array($context) && !empty($context['customer_id'])) {
                $contextCustomer = Customer::query()
                    ->active()
                    ->webEnabled()
                    ->where('id', (int) $context['customer_id'])
                    ->where('ditta_cg18', (int) $store->ditta_cg18)
                    ->first();

                if ($contextCustomer instanceof Customer) {
                    return $contextCustomer;
                }
            }
        }

        $authCustomer = auth('customer')->user();

        return $authCustomer instanceof Customer ? $authCustomer : null;
    }

    protected function readRows(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();

        return $sheet->toArray(null, true, true, true);
    }

    protected function mapRows(array $rows): array
    {
        $firstRow = reset($rows);

        if (!is_array($firstRow)) {
            return [];
        }

        $headers = $this->normalizeHeaders($firstRow);
        $hasHeaders = in_array('sku', $headers, true)
            && count(array_intersect($headers, ['qty', 'qta', 'quantita', 'quantity'])) > 0;

        $mapped = [];

        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }

            if ($hasHeaders && $rowIndex === array_key_first($rows)) {
                continue;
            }

            if ($hasHeaders) {
                $assoc = [];

                foreach ($row as $column => $value) {
                    $key = $headers[$column] ?? null;

                    if ($key !== null) {
                        $assoc[$key] = $value;
                    }
                }

                $mapped[] = [
                    '_line' => $rowIndex,
                    'sku' => $assoc['sku'] ?? null,
                    'qty' => $assoc['qty']
                        ?? $assoc['qta']
                        ?? $assoc['quantita']
                        ?? $assoc['quantity']
                        ?? null,
                ];

                continue;
            }

            $values = array_values($row);

            $mapped[] = [
                '_line' => $rowIndex,
                'sku' => $values[0] ?? null,
                'qty' => $values[1] ?? null,
            ];
        }

        return array_values(array_filter($mapped, function (array $row) {
            return trim((string) ($row['sku'] ?? '')) !== ''
                || trim((string) ($row['qty'] ?? '')) !== '';
        }));
    }

    protected function normalizeHeaders(array $row): array
    {
        $headers = [];

        foreach ($row as $column => $value) {
            $headers[$column] = str_replace(
                [' ', '-', '.'],
                '_',
                mb_strtolower(trim((string) $value))
            );
        }

        return $headers;
    }
}