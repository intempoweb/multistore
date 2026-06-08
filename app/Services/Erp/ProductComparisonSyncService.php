<?php

namespace App\Services\Erp;

use App\Models\Product;
use App\Models\ProductComparison;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductComparisonSyncService
{
    private static bool $erpSessionInitialized = false;

    private const DEFAULT_DITTE = [1, 3];
    private const CHUNK_SIZE = 300;

    private const COMPARISON_COLUMNS = [
        'buffetti' => 'COMPBUFFETTI_WEBT04',
        'flex' => 'COMPFLEX_WEBT04',
        'dataufficio' => 'COMPDATAUFFICIO_WEBT04',
        'semper' => 'COMPSEMPER_WEBT04',
        'edpro' => 'COMPEDPRO_WEBT04',
        'cierre' => 'COMPCIERRE_WEBT04',
        'screamo' => 'COMPSCREAMO_WEBT04',
        'biemme' => 'COMPBIEMME_WEBT04',
        'comp01' => 'COMP01_WEBT04',
        'comp02' => 'COMP02_WEBT04',
    ];

    public function sync(
        ?array $onlyDitte = null,
        ?array $onlySites = null,
        ?string $since = null,
        bool $dryRun = false,
        ?int $limit = null
    ): array {
        $this->initErpSession();

        $stats = [
            'local_products' => 0,
            'erp_rows' => 0,
            'comparison_upserts' => 0,
            'comparison_deletes' => 0,
            'missing_products' => 0,
            'skipped_by_date' => 0,
        ];

        $onlyDitte = $this->toIntArray($onlyDitte) ?: self::DEFAULT_DITTE;
        $onlySites = $this->toIntArray($onlySites);

        try {
            $query = Product::query()
                ->select(['id', 'ditta_cg18', 'site_type', 'sku'])
                ->whereIn('ditta_cg18', $onlyDitte)
                ->when(!empty($onlySites), fn ($q) => $q->whereIn('site_type', $onlySites))
                ->whereNotNull('sku')
                ->where('sku', '<>', '')
                ->orderBy('id');

            if ($limit !== null && $limit > 0) {
                $products = $query->limit($limit)->get();
                $this->syncProductChunk($products, $dryRun, $stats);

                return $stats;
            }

            $query->chunkById(self::CHUNK_SIZE, function (Collection $products) use ($dryRun, &$stats) {
                $this->syncProductChunk($products, $dryRun, $stats);
            });

            return $stats;
        } catch (Throwable $e) {
            Log::error('ERP Product Comparison Sync failed', [
                'message' => $e->getMessage(),
                'ditte' => $onlyDitte,
                'sites' => $onlySites,
                'limit' => $limit,
            ]);

            throw $e;
        }
    }

    private function syncProductChunk(Collection $products, bool $dryRun, array &$stats): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $stats['local_products'] += $products->count();

        $productsByKey = $products->keyBy(
            fn (Product $product) => $this->productKey(
                (int) $product->ditta_cg18,
                (int) $product->site_type,
                (string) $product->sku
            )
        );

        $rows = $this->fetchRowsFromErp($products);

        foreach ($rows as $row) {
            $stats['erp_rows']++;

            $ditta = (int) ($row->DITTA_CG18 ?? 0);
            $site = (int) ($row->FLG_B2B_B2C_WEBT04 ?? 0);
            $sku = $this->trimOrNull($row->CODART_MG66 ?? null);
            $lastChange = $this->toDate($row->LASTCHANGE ?? null);

            if ($ditta <= 0 || $site <= 0 || $sku === null) {
                continue;
            }

            $product = $productsByKey->get($this->productKey($ditta, $site, $sku));

            if (!$product instanceof Product) {
                $stats['missing_products']++;
                continue;
            }

            $comparisons = $this->extractComparisons($row);

            if ($dryRun) {
                if (empty($comparisons)) {
                    $stats['comparison_deletes']++;
                } else {
                    $stats['comparison_upserts'] += count($comparisons);
                }

                continue;
            }

            $deleted = ProductComparison::query()
                ->where('ditta_cg18', $ditta)
                ->where('site_type', $site)
                ->where('sku', $sku)
                ->delete();

            $stats['comparison_deletes'] += (int) $deleted;

            foreach ($comparisons as $source => $comparisonSku) {
                ProductComparison::updateOrCreate(
                    [
                        'ditta_cg18' => $ditta,
                        'site_type' => $site,
                        'sku' => $sku,
                        'source' => $source,
                        'comparison_sku' => $comparisonSku,
                    ],
                    [
                        'erp_lastchange' => $lastChange,
                    ]
                );

                $stats['comparison_upserts']++;
            }
        }
    }

    private function fetchRowsFromErp(Collection $products): array
    {
        $filters = $products
            ->map(fn (Product $product) => [
                'ditta' => (int) $product->ditta_cg18,
                'site' => (int) $product->site_type,
                'sku' => strtoupper(trim((string) $product->sku)),
            ])
            ->filter(fn (array $item) => $item['ditta'] > 0 && $item['site'] > 0 && $item['sku'] !== '')
            ->unique(fn (array $item) => $item['ditta'] . '|' . $item['site'] . '|' . $item['sku'])
            ->values();

        if ($filters->isEmpty()) {
            return [];
        }

        $query = DB::connection('erp')
            ->table('dbo.ANAGRARTCOMP_WEBT04')
            ->select(array_merge([
                'DITTA_CG18',
                'CODART_MG66',
                'FLG_B2B_B2C_WEBT04',
                'LASTCHANGE',
            ], array_values(self::COMPARISON_COLUMNS)));

        $query->where(function ($outer) use ($filters) {
            foreach ($filters as $filter) {
                $outer->orWhere(function ($q) use ($filter) {
                    $q->where('DITTA_CG18', $filter['ditta'])
                        ->where('FLG_B2B_B2C_WEBT04', $filter['site'])
                        ->where('CODART_MG66', $filter['sku']);
                });
            }
        });

        return $query
            ->orderBy('DITTA_CG18')
            ->orderBy('FLG_B2B_B2C_WEBT04')
            ->orderBy('CODART_MG66')
            ->get()
            ->all();
    }

    private function extractComparisons(object $row): array
    {
        $comparisons = [];

        foreach (self::COMPARISON_COLUMNS as $source => $erpColumn) {
            $comparisonSku = $this->trimOrNull($row->{$erpColumn} ?? null);

            if ($comparisonSku !== null) {
                $comparisons[$source] = $comparisonSku;
            }
        }

        return $comparisons;
    }

    private function initErpSession(): void
    {
        if (self::$erpSessionInitialized) {
            return;
        }

        $conn = DB::connection('erp');
        $conn->statement('SET ANSI_NULLS ON');
        $conn->statement('SET ANSI_WARNINGS ON');

        self::$erpSessionInitialized = true;
    }

    private function productKey(int $ditta, int $site, string $sku): string
    {
        return $ditta . '|' . $site . '|' . strtoupper(trim($sku));
    }

    private function trimOrNull($value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string === '' ? null : $string;
    }

    private function toDate($value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $string)
            ? substr($string, 0, 10)
            : null;
    }

    private function toIntArray(?array $values): ?array
    {
        if (empty($values)) {
            return null;
        }

        $out = [];

        foreach ($values as $value) {
            $number = (int) $value;

            if ($number > 0) {
                $out[] = $number;
            }
        }

        $out = array_values(array_unique($out));

        return empty($out) ? null : $out;
    }
}