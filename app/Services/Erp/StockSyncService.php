<?php

namespace App\Services\Erp;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StockSyncService
{
    private static bool $erpSessionInitialized = false;
    private const ERP_SKU_CHUNK_SIZE = 800;

    private function initErpSession(): void
    {
        if (self::$erpSessionInitialized) return;

        $conn = DB::connection('erp');
        $conn->statement('SET ANSI_NULLS ON');
        $conn->statement('SET ANSI_WARNINGS ON');

        self::$erpSessionInitialized = true;
    }

    /**
     * Sync stock da dbo.MAGPROQTAUNICA (magazzino unico -> stock globale)
     * Aggiorna SOLO products.type='simple' (i padri non hanno stock reale).
     *
     * @return array{
     *   rows:int,
     *   updated:int,
     *   skipped_missing_product:int,
     *   skipped_by_date:int
     * }
     */
    public function sync(
        ?array $onlyDitte = null,
        ?array $onlySites = null,
        ?string $since = null,
        bool $dryRun = false,
        ?int $limit = null
    ): array {
        $this->initErpSession();

        $stats = [
            'rows' => 0,
            'updated' => 0,
            'skipped_missing_product' => 0,
            'skipped_by_date' => 0,
        ];

        $onlyDitte = $this->toIntArray($onlyDitte);
        $onlySites = $this->toIntArray($onlySites);
        $sinceDate = $this->normalizeSinceDate($since);

        try {
            $localSkus = $this->localSimpleSkus($onlyDitte, $onlySites);

            if ($limit !== null) {
                $localSkus = array_slice($localSkus, 0, (int) $limit);
            }

            foreach (array_chunk($localSkus, self::ERP_SKU_CHUNK_SIZE) as $skuChunk) {
                $rows = $this->retryTransientErpQuery(
                    function () use ($skuChunk, $sinceDate) {
                        return DB::connection('erp')
                            ->table('dbo.MAGPROQTAUNICA')
                            ->select([
                                'CODART_MG66',
                                'QGIACATT_MG70',
                                'FLGNOORDINZERO_WEBT01',
                                'DATAULTVAR_MG70',
                                // li teniamo letti (potrebbero servire dopo)
                                'FLGSEMAFORO',
                                'QTA1SEMAFORO',
                                'QTA2SEMAFORO',
                            ])
                            ->where('DATAULTVAR_MG70', '>=', $sinceDate)
                            ->whereIn('CODART_MG66', $skuChunk)
                            ->get();
                    },
                    'stock_changed_rows',
                    [
                        'since' => $sinceDate,
                        'sku_count' => count($skuChunk),
                    ]
                );

                foreach ($rows as $r) {
                    if ($limit !== null && $stats['rows'] >= $limit) {
                        break 2;
                    }

                    $stats['rows']++;

                    $sku = $this->trimOrNull($r->CODART_MG66 ?? null);
                    if (!$sku) continue;

                    $rowDate = $this->toDate($r->DATAULTVAR_MG70 ?? null);
                    if (!$rowDate || $rowDate < $sinceDate) {
                        $stats['skipped_by_date']++;
                        continue;
                    }

                    $qty = $this->toFloat($r->QGIACATT_MG70 ?? null);
                    $noBackorder = $this->toBool($r->FLGNOORDINZERO_WEBT01 ?? null, false);

                    // ✅ MAGAZZINO UNICO: aggiorno TUTTI i SIMPLE locali con quello SKU
                    $baseQ = Product::query()
                        ->where('sku', $sku)
                        ->where('type', 'simple');

                    if (!empty($onlySites)) $baseQ->whereIn('site_type', $onlySites);
                    if (!empty($onlyDitte)) $baseQ->whereIn('ditta_cg18', $onlyDitte);

                    // ⚠️ NON riusare la stessa query dopo count()
                    $count = (int) (clone $baseQ)->count();

                    if ($count === 0) {
                        $stats['skipped_missing_product']++;
                        continue;
                    }

                    if ($dryRun) {
                        $stats['updated'] += $count;
                        continue;
                    }

                    (clone $baseQ)->update([
                        'stock_qty' => $qty,
                        'no_backorder' => $noBackorder,
                    ]);

                    $stats['updated'] += $count;
                }
            }

            return $stats;
        } catch (Throwable $e) {
            Log::error('ERP Stock Sync failed', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    private function localSimpleSkus(?array $onlyDitte, ?array $onlySites): array
    {
        $query = Product::query()
            ->where('type', 'simple')
            ->whereNotNull('sku');

        if (!empty($onlySites)) $query->whereIn('site_type', $onlySites);
        if (!empty($onlyDitte)) $query->whereIn('ditta_cg18', $onlyDitte);

        return $query
            ->distinct()
            ->orderBy('sku')
            ->pluck('sku')
            ->map(fn ($sku) => $this->trimOrNull($sku))
            ->filter()
            ->values()
            ->all();
    }

    private function retryTransientErpQuery(callable $callback, string $operation, array $context): mixed
    {
        $attempts = 3;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $callback();
            } catch (Throwable $e) {
                if ($attempt >= $attempts || !$this->isTransientErpFailure($e)) {
                    throw $e;
                }

                Log::warning('Transient ERP query failure, retrying.', $context + [
                    'operation' => $operation,
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);

                DB::disconnect('erp');
                self::$erpSessionInitialized = false;
                usleep(500000 * $attempt);
                $this->initErpSession();
            }
        }
    }

    private function isTransientErpFailure(Throwable $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return str_contains($message, 'resource limit was reached')
            || str_contains($message, 'sqlncli11')
            || str_contains($message, 'query timeout')
            || str_contains($message, 'deadlock')
            || str_contains($message, 'transport-level error')
            || str_contains($message, 'communication link failure');
    }

    private function normalizeSinceDate(?string $since): string
    {
        if ($since) {
            $s = trim($since);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
        }
        return Carbon::now('Europe/Rome')->toDateString();
    }

    private function trimOrNull($v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }

    private function toDate($v): ?string
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return substr($s, 0, 10);
        return null;
    }

    private function toFloat($v): float
    {
        if ($v === null) return 0.0;
        $s = str_replace(',', '.', (string) $v);
        return (float) $s;
    }

    private function toBool($v, bool $default): bool
    {
        if ($v === null) return $default;
        $s = strtoupper(trim((string) $v));
        if ($s === '') return $default;
        return in_array($s, ['1', 'Y', 'YES', 'TRUE', 'T'], true);
    }

    private function toIntArray(?array $v): ?array
    {
        if (empty($v)) return null;
        $out = [];
        foreach ($v as $x) {
            $n = (int) $x;
            if ($n > 0) $out[] = $n;
        }
        $out = array_values(array_unique($out));
        return empty($out) ? null : $out;
    }
}
