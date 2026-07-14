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
            $q = DB::connection('erp')
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
                ->where('DATAULTVAR_MG70', '>=', $sinceDate);

            if ($limit !== null) $q->limit((int) $limit);

            foreach ($q->get() as $r) {
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

            return $stats;
        } catch (Throwable $e) {
            Log::error('ERP Stock Sync failed', ['message' => $e->getMessage()]);
            throw $e;
        }
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
