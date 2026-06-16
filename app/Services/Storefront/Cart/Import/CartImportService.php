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

        $this->resolvedProductCache = [];
        $customer = $this->resolveCustomer($customer, $store);

        $rows = $this->readRows($file);

        if (empty($rows)) {
            throw new InvalidArgumentException('File vuoto o non leggibile.');
        }

        $mappedRows = $this->mapRows($rows);

        if (empty($mappedRows)) {
            throw new InvalidArgumentException('Nessuna riga valida trovata. Usa colonne codice articolo e quantità.');
        }

        $this->warmProductCache($store, $mappedRows);

        $result = [
            'total_rows' => count($mappedRows),
            'imported' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
            'skipped_rows' => [],
        ];

        $linesBySku = [];
        $productsBySku = [];
        $quantitiesBySku = [];

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

            if (!$this->isProductAvailableForImport($product)) {
                $result['skipped']++;
                $result['skipped_rows'][] = "Riga {$line}: prodotto {$code} non importato perché non disponibile.";
                continue;
            }

            $sku = (string) $product->sku;
            $productsBySku[$sku] = $product;
            $quantitiesBySku[$sku] = ($quantitiesBySku[$sku] ?? 0) + $qty;
            $linesBySku[$sku][] = $line;
        }

        if (empty($productsBySku)) {
            return $result;
        }

        $itemsToAdd = [];

        foreach ($productsBySku as $sku => $product) {
            $requestedQty = (float) ($quantitiesBySku[$sku] ?? 0);
            $resolvedQty = $this->normalizeQuantityForProduct($product, $requestedQty);

            if (!$this->hasEnoughStockForImport($product, $resolvedQty)) {
                $result['skipped']++;
                $lineLabel = implode(', ', array_unique(array_map('strval', $linesBySku[$sku] ?? [])));
                $result['skipped_rows'][] = "Riga {$lineLabel}: prodotto {$sku} non importato perché non disponibile nella quantità richiesta.";
                continue;
            }

            $itemsToAdd[] = [
                'product' => $product,
                'quantity' => $resolvedQty,
                'lines' => $linesBySku[$sku] ?? [],
            ];
        }

        if (empty($itemsToAdd)) {
            return $result;
        }

        if (method_exists($this->cartService, 'addProducts')) {
            try {
                $this->cartService->addProducts(
                    store: $store,
                    items: $itemsToAdd,
                    customer: $customer,
                );

                $result['imported'] += count($itemsToAdd);

                return $result;
            } catch (Throwable $exception) {
                $result['errors'][] = 'Import massivo non completato, provo inserimento riga per riga: ' . $exception->getMessage();
            }
        }

        foreach ($itemsToAdd as $item) {
            /** @var Product $product */
            $product = $item['product'];
            $resolvedQty = (float) $item['quantity'];
            $lineLabel = implode(', ', array_unique(array_map('strval', $item['lines'] ?? [])));
            $sku = (string) $product->sku;

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
                $result['errors'][] = "Riga {$lineLabel} / codice articolo {$sku}: {$exception->getMessage()}";
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

    protected function warmProductCache(Store $store, array $mappedRows): void
    {
        $codes = collect($mappedRows)
            ->map(fn (array $row) => $this->normalizeProductCode($row['code'] ?? null))
            ->filter()
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return;
        }

        $products = Product::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('site_type', (int) $store->erp_site_code)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($codes) {
                $query->whereIn('sku', $codes->all())
                    ->orWhereIn('barcode', $codes->all());
            })
            ->get();

        foreach ($codes as $code) {
            $cacheKey = $this->productCacheKey($store, $code);
            $product = $products->first(function (Product $product) use ($code) {
                return (string) $product->sku === $code
                    || (string) ($product->barcode ?? '') === $code;
            });

            if ($product instanceof Product) {
                $this->resolvedProductCache[$cacheKey] = $product;
            }
        }
    }

    protected function resolveProduct(Store $store, string $code): ?Product
    {
        $cacheKey = $this->productCacheKey($store, $code);

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

    protected function isProductAvailableForImport(Product $product): bool
    {
        if ((bool) ($product->no_backorder ?? false) !== true) {
            return true;
        }

        if ($product->stock_qty === null) {
            return true;
        }

        return (float) $product->stock_qty > 0;
    }

    protected function hasEnoughStockForImport(Product $product, float $quantity): bool
    {
        if ((bool) ($product->no_backorder ?? false) !== true) {
            return true;
        }

        if ($product->stock_qty === null) {
            return true;
        }

        return $quantity <= (float) $product->stock_qty;
    }

    protected function productCacheKey(Store $store, string $code): string
    {
        return implode('|', [
            (int) $store->ditta_cg18,
            (int) $store->erp_site_code,
            $code,
        ]);
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