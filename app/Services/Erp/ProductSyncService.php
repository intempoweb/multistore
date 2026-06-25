<?php

namespace App\Services\Erp;

use App\Models\ConfigurableProduct;
use App\Models\Product;
use App\Models\ProductTranslation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductSyncService
{
    private const LOCALE_MAP = [
        'ITA' => 'it',
        'IT'  => 'it',
        'GB'  => 'en',
        'EN'  => 'en',
        'UK'  => 'en',
        'ESP' => 'es',
        'ES'  => 'es',
        'FRA' => 'fr',
        'FR'  => 'fr',
        'DEU' => 'de',
        'DE'  => 'de',
    ];

    private const ERP_WHERE_IN_CHUNK_SIZE = 1000;

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
     * @return array{
     *   simple_rows:int,
     *   parent_rows:int,
     *   simple_upserts:int,
     *   configurable_upserts:int,
     *   configurable_meta_upserts:int,
     *   product_translations_upserts:int,
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
            'simple_rows' => 0,
            'parent_rows' => 0,
            'simple_upserts' => 0,
            'configurable_upserts' => 0,
            'configurable_meta_upserts' => 0,
            'product_translations_upserts' => 0,
            'skipped_by_date' => 0,
        ];

        $onlyDitte = $this->toIntArray($onlyDitte);
        $onlySites = $this->toIntArray($onlySites);
        $sinceDate = $this->normalizeSinceDate($since);

        try {
            $this->syncSimple($stats, $onlyDitte, $onlySites, $sinceDate, $dryRun, $limit);
            $this->syncParents($stats, $onlyDitte, $onlySites, $sinceDate, $dryRun, $limit);

            return $stats;
        } catch (Throwable $e) {
            Log::error('ERP Product Sync failed', [
                'message' => $e->getMessage(),
                'ditte' => $onlyDitte,
                'sites' => $onlySites,
                'since' => $sinceDate,
                'limit' => $limit,
            ]);

            throw $e;
        }
    }

    /**
     * SIMPLE
     *
     * Regola:
     * - importiamo gli SKU per cui MAX(ARTDESC_TOT.LASTCHANGE_WEBT87) >= since
     * - oppure per cui ANAGRARTWEB_WEBT01.DATAULTIMOAGG_WEBT01 >= since
     * - poi carichiamo i dati tecnici da WEBT01 e le traduzioni da ARTDESC_TOT in query separate,
     *   per evitare timeout dovuti a join/subquery troppo pesanti.
     */
    private function syncSimple(
        array &$stats,
        ?array $onlyDitte,
        ?array $onlySites,
        string $sinceDate,
        bool $dryRun,
        ?int $limit
    ): void {
        $candidateKeys = $this->fetchSimpleCandidateKeys($onlyDitte, $onlySites, $sinceDate, $limit);

        if ($candidateKeys->isEmpty()) {
            return;
        }

        $groupedKeys = $candidateKeys->groupBy(
            fn ($row) => ((int) $row->DITTA_CG18) . ':' . ((int) $row->FLG_B2B_B2C)
        );

        $productCache = [];
        $productDone = [];

        foreach ($groupedKeys as $groupKey => $keyRows) {
            [$ditta, $site] = array_map('intval', explode(':', (string) $groupKey, 2));

            $skus = collect($keyRows)
                ->pluck('CODART_MG66')
                ->map(fn ($value) => $this->trimOrNull($value))
                ->filter()
                ->values();

            if ($skus->isEmpty()) {
                continue;
            }

            $webRows = $this->fetchSimpleWebRows($ditta, $site, $skus)->keyBy('CODART_MG66');
            $descRows = $this->fetchSimpleDescriptionRows($ditta, $site, $skus)->groupBy('CODART_MG66');

            foreach ($keyRows as $keyRow) {
                $sku = $this->trimOrNull($keyRow->CODART_MG66 ?? null);

                if (!$sku) {
                    continue;
                }

                $webRow = $webRows->get($sku);

                if (!$webRow) {
                    continue;
                }

                $stats['simple_rows']++;

                $cacheKey = $ditta . ':' . $site . ':' . $sku;

                if (!array_key_exists($cacheKey, $productCache)) {
                    $productCache[$cacheKey] = Product::query()
                        ->where('ditta_cg18', $ditta)
                        ->where('site_type', $site)
                        ->where('sku', $sku)
                        ->first();
                }

                /** @var Product|null $existing */
                $existing = $productCache[$cacheKey];

                $erpIsActive = $this->toBool($webRow->FLGATTIVO_WEBT01 ?? null, true);
                $noBackorder = $this->toBool($webRow->FLGNOORDINZERO_WEBT01 ?? null, false);
                $flgModUltime = $this->toBool($webRow->FLGMODULTIME_WEBT01 ?? null, false);
                $flgInTempo = $this->toBool($webRow->FLGINTEMPO_WEBT01 ?? null, false);
                $flgStaging = $this->toBool($webRow->FLGSTAGING_WEBT01 ?? null, false);
                $dataUltAgg = $this->toDate($webRow->DATAULTIMOAGG_WEBT01 ?? null);
                $minOrderQty = $this->toMinQty($webRow->CONFMINACQ_WEBT01 ?? null, 1);

                if (!$erpIsActive && !$existing) {
                    continue;
                }

                if (!$dryRun && !$erpIsActive && $existing) {
                    Product::query()
                        ->where('ditta_cg18', $ditta)
                        ->where('site_type', $site)
                        ->where('sku', $sku)
                        ->update(['is_active' => false]);

                    $stats['simple_upserts']++;
                    continue;
                }

                if (!isset($productDone[$cacheKey])) {
                    $productDone[$cacheKey] = true;

                    if ($dryRun) {
                        $stats['simple_upserts']++;
                    } else {
                        if ($erpIsActive) {
                            $data = [
                                'parent_code' => $this->trimOrNull($webRow->RADICEARTIC_WEBT01 ?? null),
                                'parent_site_type' => null,
                                'type' => 'simple',
                                'is_active' => true,
                                'no_backorder' => $noBackorder,
                                'flgmodultime_webt01' => $flgModUltime,
                                'flgintempo_webt01' => $flgInTempo,
                                'flgstaging_webt01' => $flgStaging,
                                'codgrupfis_mg61' => $this->trimOrNull($webRow->CODGRUPFIS_MG61 ?? null),
                                'barcode' => $this->trimOrNull($webRow->CODBARCODE_MG65 ?? null),
                                'unit' => $this->trimOrNull($webRow->UNITAMISURA_WEBT01 ?? null),
                                'fam_99' => $this->trimOrNull($webRow->FAM_99 ?? null),
                                'sfam_99' => $this->trimOrNull($webRow->SFAM_99 ?? null),
                                'gruppo_99' => $this->trimOrNull($webRow->GRUPPO_99 ?? null),
                                'sgruppo_99' => $this->trimOrNull($webRow->SGRUPPO_99 ?? null),
                                'marca_mg64' => $this->trimOrNull($webRow->MARCA_MG64 ?? null),
                                'gruattr01_w11' => $this->trimOrNull($webRow->GRUATTR01_W11 ?? null),
                                'gruattr02_w12' => $this->trimOrNull($webRow->GRUATTR02_W12 ?? null),
                                'gruattr03_w13' => $this->trimOrNull($webRow->GRUATTR03_W13 ?? null),
                                'gruattr04_w14' => $this->trimOrNull($webRow->GRUATTR04_W14 ?? null),
                                'gruattr05_w15' => $this->trimOrNull($webRow->GRUATTR05_W15 ?? null),
                                'gruattr06_w16' => $this->trimOrNull($webRow->GRUATTR06_W16 ?? null),
                                'gruattr07_w17' => $this->trimOrNull($webRow->GRUATTR07_W17 ?? null),
                                'gruattr08_w18' => $this->trimOrNull($webRow->GRUATTR08_W18 ?? null),
                                'gruattr09_w19' => $this->trimOrNull($webRow->GRUATTR09_W19 ?? null),
                                'gruattr10_w20' => $this->trimOrNull($webRow->GRUATTR10_W20 ?? null),
                                'gruattr11_w21' => $this->trimOrNull($webRow->GRUATTR11_W21 ?? null),
                                'gruattr12_w22' => $this->trimOrNull($webRow->GRUATTR12_W22 ?? null),
                                'gruattr13_w23' => $this->trimOrNull($webRow->GRUATTR13_W23 ?? null),
                                'gruattr14_w24' => $this->trimOrNull($webRow->GRUATTR14_W24 ?? null),
                                'gruattr15_w25' => $this->trimOrNull($webRow->GRUATTR15_W25 ?? null),
                                'gruattr16_w26' => $this->trimOrNull($webRow->GRUATTR16_W26 ?? null),
                                'gruattr17_w27' => $this->trimOrNull($webRow->GRUATTR17_W27 ?? null),
                                'gruattr18_w28' => $this->trimOrNull($webRow->GRUATTR18_W28 ?? null),
                                'gruattr19_w29' => $this->trimOrNull($webRow->GRUATTR19_W29 ?? null),
                                'gruattr20_w30' => $this->trimOrNull($webRow->GRUATTR20_W30 ?? null),
                                'gruattr21_w31' => $this->trimOrNull($webRow->GRUATTR21_W31 ?? null),
                                'gruattr22_w32' => $this->trimOrNull($webRow->GRUATTR22_W32 ?? null),
                                'gruattr23_w33' => $this->trimOrNull($webRow->GRUATTR23_W33 ?? null),
                                'gruattr24_w34' => $this->trimOrNull($webRow->GRUATTR24_W34 ?? null),
                                'gruattr25_w35' => $this->trimOrNull($webRow->GRUATTR25_W35 ?? null),
                                'gruattr26_w36' => $this->trimOrNull($webRow->GRUATTR26_W36 ?? null),
                                'gruattr27_w37' => $this->trimOrNull($webRow->GRUATTR27_W37 ?? null),
                                'gruattr28_w38' => $this->trimOrNull($webRow->GRUATTR28_W38 ?? null),
                                'gruattr29_w39' => $this->trimOrNull($webRow->GRUATTR29_W39 ?? null),
                                'gruattr30_w40' => $this->trimOrNull($webRow->GRUATTR30_W40 ?? null),
                                'gruattr31_w41' => $this->trimOrNull($webRow->GRUATTR31_W41 ?? null),
                                'gruattr32_w42' => $this->trimOrNull($webRow->GRUATTR32_W42 ?? null),
                                'gruattr33_w43' => $this->trimOrNull($webRow->GRUATTR33_W43 ?? null),
                                'gruattr34_w44' => $this->trimOrNull($webRow->GRUATTR34_W44 ?? null),
                                'gruattr35_w45' => $this->trimOrNull($webRow->GRUATTR35_W45 ?? null),
                                'gruattr36_w46' => $this->trimOrNull($webRow->GRUATTR36_W46 ?? null),
                                'gruattr37_w47' => $this->trimOrNull($webRow->GRUATTR37_W47 ?? null),
                                'gruattr38_w48' => $this->trimOrNull($webRow->GRUATTR38_W48 ?? null),
                                'gruattr39_w49' => $this->trimOrNull($webRow->GRUATTR39_W49 ?? null),
                                'gruattr40_w50' => $this->trimOrNull($webRow->GRUATTR40_W50 ?? null),
                                'opzionefam_webt01' => $this->toNullableInt($webRow->OPZIONEFAM_WEBT01 ?? null),
                                'opzioneraggr_webt01' => $this->toNullableInt($webRow->OPZIONERAGGR_WEBT01 ?? null),
                                'raggrupcat1_w51' => $this->trimOrNull($webRow->RAGGRUPCAT1_W51 ?? null),
                                'raggrupcat2_w52' => $this->trimOrNull($webRow->RAGGRUPCAT2_W52 ?? null),
                                'raggrupcat3_w53' => $this->trimOrNull($webRow->RAGGRUPCAT3_W53 ?? null),
                                'raggrupcat4_w54' => $this->trimOrNull($webRow->RAGGRUPCAT4_W54 ?? null),
                                'codlinea_w55' => $this->trimOrNull($webRow->CODLINEA_W55 ?? null),
                                'codedizione_w56' => $this->trimOrNull($webRow->CODEDIZIONE_W56 ?? null),
                                'codcollezione_w57' => $this->trimOrNull($webRow->CODCOLLEZIONE_W57 ?? null),
                                'codbrand_w58' => $this->trimOrNull($webRow->CODBRAND_W58 ?? null),
                                'codfantasie_w59' => $this->trimOrNull($webRow->CODFANTASIE_W59 ?? null),
                                'codassociazioneart_w60' => $this->trimOrNull($webRow->CODASSOCIAZIONEART_W60 ?? null),
                                'raggrupassoc1_w61' => $this->trimOrNull($webRow->RAGGRUPASSOC1_W61 ?? null),
                                'raggrupassoc2_w62' => $this->trimOrNull($webRow->RAGGRUPASSOC2_W62 ?? null),
                                'raggrupassoc3_w63' => $this->trimOrNull($webRow->RAGGRUPASSOC3_W63 ?? null),
                                'raggrupassoc4_w64' => $this->trimOrNull($webRow->RAGGRUPASSOC4_W64 ?? null),
                                'pagcatalogo_webt01' => $this->trimOrNull($webRow->PAGCATALOGO_WEBT01 ?? null),
                                'flgofferta_webt01' => $this->toBool($webRow->FLGOFFERTA_WEBT01 ?? null, false),
                                'datainizofferta_webt01' => $this->toDate($webRow->DATAINIZOFFERTA_WEBT01 ?? null),
                                'datafineofferta_webt01' => $this->toDate($webRow->DATAFINEOFFERTA_WEBT01 ?? null),
                                'flgpromo_webt01' => $this->toBool($webRow->FLGPROMO_WEBT01 ?? null, false),
                                'datainizpromo_webt01' => $this->toDate($webRow->DATAINIZPROMO_WEBT01 ?? null),
                                'datafinepromo_webt01' => $this->toDate($webRow->DATAFINEPROMO_WEBT01 ?? null),
                                'flgnovita_webt01' => $this->toBool($webRow->FLGNOVITA_WEBT01 ?? null, false),
                                'datainiznovita_webt01' => $this->toDate($webRow->DATAINIZNOVITA_WEBT01 ?? null),
                                'datafinenovita_webt01' => $this->toDate($webRow->DATAFINENOVITA_WEBT01 ?? null),
                                'flgcampagna_webt01' => $this->toBool($webRow->FLGCAMPAGNA_WEBT01 ?? null, false),
                                'datainizcampagna_webt01' => $this->toDate($webRow->DATAINIZCAMPAGNA_WEBT01 ?? null),
                                'datafinecampagna_webt01' => $this->toDate($webRow->DATAFINECAMPAGNA_WEBT01 ?? null),
                                'qtamaxvisibile_webt01' => $this->toDecimalOrNull($webRow->QTAMAXVISIBILE_WEBT01 ?? null, 3),
                                'flgsemaforo_webt01' => $this->toBool($webRow->FLGSEMAFORO_WEBT01 ?? null, false),
                                'qtasemafverde_webt01' => $this->toDecimalOrNull($webRow->QTASEMAFVERDE_WEBT01 ?? null, 3),
                                'qtasemafarancio_webt01' => $this->toDecimalOrNull($webRow->QTASEMAFARANCIO_WEBT01 ?? null, 3),
                                'qtasemafrosso_webt01' => $this->toDecimalOrNull($webRow->QTASEMAFROSSO_WEBT01 ?? null, 3),
                                'notedepprel_mg69' => $this->trimOrNull($webRow->NOTEDEPPREL_MG69 ?? null),
                                'codconfez_mg96' => $this->trimOrNull($webRow->CODCONFEZ_MG96 ?? null),
                                'pzconf_mg68' => $this->toDecimalOrNull($webRow->PZCONF_MG68 ?? null, 3),
                                'pesocalc' => $this->toDecimalOrNull($webRow->PESOCALC ?? null, 4),
                                'umpeso_mg68' => $this->trimOrNull($webRow->UMPESO_MG68 ?? null),
                                'peson_mg68' => $this->toDecimalOrNull($webRow->PESON_MG68 ?? null, 4),
                                'pesol_mg68' => $this->toDecimalOrNull($webRow->PESOL_MG68 ?? null, 4),
                                'massanetta_mg98' => $this->toDecimalOrNull($webRow->MASSANETTA_MG98 ?? null, 6),
                                'largh_mg68' => $this->toDecimalOrNull($webRow->LARGH_MG68 ?? null, 4),
                                'altez_mg68' => $this->toDecimalOrNull($webRow->ALTEZ_MG68 ?? null, 4),
                                'prof_mg68' => $this->toDecimalOrNull($webRow->PROF_MG68 ?? null, 4),
                                'min_order_qty' => $minOrderQty,
                                'erp_dataultimoagg' => $dataUltAgg,
                            ];

                            $affected = Product::query()
                                ->where('ditta_cg18', $ditta)
                                ->where('site_type', $site)
                                ->where('sku', $sku)
                                ->update($data);

                            if ($affected === 0) {
                                $product = new Product();
                                $product->ditta_cg18 = $ditta;
                                $product->site_type = $site;
                                $product->sku = $sku;

                                foreach ($data as $field => $value) {
                                    $product->{$field} = $value;
                                }

                                $product->save();
                                $productCache[$cacheKey] = $product;
                            } else {
                                $productCache[$cacheKey] = Product::query()
                                    ->where('ditta_cg18', $ditta)
                                    ->where('site_type', $site)
                                    ->where('sku', $sku)
                                    ->first();
                            }

                            $stats['simple_upserts']++;
                        }
                    }
                }

                foreach (($descRows->get($sku) ?? collect()) as $descRow) {
                    $lingua = strtoupper(trim((string) ($descRow->LINGUA_MG52 ?? '')));
                    $locale = self::LOCALE_MAP[$lingua] ?? null;

                    $name = $this->trimOrNull($descRow->DESCART_MG87 ?? null);
                    $descLong = $this->trimOrNull($descRow->DESCARTEST_MG87 ?? null);
                    $descShort = $this->trimOrNull($descRow->NOTEART_MG87 ?? null);
                    $description = $descLong ?: $descShort;

                    if ($erpIsActive && $locale && ($name || $description)) {
                        if ($dryRun) {
                            $stats['product_translations_upserts']++;
                        } else {
                            $product = $productCache[$cacheKey] ?? null;

                            if ($product && $product->exists) {
                                $seo = app(\App\Services\Storefront\Seo\ProductSeoGenerator::class)
                                    ->generate($product, $locale, $name, $description);

                                ProductTranslation::updateOrCreate(
                                    ['product_id' => $product->id, 'locale' => $locale],
                                    [
                                        'name' => $name,
                                        'description' => $description,
                                        'short_description' => $descShort,
                                        'seo_title' => $seo['seo_title'],
                                        'seo_description' => $seo['seo_description'],
                                    ]
                                );

                                $stats['product_translations_upserts']++;
                            }
                        }
                    }
                }
            }
        }
    }

    private function fetchSimpleCandidateKeys(
        ?array $onlyDitte,
        ?array $onlySites,
        string $sinceDate,
        ?int $limit
    ): Collection {
        $descriptionKeys = DB::connection('erp')
            ->table('dbo.ARTDESC_TOT as d')
            ->selectRaw('
                d.DITTA_CG18,
                d.FLG_B2B_B2C,
                d.CODART_MG66
            ')
            ->whereNotNull('d.LASTCHANGE_WEBT87')
            ->when(!empty($onlyDitte), fn ($q) => $q->whereIn('d.DITTA_CG18', $onlyDitte))
            ->when(!empty($onlySites), fn ($q) => $q->whereIn('d.FLG_B2B_B2C', $onlySites))
            ->groupBy('d.DITTA_CG18', 'd.FLG_B2B_B2C', 'd.CODART_MG66')
            ->havingRaw('MAX(d.LASTCHANGE_WEBT87) >= ?', [$sinceDate])
            ->get();

        $webKeys = DB::connection('erp')
            ->table('dbo.ANAGRARTWEB_WEBT01 as w')
            ->selectRaw('
                w.DITTA_CG18,
                w.FLG_B2B_B2C_WEBT01 AS FLG_B2B_B2C,
                w.CODART_MG66
            ')
            ->whereNotNull('w.DATAULTIMOAGG_WEBT01')
            ->where('w.DATAULTIMOAGG_WEBT01', '>=', $sinceDate)
            ->when(!empty($onlyDitte), fn ($q) => $q->whereIn('w.DITTA_CG18', $onlyDitte))
            ->when(!empty($onlySites), fn ($q) => $q->whereIn('w.FLG_B2B_B2C_WEBT01', $onlySites))
            ->groupBy('w.DITTA_CG18', 'w.FLG_B2B_B2C_WEBT01', 'w.CODART_MG66')
            ->get();

        $keys = $descriptionKeys
            ->merge($webKeys)
            ->filter(fn ($row) => (int) ($row->DITTA_CG18 ?? 0) > 0
                && (int) ($row->FLG_B2B_B2C ?? 0) > 0
                && $this->trimOrNull($row->CODART_MG66 ?? null) !== null)
            ->unique(fn ($row) => ((int) $row->DITTA_CG18) . ':' . ((int) $row->FLG_B2B_B2C) . ':' . $this->trimOrNull($row->CODART_MG66 ?? null))
            ->sortBy(fn ($row) => sprintf(
                '%010d:%010d:%s',
                (int) $row->DITTA_CG18,
                (int) $row->FLG_B2B_B2C,
                (string) $row->CODART_MG66
            ))
            ->values();

        return $limit !== null
            ? $keys->take((int) $limit)->values()
            : $keys;
    }

    /**
     * PADRI
     *
     * Restiamo su DATAULTIMOAGG_WEBT00 come discriminante.
     */
    private function syncParents(
        array &$stats,
        ?array $onlyDitte,
        ?array $onlySites,
        string $sinceDate,
        bool $dryRun,
        ?int $limit
    ): void {
        $query = DB::connection('erp')
            ->table('dbo.ANAGRARTPADRE_TOT')
            ->selectRaw('
                DITTA_CG18,
                CAST(CODARTPADRE_WEBT00 AS NVARCHAR(255)) AS CODARTPADRE_WEBT00,
                FLG_B2B_B2C_WEBT00,
                CAST(LINGUA_WEBT00 AS NVARCHAR(20)) AS LINGUA_WEBT00,
                CAST(TITOLOARTPADRE_WEBT00 AS NVARCHAR(MAX)) AS TITOLOARTPADRE_WEBT00,
                CAST(DESCRARTPADRE_WEBT00 AS NVARCHAR(MAX)) AS DESCRARTPADRE_WEBT00,
                CAST(FOTOARTPADRE_WEBT00 AS NVARCHAR(255)) AS FOTOARTPADRE_WEBT00,
                DATAULTIMOAGG_WEBT00
            ')
            ->where('DATAULTIMOAGG_WEBT00', '>=', $sinceDate)
            ->orderBy('DITTA_CG18')
            ->orderBy('FLG_B2B_B2C_WEBT00')
            ->orderBy('CODARTPADRE_WEBT00');

        if (!empty($onlyDitte)) {
            $query->whereIn('DITTA_CG18', $onlyDitte);
        }

        if (!empty($onlySites)) {
            $query->whereIn('FLG_B2B_B2C_WEBT00', $onlySites);
        }

        if ($limit !== null) {
            $query->limit((int) $limit);
        }

        $rows = $query->get();

        foreach ($rows as $row) {
            $stats['parent_rows']++;

            $ditta = (int) ($row->DITTA_CG18 ?? 0);
            $site = (int) ($row->FLG_B2B_B2C_WEBT00 ?? 0);
            $code = $this->trimOrNull($row->CODARTPADRE_WEBT00 ?? null);

            if ($ditta <= 0 || $site <= 0 || !$code) {
                continue;
            }

            $dataUltimoAgg = $this->toDate($row->DATAULTIMOAGG_WEBT00 ?? null);

            if (!$dataUltimoAgg || $dataUltimoAgg < $sinceDate) {
                $stats['skipped_by_date']++;
                continue;
            }

            $lingua = strtoupper(trim((string) ($row->LINGUA_WEBT00 ?? '')));
            $locale = self::LOCALE_MAP[$lingua] ?? null;

            $title = $this->trimOrNull($row->TITOLOARTPADRE_WEBT00 ?? null);
            $description = $this->trimOrNull($row->DESCRARTPADRE_WEBT00 ?? null);
            $photoFile = $this->trimOrNull($row->FOTOARTPADRE_WEBT00 ?? null);

            if ($dryRun) {
                $stats['configurable_upserts']++;
                $stats['configurable_meta_upserts']++;

                if ($locale && ($title || $description)) {
                    $stats['product_translations_upserts']++;
                }

                continue;
            }

            $product = Product::updateOrCreate(
                [
                    'ditta_cg18' => $ditta,
                    'site_type' => $site,
                    'sku' => $code,
                ],
                [
                    'parent_code' => null,
                    'parent_site_type' => null,
                    'type' => 'configurable',
                    'is_active' => true,
                    'no_backorder' => false,
                    'stock_qty' => 0,
                    'codgrupfis_mg61' => null,
                    'barcode' => null,
                    'unit' => null,
                    'min_order_qty' => 1,
                    'erp_dataultimoagg' => $dataUltimoAgg,
                    'erp_lastchange' => null,
                ]
            );

            $stats['configurable_upserts']++;

            ConfigurableProduct::updateOrCreate(
                [
                    'ditta_cg18' => $ditta,
                    'site_type' => $site,
                    'parent_code' => $code,
                ],
                [
                    'photo' => $photoFile ?: null,
                    'dataultimoagg' => $dataUltimoAgg,
                    'erp_lastchange' => null,
                ]
            );

            $stats['configurable_meta_upserts']++;

            if ($locale && ($title || $description)) {
                $seo = app(\App\Services\Storefront\Seo\ProductSeoGenerator::class)
                    ->generate($product, $locale, $title, $description);

                ProductTranslation::updateOrCreate(
                    ['product_id' => $product->id, 'locale' => $locale],
                    [
                        'name' => $title,
                        'description' => $description,
                        'seo_title' => $seo['seo_title'],
                        'seo_description' => $seo['seo_description'],
                    ]
                );

                $stats['product_translations_upserts']++;
            }
        }
    }

    private function fetchSimpleWebRows(int $ditta, int $site, Collection $skus): Collection
    {
        $rows = collect();

        foreach ($skus->filter()->unique()->values()->chunk(self::ERP_WHERE_IN_CHUNK_SIZE) as $chunk) {
            $chunkRows = DB::connection('erp')
                ->table('dbo.ANAGRARTWEB_WEBT01 as w')
                ->select([
                    'w.DITTA_CG18',
                    'w.CODART_MG66',
                    'w.RADICEARTIC_WEBT01',
                    'w.FLGATTIVO_WEBT01',
                    'w.FLG_B2B_B2C_WEBT01',
                    'w.FLGNOORDINZERO_WEBT01',
                    'w.FLGMODULTIME_WEBT01',
                    'w.FLGINTEMPO_WEBT01',
                    'w.FLGSTAGING_WEBT01',
                    'w.UNITAMISURA_WEBT01',
                    'w.CONFMINACQ_WEBT01',
                    'w.OPZIONEFAM_WEBT01',
                    'w.FAM_99',
                    'w.SFAM_99',
                    'w.GRUPPO_99',
                    'w.SGRUPPO_99',
                    'w.MARCA_MG64',
                    'w.GRUATTR01_W11',
                    'w.GRUATTR02_W12',
                    'w.GRUATTR03_W13',
                    'w.GRUATTR04_W14',
                    'w.GRUATTR05_W15',
                    'w.GRUATTR06_W16',
                    'w.GRUATTR07_W17',
                    'w.GRUATTR08_W18',
                    'w.GRUATTR09_W19',
                    'w.GRUATTR10_W20',
                    'w.GRUATTR11_W21',
                    'w.GRUATTR12_W22',
                    'w.GRUATTR13_W23',
                    'w.GRUATTR14_W24',
                    'w.GRUATTR15_W25',
                    'w.GRUATTR16_W26',
                    'w.GRUATTR17_W27',
                    'w.GRUATTR18_W28',
                    'w.GRUATTR19_W29',
                    'w.GRUATTR20_W30',
                    'w.GRUATTR21_W31',
                    'w.GRUATTR22_W32',
                    'w.GRUATTR23_W33',
                    'w.GRUATTR24_W34',
                    'w.GRUATTR25_W35',
                    'w.GRUATTR26_W36',
                    'w.GRUATTR27_W37',
                    'w.GRUATTR28_W38',
                    'w.GRUATTR29_W39',
                    'w.GRUATTR30_W40',
                    'w.GRUATTR31_W41',
                    'w.GRUATTR32_W42',
                    'w.GRUATTR33_W43',
                    'w.GRUATTR34_W44',
                    'w.GRUATTR35_W45',
                    'w.GRUATTR36_W46',
                    'w.GRUATTR37_W47',
                    'w.GRUATTR38_W48',
                    'w.GRUATTR39_W49',
                    'w.GRUATTR40_W50',
                    'w.OPZIONERAGGR_WEBT01',
                    'w.RAGGRUPCAT1_W51',
                    'w.RAGGRUPCAT2_W52',
                    'w.RAGGRUPCAT3_W53',
                    'w.RAGGRUPCAT4_W54',
                    'w.CODLINEA_W55',
                    'w.CODEDIZIONE_W56',
                    'w.CODCOLLEZIONE_W57',
                    'w.CODBRAND_W58',
                    'w.CODFANTASIE_W59',
                    'w.CODASSOCIAZIONEART_W60',
                    'w.RAGGRUPASSOC1_W61',
                    'w.RAGGRUPASSOC2_W62',
                    'w.RAGGRUPASSOC3_W63',
                    'w.RAGGRUPASSOC4_W64',
                    'w.PAGCATALOGO_WEBT01',
                    'w.FLGOFFERTA_WEBT01',
                    'w.DATAINIZOFFERTA_WEBT01',
                    'w.DATAFINEOFFERTA_WEBT01',
                    'w.FLGPROMO_WEBT01',
                    'w.DATAINIZPROMO_WEBT01',
                    'w.DATAFINEPROMO_WEBT01',
                    'w.FLGNOVITA_WEBT01',
                    'w.DATAINIZNOVITA_WEBT01',
                    'w.DATAFINENOVITA_WEBT01',
                    'w.FLGCAMPAGNA_WEBT01',
                    'w.DATAINIZCAMPAGNA_WEBT01',
                    'w.DATAFINECAMPAGNA_WEBT01',
                    'w.DATAULTIMOAGG_WEBT01',
                    'w.QTAMAXVISIBILE_WEBT01',
                    'w.FLGSEMAFORO_WEBT01',
                    'w.QTASEMAFVERDE_WEBT01',
                    'w.QTASEMAFARANCIO_WEBT01',
                    'w.QTASEMAFROSSO_WEBT01',
                    'w.NOTEDEPPREL_MG69',
                    'w.CODCONFEZ_MG96',
                    'w.PZCONF_MG68',
                    'w.PESOCALC',
                    'w.UMPESO_MG68',
                    'w.PESON_MG68',
                    'w.PESOL_MG68',
                    'w.MASSANETTA_MG98',
                    'w.LARGH_MG68',
                    'w.ALTEZ_MG68',
                    'w.PROF_MG68',
                    'w.CODBARCODE_MG65',
                    'w.CODGRUPFIS_MG61',
                ])
                ->where('w.DITTA_CG18', $ditta)
                ->where('w.FLG_B2B_B2C_WEBT01', $site)
                ->whereIn('w.CODART_MG66', $chunk->all())
                ->orderBy('w.CODART_MG66')
                ->get();

            $rows = $rows->merge($chunkRows);
        }

        return $rows->sortBy('CODART_MG66')->values();
    }

    private function fetchSimpleDescriptionRows(int $ditta, int $site, Collection $skus): Collection
    {
        $rows = collect();

        foreach ($skus->filter()->unique()->values()->chunk(self::ERP_WHERE_IN_CHUNK_SIZE) as $chunk) {
            $chunkRows = DB::connection('erp')
                ->table('dbo.ARTDESC_TOT as d')
                ->select([
                    'd.DITTA_CG18',
                    'd.FLG_B2B_B2C',
                    'd.CODART_MG66',
                    'd.LINGUA_MG52',
                    'd.DESCART_MG87',
                    'd.DESCARTEST_MG87',
                    'd.NOTEART_MG87',
                    'd.LASTCHANGE_WEBT87',
                    'd.METATAG_WEBT87',
                ])
                ->where('d.DITTA_CG18', $ditta)
                ->where('d.FLG_B2B_B2C', $site)
                ->whereIn('d.CODART_MG66', $chunk->all())
                ->orderBy('d.CODART_MG66')
                ->orderBy('d.LINGUA_MG52')
                ->get();

            $rows = $rows->merge($chunkRows);
        }

        return $rows->sortBy([
            ['CODART_MG66', 'asc'],
            ['LINGUA_MG52', 'asc'],
        ])->values();
    }

    private function normalizeSinceDate(?string $since): string
    {
        if ($since) {
            $value = trim($since);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return $value;
            }
        }

        return Carbon::now('Europe/Rome')->subDays(7)->toDateString();
    }

    private function trimOrNull($value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string === '' ? null : $string;
    }

    private function toBool($value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        $string = strtoupper(trim((string) $value));

        if ($string === '') {
            return $default;
        }

        return in_array($string, ['1', 'Y', 'YES', 'TRUE', 'T', '9'], true);
    }

    private function toDate($value): ?string
    {
        $string = trim((string) ($value ?? ''));

        if ($string === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $string)) {
            return substr($string, 0, 10);
        }

        return null;
    }

    /**
     * CONFMINACQ_WEBT01 può arrivare come "5.000" / "1.000" / "2,500".
     * Vogliamo un intero con minimo 1.
     */
    private function toMinQty($value, int $default = 1): int
    {
        if ($value === null) {
            return $default;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return $default;
        }

        $string = str_replace(',', '.', $string);

        if (!is_numeric($string)) {
            return $default;
        }

        $number = (float) $string;
        $number = (int) ceil($number);

        return $number < 1 ? 1 : $number;
    }

    private function toDecimalOrNull($value, ?int $scale = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        $string = str_replace(',', '.', $string);

        if (!is_numeric($string)) {
            return null;
        }

        $number = (float) $string;

        if ($scale !== null) {
            return number_format($number, $scale, '.', '');
        }

        return (string) $number;
    }

    private function toNullableInt($value): ?int
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        if (!is_numeric($string)) {
            return null;
        }

        return (int) $string;
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
