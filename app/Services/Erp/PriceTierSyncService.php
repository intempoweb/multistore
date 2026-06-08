<?php

namespace App\Services\Erp;

use App\Models\PriceTier;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PriceTierSyncService
{
    private const UPSERT_CHUNK_SIZE = 1000;
    private const ERP_IN_CHUNK_SIZE = 800;
    private const MAX_QTY_TO = '99999999.000';

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
     * Sync B2B tiers from ERP dbo.LISTINOCLI_RAGG.
     *
     * IMPORTANT:
     * - sync only local SIMPLE active products
     * - sync ERP listini for selected ditte (or explicit --listini)
     * - do NOT derive listini from local customers
     * - B2C/public prices are NOT handled here
     *
     * @return array{
     *   rows_read:int,
     *   upserts:int,
     *   ditte:int,
     *   listini:int,
     *   skus:int
     * }
     */
    public function sync(
        ?array $onlyDitte = null,
        ?array $onlyListini = null,
        ?string $onlySku = null,
        ?string $since = null,
        bool $dryRun = false
    ): array {
        $this->initErpSession();

        $stats = [
            'rows_read' => 0,
            'upserts'   => 0,
            'ditte'     => 0,
            'listini'   => 0,
            'skus'      => 0,
        ];

        $onlyDitte   = $this->toIntArray($onlyDitte);
        $onlyListini = $this->toIntArray($onlyListini);
        $onlySku     = $this->trimOrNull($onlySku);
        $sinceDate   = $this->normalizeSinceDate($since);

        $runStartedAt = Carbon::now();

        try {
            /*
            |--------------------------------------------------------------------------
            | 1) Local context = only local SIMPLE active products
            |--------------------------------------------------------------------------
            */
            $context = $this->deriveLocalSkuContext($onlyDitte, $onlySku);

            $ditte = $context['ditte'];
            $skusByDitta = $context['skusByDitta'];

            $stats['ditte'] = count($ditte);
            $stats['skus'] = array_sum(array_map('count', $skusByDitta));

            if (empty($ditte) || $stats['skus'] === 0) {
                return $stats;
            }

            /*
            |--------------------------------------------------------------------------
            | 2) ERP listini per ditta
            |--------------------------------------------------------------------------
            */
            $listiniByDitta = $this->deriveErpListiniByDitta($ditte, $onlyListini);
            $stats['listini'] = array_sum(array_map('count', $listiniByDitta));

            if ($stats['listini'] === 0) {
                return $stats;
            }

            /*
            |--------------------------------------------------------------------------
            | 3) Sync LISTINOCLI_RAGG
            |--------------------------------------------------------------------------
            */
            $payload = [];

            foreach ($ditte as $ditta) {
                $skus = $skusByDitta[$ditta] ?? [];
                $listini = $listiniByDitta[$ditta] ?? [];

                if (empty($skus) || empty($listini)) {
                    continue;
                }

                foreach (array_chunk($skus, self::ERP_IN_CHUNK_SIZE) as $skuChunk) {
                    $query = DB::connection('erp')
                        ->table('dbo.LISTINOCLI_RAGG')
                        ->select([
                            'DITTA_CG18_XX73',
                            'IDLISTINO_XX73',
                            'CODART_MG66_XX73',
                            'DAQTA_XX73',
                            'FINOAQTA_XX73',
                            'PREZZONETTO_XX73',
                            'SC1PER_XX73',
                            'SC2PER_XX73',
                            'SC3PER_XX73',
                            'SC4PER_XX73',
                            'SC5PER_XX73',
                            'SC6PER_XX73',
                            'DATAULTAGG_XX73',
                        ])
                        ->where('DITTA_CG18_XX73', $ditta)
                        ->whereIn('IDLISTINO_XX73', $listini)
                        ->whereIn('CODART_MG66_XX73', $skuChunk);

                    if ($sinceDate) {
                        $query->where(function ($sub) use ($sinceDate) {
                            $sub->whereNull('DATAULTAGG_XX73')
                                ->orWhere('DATAULTAGG_XX73', '>=', $sinceDate);
                        });
                    }

                    foreach ($query->cursor() as $row) {
                        $stats['rows_read']++;

                        $erpDitta = (int) ($row->DITTA_CG18_XX73 ?? 0);
                        $listino  = (int) ($row->IDLISTINO_XX73 ?? 0);
                        $sku      = $this->trimOrNull($row->CODART_MG66_XX73 ?? null);

                        if ($erpDitta <= 0 || $listino <= 0 || !$sku) {
                            continue;
                        }

                        $qtyFrom = $this->toDecimal3String($row->DAQTA_XX73 ?? null, '0.000');
                        $qtyTo   = $this->toDecimal3String($row->FINOAQTA_XX73 ?? null, null);

                        if ($qtyTo === null || (float) $qtyTo <= 0) {
                            $qtyTo = self::MAX_QTY_TO;
                        }

                        $priceNet = $this->toDecimal6String($row->PREZZONETTO_XX73 ?? null, null);
                        if ($priceNet === null) {
                            continue;
                        }

                        $stats['upserts']++;

                        if ($dryRun) {
                            continue;
                        }

                        $payload[] = [
                            'ditta_cg18'       => $erpDitta,
                            'listino_id'       => $listino,
                            'sku'              => $sku,
                            'qty_from'         => $qtyFrom,
                            'qty_to'           => $qtyTo,
                            'price_net'        => $priceNet,
                            'sc1'              => $this->toDecimal3String($row->SC1PER_XX73 ?? null, '0.000'),
                            'sc2'              => $this->toDecimal3String($row->SC2PER_XX73 ?? null, '0.000'),
                            'sc3'              => $this->toDecimal3String($row->SC3PER_XX73 ?? null, '0.000'),
                            'sc4'              => $this->toDecimal3String($row->SC4PER_XX73 ?? null, '0.000'),
                            'sc5'              => $this->toDecimal3String($row->SC5PER_XX73 ?? null, '0.000'),
                            'sc6'              => $this->toDecimal3String($row->SC6PER_XX73 ?? null, '0.000'),
                            'erp_lastchange'   => $this->toDate($row->DATAULTAGG_XX73 ?? null),
                            'erp_last_seen_at' => $runStartedAt,
                            'created_at'       => $runStartedAt,
                            'updated_at'       => $runStartedAt,
                        ];

                        if (count($payload) >= self::UPSERT_CHUNK_SIZE) {
                            $this->flushUpsert($payload);
                            $payload = [];
                        }
                    }
                }
            }

            if (!$dryRun && !empty($payload)) {
                $this->flushUpsert($payload);
            }

            return $stats;
        } catch (Throwable $e) {
            Log::error('ERP B2B Price Tier Sync failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Local SIMPLE active products only.
     *
     * @return array{
     *   ditte:array<int,int>,
     *   skusByDitta:array<int,array<int,string>>
     * }
     */
    private function deriveLocalSkuContext(?array $onlyDitte, ?string $onlySku): array
    {
        $ditte = [];
        $skusByDitta = [];

        $query = Product::query()
            ->select(['ditta_cg18', 'sku'])
            ->where('is_active', true)
            ->where('type', 'simple');

        if (!empty($onlyDitte)) {
            $query->whereIn('ditta_cg18', $onlyDitte);
        }

        if ($onlySku) {
            $query->where('sku', $onlySku);
        }

        foreach ($query->distinct()->cursor() as $product) {
            $ditta = (int) ($product->ditta_cg18 ?? 0);
            $sku   = $this->trimOrNull($product->sku ?? null);

            if ($ditta <= 0 || !$sku) {
                continue;
            }

            $ditte[$ditta] = $ditta;
            $skusByDitta[$ditta] ??= [];
            $skusByDitta[$ditta][] = $sku;
        }

        foreach ($skusByDitta as $ditta => $skus) {
            $skusByDitta[$ditta] = array_values(array_unique($skus));
        }

        $ditte = array_values($ditte);
        sort($ditte);

        return [
            'ditte' => $ditte,
            'skusByDitta' => $skusByDitta,
        ];
    }

    /**
     * Read all real ERP listini for selected ditte.
     *
     * If --listini is passed, keep only those listini that really exist in ERP.
     *
     * @param array<int,int> $ditte
     * @param array<int,int>|null $onlyListini
     * @return array<int,array<int,int>>
     */
    private function deriveErpListiniByDitta(array $ditte, ?array $onlyListini): array
    {
        $out = [];

        if (empty($ditte)) {
            return $out;
        }

        $query = DB::connection('erp')
            ->table('dbo.LISTINOCLI_RAGG')
            ->select(['DITTA_CG18_XX73', 'IDLISTINO_XX73'])
            ->whereIn('DITTA_CG18_XX73', $ditte)
            ->distinct();

        if (!empty($onlyListini)) {
            $query->whereIn('IDLISTINO_XX73', $onlyListini);
        }

        foreach ($query->cursor() as $row) {
            $ditta = (int) ($row->DITTA_CG18_XX73 ?? 0);
            $id    = (int) ($row->IDLISTINO_XX73 ?? 0);

            if ($ditta <= 0 || $id <= 0) {
                continue;
            }

            $out[$ditta] ??= [];
            $out[$ditta][] = $id;
        }

        foreach ($out as $ditta => $listini) {
            $out[$ditta] = array_values(array_unique($listini));
            sort($out[$ditta]);
        }

        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $payload
     */
    private function flushUpsert(array $payload): void
    {
        PriceTier::query()->upsert(
            $payload,
            ['ditta_cg18', 'listino_id', 'sku', 'qty_from'],
            [
                'qty_to',
                'price_net',
                'sc1',
                'sc2',
                'sc3',
                'sc4',
                'sc5',
                'sc6',
                'erp_lastchange',
                'erp_last_seen_at',
                'updated_at',
            ]
        );
    }

    private function normalizeSinceDate(?string $since): ?string
    {
        if (!$since) {
            return null;
        }

        $since = trim($since);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $since) ? $since : null;
    }

    private function trimOrNull($value): ?string
    {
        $s = trim((string) ($value ?? ''));
        return $s === '' ? null : $s;
    }

    private function toDate($value): ?string
    {
        $s = trim((string) ($value ?? ''));

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

    private function toDecimal3String($value, ?string $default): ?string
    {
        if ($value === null) {
            return $default;
        }

        $s = trim((string) $value);
        if ($s === '') {
            return $default;
        }

        $s = str_replace(',', '.', $s);
        if (!is_numeric($s)) {
            return $default;
        }

        return number_format((float) $s, 3, '.', '');
    }

    private function toDecimal6String($value, ?string $default): ?string
    {
        if ($value === null) {
            return $default;
        }

        $s = trim((string) $value);
        if ($s === '') {
            return $default;
        }

        $s = str_replace(',', '.', $s);
        if (!is_numeric($s)) {
            return $default;
        }

        return number_format((float) $s, 6, '.', '');
    }

    /**
     * @param array<int,mixed>|null $values
     * @return array<int,int>|null
     */
    private function toIntArray(?array $values): ?array
    {
        if (empty($values)) {
            return null;
        }

        $out = [];

        foreach ($values as $value) {
            $n = (int) $value;
            if ($n > 0) {
                $out[] = $n;
            }
        }

        $out = array_values(array_unique($out));

        return empty($out) ? null : $out;
    }
}