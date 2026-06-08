<?php

namespace App\Services\Erp;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductAttributeValueSyncService
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
     * @return array{
     *   rows:int,
     *   upserts:int,
     *   deleted_old:int,
     *   skipped_missing_product:int,
     *   skipped_by_date:int,
     *   skipped_missing_dictionary:int
     * }
     */
    public function sync(
        ?array $onlyDitte = null,
        ?array $onlySites = null,
        ?string $since = null,
        bool $dryRun = false,
        ?int $limit = null,
        bool $keepOld = false
    ): array {
        $this->initErpSession();

        $stats = [
            'rows' => 0,
            'upserts' => 0,
            'deleted_old' => 0,
            'skipped_missing_product' => 0,
            'skipped_by_date' => 0, // rimane ma NON lo usiamo più
            'skipped_missing_dictionary' => 0,
        ];

        $onlyDitte = $this->toIntArray($onlyDitte);
        $onlySites = $this->toIntArray($onlySites);

        try {
            // 1) slot -> attribute_code da ERP (NOMIATTRIBUTI_WEBT05)
            $slotMap = $this->loadSlotMapFromErp();
            if (empty($slotMap)) return $stats;

            // 2) attributes locali GLOBALI: code -> id
            $attrIdByCode = $this->loadLocalAttributeIdsGlobal();
            if (empty($attrIdByCode)) return $stats;

            // 3) query ERP prodotti con GRUATTRxx (FULL SCAN)
            $selectCols = [
                'DITTA_CG18',
                'CODART_MG66',
                'FLG_B2B_B2C_WEBT01',
            ];
            for ($i = 1; $i <= 40; $i++) $selectCols[] = $this->slotColumnName($i);

            $q = DB::connection('erp')
                ->table('dbo.ANAGRARTWEB_WEBT01')
                ->select($selectCols);

            if (!empty($onlyDitte)) $q->whereIn('DITTA_CG18', $onlyDitte);
            if (!empty($onlySites)) $q->whereIn('FLG_B2B_B2C_WEBT01', $onlySites);
            if ($limit !== null) $q->limit((int) $limit);

            $rows = $q->get();

            // cache: "attribute_id:value_code_norm" => attribute_value_id
            $attrValueIdCache = [];

            foreach ($rows as $r) {
                $stats['rows']++;

                $ditta = (int) ($r->DITTA_CG18 ?? 0);
                $site  = (int) ($r->FLG_B2B_B2C_WEBT01 ?? 0);
                $sku   = $this->trimOrNull($r->CODART_MG66 ?? null);
                if ($ditta <= 0 || $site <= 0 || !$sku) continue;

                $product = Product::query()
                    ->where('ditta_cg18', $ditta)
                    ->where('site_type', $site)
                    ->where('sku', $sku)
                    ->first();

                if (!$product) {
                    $stats['skipped_missing_product']++;
                    continue;
                }

                foreach ($slotMap as $slotId => $attrCodeRaw) {
                    $attrCode = $this->normCode($attrCodeRaw);
                    if ($attrCode === null) continue;

                    $col = $this->slotColumnName((int) $slotId);

                    $valueCode = $this->normCode($r->$col ?? null);
                    if ($valueCode === null) continue;

                    $attrId = $attrIdByCode[$attrCode] ?? null;
                    if (!$attrId) {
                        $stats['skipped_missing_dictionary']++;
                        continue;
                    }

                    $cacheKey = $attrId . ':' . $valueCode;

                    if (!isset($attrValueIdCache[$cacheKey])) {
                        $attrValueIdCache[$cacheKey] = (int) (AttributeValue::query()
                            ->where('attribute_id', $attrId)
                            ->whereRaw('RTRIM(LTRIM(UPPER(value_code))) = ?', [$valueCode])
                            ->value('id') ?? 0);
                    }

                    $attrValueId = (int) $attrValueIdCache[$cacheKey];
                    if ($attrValueId <= 0) {
                        $stats['skipped_missing_dictionary']++;
                        continue;
                    }

                    $valueKey = ProductAttributeValue::makeValueKey($attrValueId, null);

                    if ($dryRun) {
                        $stats['upserts']++;
                        continue;
                    }

                    if (!$keepOld) {
                        $stats['deleted_old'] += ProductAttributeValue::query()
                            ->where('product_id', $product->id)
                            ->where('attribute_id', $attrId)
                            ->where('value_key', '!=', $valueKey)
                            ->delete();
                    }

                    ProductAttributeValue::query()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'attribute_id' => $attrId,
                            'value_key' => $valueKey,
                        ],
                        [
                            'attribute_value_id' => $attrValueId,
                            'raw_value' => null,
                            // NON abbiamo una data affidabile -> null
                            'erp_lastchange' => null,
                        ]
                    );

                    $stats['upserts']++;
                }
            }

            return $stats;
        } catch (Throwable $e) {
            Log::error('ERP ProductAttributeValue Sync failed', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    private function normCode($v): ?string
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') return null;
        return strtoupper($s);
    }

    private function slotColumnName(int $slotId): string
    {
        $w = 10 + $slotId; // 1->11, 40->50
        return sprintf('GRUATTR%02d_W%02d', $slotId, $w);
    }

    /**
     * @return array<int,string> [slotId => attrCode]
     */
    private function loadSlotMapFromErp(): array
    {
        $rows = DB::connection('erp')
            ->table('dbo.NOMIATTRIBUTI_WEBT05')
            ->select(['IDTABELLA_WEBT05', 'CODGRUATTR_WEBT05'])
            ->whereNotNull('IDTABELLA_WEBT05')
            ->whereNotNull('CODGRUATTR_WEBT05')
            ->groupBy('IDTABELLA_WEBT05', 'CODGRUATTR_WEBT05')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $slot = (int) ($r->IDTABELLA_WEBT05 ?? 0);
            $code = $this->normCode($r->CODGRUATTR_WEBT05 ?? null);

            if ($slot <= 0 || $slot > 40 || !$code) continue;
            $out[$slot] = $code;
        }

        return $out;
    }

    /**
     * ✅ attributes globali: code unico globale
     * @return array<string,int> [CODE => attribute_id]
     */
    private function loadLocalAttributeIdsGlobal(): array
    {
        $rows = Attribute::query()->select(['id', 'code'])->get();

        $out = [];
        foreach ($rows as $a) {
            $c = $this->normCode($a->code ?? null);
            if ($c) $out[$c] = (int) $a->id;
        }
        return $out;
    }

    private function trimOrNull($v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
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