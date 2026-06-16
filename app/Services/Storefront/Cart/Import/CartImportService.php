<?php

namespace App\Services\Storefront\Cart\Import;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Store;
use App\Services\Storefront\Cart\CartService;
use Illuminate\Database\Eloquent\Builder;
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

    protected array $resolvedProductCache = [];

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
            throw new InvalidArgumentException('Nessuna riga valida trovata. Usa colonne codice articolo e quantità.');
        }

        $result = [
            'total_rows' => count($mappedRows),
            'imported' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($mappedRows as $index => $row) {
            $line = $row['_line'] ?? ($index + 1);
            $code = $this->normalizeProductCode($row['code'] ?? null);
            $qty = (float) str_replace(',', '.', (string) ($row['qty'] ?? 0));

            if ($code === '') {
                $result['failed']++;
                $result['errors'][] = "Riga {$line}: codice articolo mancante.";
                continue;
            }

            if ($qty <= 0) {
                $result['failed']++;
                $result['errors'][] = "Riga {$line}: quantità non valida per codice articolo {$code}.";
                continue;
            }

            $product = $this->resolveProduct($store, $code);

            if (!$product instanceof Product) {
                $result['failed']++;
                $result['errors'][] = "Riga {$line}: prodotto {$code} non trovato o non attivo.";
                continue;
            }

            $resolvedQty = $this->normalizeQuantityForProduct($product, $qty);

            try {
                $this->cartService->addProduct(
                    store: $store,
                    product: $product,
                    quantity: $resolvedQty,
                    customer: $customer,
                );

                $result['imported']++;
            } catch (Throwable $exception) {
                $result['failed']++;
                $result['errors'][] = "Riga {$line} / codice articolo {$code}: {$exception->getMessage()}";
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
        $hasCodeHeader = count(array_intersect($headers, $this->codeHeaderAliases())) > 0;
        $hasQtyHeader = count(array_intersect($headers, $this->qtyHeaderAliases())) > 0;
        $hasHeaders = $hasCodeHeader && $hasQtyHeader;

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
                    'code' => $this->firstAssocValue($assoc, $this->codeHeaderAliases()),
                    'qty' => $this->firstAssocValue($assoc, $this->qtyHeaderAliases()),
                ];

                continue;
            }

            $values = array_values($row);

            $mapped[] = [
                '_line' => $rowIndex,
                'code' => $values[0] ?? null,
                'qty' => $values[1] ?? null,
            ];
        }

        return array_values(array_filter($mapped, function (array $row) {
            return trim((string) ($row['code'] ?? '')) !== ''
                || trim((string) ($row['qty'] ?? '')) !== '';
        }));
    }

    protected function resolveProduct(Store $store, string $code): ?Product
    {
        $cacheKey = implode('|', [
            (int) $store->ditta_cg18,
            (int) $store->erp_site_code,
            $code,
        ]);

        if (array_key_exists($cacheKey, $this->resolvedProductCache)) {
            return $this->resolvedProductCache[$cacheKey];
        }

        $baseQuery = Product::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('site_type', (int) $store->erp_site_code)
            ->where('is_active', true);

        $product = (clone $baseQuery)
            ->where('sku', $code)
            ->first();

        if (!$product instanceof Product) {
            $product = (clone $baseQuery)
                ->where('barcode', $code)
                ->first();
        }

        if (!$product instanceof Product && strlen($code) >= 4) {
            $product = (clone $baseQuery)
                ->where(function (Builder $query) use ($code) {
                    $query->where('barcode', 'like', '%' . $code)
                        ->orWhere('sku', 'like', '%' . $code);
                })
                ->orderBy('sku')
                ->first();
        }

        return $this->resolvedProductCache[$cacheKey] = $product instanceof Product ? $product : null;
    }

    protected function normalizeQuantityForProduct(Product $product, float $qty): float
    {
        $constraints = $this->cartService->resolveQuantityConstraintsForProduct($product);

        $quantityMin = max(1, (float) ($constraints['quantity_min'] ?? 1));
        $quantityStep = max(1, (float) ($constraints['quantity_step'] ?? 1));
        $resolvedQty = max($qty, $quantityMin);

        if ($quantityStep <= 1) {
            return $resolvedQty;
        }

        $steps = (int) ceil($resolvedQty / $quantityStep);

        return max($quantityMin, $steps * $quantityStep);
    }

    protected function firstAssocValue(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }

    protected function codeHeaderAliases(): array
    {
        return [
            'sku',
            'codice',
            'codice_articolo',
            'codicearticolo',
            'cod_articolo',
            'codart',
            'cod_art',
            'codarticolo',
            'articolo',
            'codice_prodotto',
            'product_code',
            'barcode',
            'bar_code',
            'ean',
            'ean13',
        ];
    }

    protected function qtyHeaderAliases(): array
    {
        return [
            'qty',
            'qta',
            'quantita',
            'quantità',
            'quantity',
            'pezzi',
            'pz',
        ];
    }

    protected function normalizeProductCode(mixed $value): string
    {
        return trim((string) $value);
    }

    protected function normalizeHeaders(array $row): array
    {
        $headers = [];

        foreach ($row as $column => $value) {
            $headers[$column] = str_replace(
                [' ', '-', '.', '/', '\\'],
                '_',
                mb_strtolower(trim((string) $value))
            );
        }

        return $headers;
    }
}