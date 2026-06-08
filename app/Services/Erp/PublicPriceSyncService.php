<?php

namespace App\Services\Erp;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublicPriceSyncService
{
    private const ERP_CHUNK_SIZE = 800;

    /**
     * Mapping listino pubblico B2C per ditta.
     * ditta 1 => listino 31
     * ditta 3 => nessun B2C
     */
    private const PUBLIC_LISTINO_BY_DITTA = [
        1 => 31,
    ];

    private static bool $erpSessionInitialized = false;

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

    /**
     * Sync prezzi pubblici da ERP LISTARTIC_TOT -> products.
     *
     * Il listino pubblico vale come prezzo pubblico/default per TUTTI i prodotti
     * locali attivi della ditta, sia B2C che B2B.
     *
     * products.public_price            = PREZZONETTO (2 decimali)
     * products.public_price_gross      = PREZZO_LI10
     * products.public_price_listino_id = listino pubblico ERP (es. 31)
     *
     * @return array{rows_read:int, updates:int, ditte:int, skus:int}
     */
    public function sync(
        ?array $onlyDitte = null,
        ?string $onlySku = null,
        bool $dryRun = false
    ): array {
        $this->initErpSession();

        $stats = [
            'rows_read' => 0,
            'updates'   => 0,
            'ditte'     => 0,
            'skus'      => 0,
        ];

        $onlyDitte = $this->toIntArray($onlyDitte);
        $onlySku   = $this->trimOrNull($onlySku);

        $eligibleDitte = array_keys(self::PUBLIC_LISTINO_BY_DITTA);

        if (!empty($onlyDitte)) {
            $eligibleDitte = array_values(array_intersect($eligibleDitte, $onlyDitte));
        }

        if (empty($eligibleDitte)) {
            return $stats;
        }

        $runStartedAt = Carbon::now();

        try {
            // Scope prodotti locali attivi realmente presenti, senza limitazione B2C.
            $skusByDitta = $this->deriveLocalProductScope($eligibleDitte, $onlySku);

            $stats['ditte'] = count($skusByDitta);
            $stats['skus']  = array_sum(array_map('count', $skusByDitta));

            if ($stats['skus'] === 0) {
                return $stats;
            }

            foreach ($skusByDitta as $ditta => $skus) {
                $listinoId = self::PUBLIC_LISTINO_BY_DITTA[$ditta] ?? null;

                if (!$listinoId || empty($skus)) {
                    continue;
                }

                foreach (array_chunk($skus, self::ERP_CHUNK_SIZE) as $skuChunk) {
                    $q = DB::connection('erp')
                        ->table('dbo.LISTARTIC_TOT as l')
                        ->select([
                            'l.DITTA_CG18',
                            'l.NUMLIST_LI10',
                            'l.CODART_MG66',
                            'l.PREZZO_LI10',
                            'l.PREZZONETTO',
                            'l.LASTCHANGE',
                        ])
                        ->where('l.DITTA_CG18', $ditta)
                        ->where('l.NUMLIST_LI10', $listinoId)
                        ->whereIn('l.CODART_MG66', $skuChunk)
                        ->orderBy('l.CODART_MG66');

                    foreach ($q->cursor() as $r) {
                        $stats['rows_read']++;

                        $sku = $this->trimOrNull($r->CODART_MG66 ?? null);
                        if (!$sku) {
                            continue;
                        }

                        $publicPriceNet   = $this->toDecimal($r->PREZZONETTO ?? null, null);
                        $publicPriceGross = $this->toDecimal($r->PREZZO_LI10 ?? null, null);
                        $lastChange       = $this->toDate($r->LASTCHANGE ?? null);

                        if ($publicPriceNet === null) {
                            continue;
                        }

                        if ($dryRun) {
                            $matchedRows = Product::query()
                                ->where('ditta_cg18', $ditta)
                                ->where('sku', $sku)
                                ->where('is_active', true)
                                ->count();

                            $stats['updates'] += $matchedRows;
                            continue;
                        }

                        $affected = Product::query()
                            ->where('ditta_cg18', $ditta)
                            ->where('sku', $sku)
                            ->where('is_active', true)
                            ->update([
                                'public_price'              => round($publicPriceNet, 2),
                                'public_price_listino_id'   => $listinoId,
                                'public_price_gross'        => $publicPriceGross,
                                'public_price_lastchange'   => $lastChange,
                                'public_price_last_seen_at' => $runStartedAt,
                                'updated_at'                => $runStartedAt,
                            ]);

                        $stats['updates'] += $affected;
                    }
                }
            }

            return $stats;
        } catch (Throwable $e) {
            Log::error('ERP Public Price Sync failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Scope prodotti locali attivi realmente esistenti per ditta,
     * senza limitazione ai soli site_type B2C.
     *
     * @param array<int,int> $ditte
     * @return array<int,array<int,string>>
     */
    private function deriveLocalProductScope(array $ditte, ?string $onlySku): array
    {
        $out = [];

        if (empty($ditte)) {
            return $out;
        }

        $query = Product::query()
            ->select(['ditta_cg18', 'sku'])
            ->whereIn('ditta_cg18', $ditte)
            ->where('is_active', true)
            ->distinct();

        if ($onlySku) {
            $query->where('sku', $onlySku);
        }

        foreach ($query->cursor() as $product) {
            $ditta = (int) ($product->ditta_cg18 ?? 0);
            $sku   = $this->trimOrNull($product->sku ?? null);

            if ($ditta <= 0 || !$sku) {
                continue;
            }

            $out[$ditta] ??= [];
            $out[$ditta][] = $sku;
        }

        foreach ($out as $ditta => $skus) {
            $out[$ditta] = array_values(array_unique($skus));
            sort($out[$ditta]);
        }

        ksort($out);

        return $out;
    }

    private function trimOrNull($v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }

    private function toDecimal($v, ?float $default): ?float
    {
        if ($v === null) {
            return $default;
        }

        $s = trim((string) $v);
        if ($s === '') {
            return $default;
        }

        $s = str_replace(',', '.', $s);

        return is_numeric($s) ? (float) $s : $default;
    }

    private function toDate($v): ?string
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return $s;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
            return substr($s, 0, 10);
        }

        return null;
    }

    private function toIntArray(?array $v): ?array
    {
        if (empty($v)) {
            return null;
        }

        $out = [];

        foreach ($v as $x) {
            $n = (int) $x;
            if ($n > 0) {
                $out[] = $n;
            }
        }

        $out = array_values(array_unique($out));

        return empty($out) ? null : $out;
    }
}