<?php

namespace App\Services\Erp;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\MediaAsset;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductMediaSyncService
{
    private string $erpRoot;
    private static bool $erpSessionInitialized = false;

    /**
     * key = dstRel (es: image_product/7240EG21_A.jpg)
     * val = ['srcAbs'=>string,'score'=>int,'erpDate'=>?string]
     *
     * @var array<string, array{srcAbs:string, score:int, erpDate:?string}>
     */
    private array $bestCopyCandidate = [];

    public function __construct()
    {
        $this->erpRoot = rtrim((string) env('ERP_MEDIA_ROOT', '/Volumes/magentoimg'), '/');
    }

    /**
     * @return array{
     *   simple_rows:int,
     *   parent_rows:int,
     *   skipped_by_date:int,
     *   assets_upserted:int,
     *   files_copied:int,
     *   files_skipped:int,
     *   missing_source:int,
     *   products_not_found:int,
     *   swatch_targets_not_found:int,
     *   resolved_sources:int,
     *   copy_candidates:int
     * }
     */
    public function sync(
        ?array $onlyDitte = null,
        ?array $onlySites = null,
        ?string $since = null,
        bool $dryRun = false,
        bool $copyFiles = false,
        bool $force = false,
        ?int $limit = null
    ): array {
        $stats = [
            'simple_rows' => 0,
            'parent_rows' => 0,
            'skipped_by_date' => 0,
            'assets_upserted' => 0,
            'files_copied' => 0,
            'files_skipped' => 0,
            'missing_source' => 0,
            'products_not_found' => 0,
            'swatch_targets_not_found' => 0,
            'resolved_sources' => 0,
            'copy_candidates' => 0,
        ];

        try {
            $this->initErpSession();

            $onlyDitte = $this->toIntArray($onlyDitte);
            $onlySites = $this->toIntArray($onlySites);
            $sinceDate = $this->normalizeSinceDate($since);

            Log::info('ERP Media Sync start', [
                'erp_root' => $this->erpRoot,
                'only_ditte' => $onlyDitte,
                'only_sites' => $onlySites,
                'since_date' => $sinceDate,
                'dry_run' => $dryRun,
                'copy_files' => $copyFiles,
                'force' => $force,
                'limit' => $limit,
            ]);

            $a09Id = $this->getA09AttributeId();

            Log::info('ERP Media Sync A09 attribute lookup', [
                'a09_attribute_id' => $a09Id,
            ]);

            $this->syncSimpleMedia($stats, $a09Id, $onlyDitte, $onlySites, $sinceDate, $dryRun, $copyFiles, $limit);
            $this->syncParentMedia($stats, $onlyDitte, $onlySites, $sinceDate, $dryRun, $copyFiles, $limit);

            if ($copyFiles && !$dryRun) {
                $this->flushBestCopies($stats, $force);
            }

            Log::info('ERP Media Sync completed', $stats);

            return $stats;
        } catch (Throwable $e) {
            Log::error('ERP Media Sync failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
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

    /**
     * SIMPLE: dbo.ANAGRARTWEBFOTO_WEBT02
     */
    private function syncSimpleMedia(
        array &$stats,
        ?int $a09Id,
        ?array $onlyDitte,
        ?array $onlySites,
        string $sinceDate,
        bool $dryRun,
        bool $copyFiles,
        ?int $limit
    ): void {
        $q = DB::connection('erp')
            ->table('dbo.ANAGRARTWEBFOTO_WEBT02')
            ->select([
                'DITTA_CG18',
                'CODART_MG66',
                'FLG_B2B_B2C_WEBT02',
                'PATHFOTO_WEBT02',
                'FOTO01_WEBT02',
                'FOTO02_WEBT02',
                'FOTO03_WEBT02',
                'FOTO04_WEBT02',
                'FOTO05_WEBT02',
                'FOTO06_WEBT02',
                'FOTO07_WEBT02',
                'FOTO08_WEBT02',
                'FOTO09_WEBT02',
                'FOTO10_WEBT02',
                'FOTOLINEA01_WEBT02',
                'FOTOLINEA02_WEBT02',
                'FOTOLINEA03_WEBT02',
                'PATHFOTOATTR_WEBT02',
                'FOTOCOLORE01_WEBT02',
                'FOTOEDIZIONE01_WEBT02',
                'FOTOMATERIALE01_WEBT02',
                'FOTOFORMATO01_WEBT02',
                'PATHICONE_WEBT02',
                'FOTOICONA01_WEBT02',
                'FOTOICONA02_WEBT02',
                'FOTOICONA03_WEBT02',
                'FOTOICONA04_WEBT02',
                'FOTOICONA05_WEBT02',
                'FOTOICONA06_WEBT02',
                'FOTOICONA07_WEBT02',
                'FOTOICONA08_WEBT02',
                'FOTOICONA09_WEBT02',
                'FOTOICONA10_WEBT02',
                'FOTOICONA11_WEBT02',
                'FOTOICONA12_WEBT02',
                'DOCUMPDF01_WEBT02',
                'DOCUMPDF02_WEBT02',
                'DOCUMPDF03_WEBT02',
                'DATAULTIMOAGG_WEBT02',
            ])
            ->where('DATAULTIMOAGG_WEBT02', '>=', $sinceDate);

        if (!empty($onlyDitte)) {
            $q->whereIn('DITTA_CG18', $onlyDitte);
        }

        if (!empty($onlySites)) {
            $q->whereIn('FLG_B2B_B2C_WEBT02', $onlySites);
        }

        if ($limit !== null) {
            $q->limit((int) $limit);
        }

        Log::info('ERP Media Sync simple query start', [
            'only_ditte' => $onlyDitte,
            'only_sites' => $onlySites,
            'since_date' => $sinceDate,
            'dry_run' => $dryRun,
            'copy_files' => $copyFiles,
            'limit' => $limit,
        ]);

        foreach ($q->get() as $r) {
            $stats['simple_rows']++;

            $ditta = (int) ($r->DITTA_CG18 ?? 0);
            $site = (int) ($r->FLG_B2B_B2C_WEBT02 ?? 0);
            $sku = $this->trimOrNull($r->CODART_MG66 ?? null);

            if ($ditta <= 0 || $site <= 0 || !$sku) {
                Log::warning('ERP Media Sync simple row skipped: invalid identity', [
                    'ditta' => $ditta,
                    'site' => $site,
                    'sku' => $sku,
                    'row' => (array) $r,
                ]);
                continue;
            }

            $erpDate = $this->toDate($r->DATAULTIMOAGG_WEBT02 ?? null);

            if (!$erpDate || $erpDate < $sinceDate) {
                $stats['skipped_by_date']++;

                Log::info('ERP Media Sync simple row skipped by date', [
                    'sku' => $sku,
                    'ditta' => $ditta,
                    'site' => $site,
                    'erp_date_raw' => $r->DATAULTIMOAGG_WEBT02 ?? null,
                    'erp_date_normalized' => $erpDate,
                    'since_date' => $sinceDate,
                ]);

                continue;
            }

            $product = Product::query()
                ->where('ditta_cg18', $ditta)
                ->where('site_type', $site)
                ->where('sku', $sku)
                ->first();

            if (!$product) {
                $stats['products_not_found']++;

                Log::warning('ERP Media Sync simple row skipped: product not found locally', [
                    'sku' => $sku,
                    'ditta' => $ditta,
                    'site' => $site,
                    'erp_date' => $erpDate,
                ]);

                continue;
            }

            Log::info('ERP Media Sync simple row processing', [
                'sku' => $sku,
                'product_id' => $product->getKey(),
                'ditta' => $ditta,
                'site' => $site,
                'erp_date' => $erpDate,
                'path_foto_raw' => $r->PATHFOTO_WEBT02 ?? null,
                'path_foto_attr_raw' => $r->PATHFOTOATTR_WEBT02 ?? null,
                'path_icone_raw' => $r->PATHICONE_WEBT02 ?? null,
            ]);

            $pathFoto = $this->normalizeErpPath($r->PATHFOTO_WEBT02 ?? null);

            $photoCols = [
                'FOTO01_WEBT02', 'FOTO02_WEBT02', 'FOTO03_WEBT02', 'FOTO04_WEBT02', 'FOTO05_WEBT02',
                'FOTO06_WEBT02', 'FOTO07_WEBT02', 'FOTO08_WEBT02', 'FOTO09_WEBT02', 'FOTO10_WEBT02',
            ];

            foreach ($photoCols as $idx => $col) {
                $filename = $this->trimOrNull($r->$col ?? null);
                if (!$filename) {
                    continue;
                }

                $role = $idx === 0 ? MediaAsset::ROLE_MAIN : MediaAsset::ROLE_GALLERY;
                $sortOrder = $idx === 0 ? 0 : $idx;

                Log::info('ERP Media Sync product image candidate', [
                    'sku' => $sku,
                    'product_id' => $product->getKey(),
                    'column' => $col,
                    'filename' => $filename,
                    'role' => $role,
                    'erp_path' => $pathFoto,
                ]);

                $stats['assets_upserted'] += $this->upsertAndMaybeQueueCopy(
                    mediable: $product,
                    ditta: $ditta,
                    site: $site,
                    role: $role,
                    sortOrder: $sortOrder,
                    erpPath: $pathFoto,
                    filename: $filename,
                    erpDate: $erpDate,
                    dryRun: $dryRun,
                    copyFiles: $copyFiles,
                    stats: $stats
                );
            }

            $lineCols = ['FOTOLINEA01_WEBT02', 'FOTOLINEA02_WEBT02', 'FOTOLINEA03_WEBT02'];

            foreach ($lineCols as $i => $col) {
                $filename = $this->trimOrNull($r->$col ?? null);
                if (!$filename) {
                    continue;
                }

                Log::info('ERP Media Sync product line-image candidate', [
                    'sku' => $sku,
                    'product_id' => $product->getKey(),
                    'column' => $col,
                    'filename' => $filename,
                    'erp_path' => $pathFoto,
                    'line_index' => $i + 1,
                ]);

                $stats['assets_upserted'] += $this->upsertAndMaybeQueueCopy(
                    mediable: $product,
                    ditta: $ditta,
                    site: $site,
                    role: MediaAsset::ROLE_GALLERY,
                    sortOrder: 100 + $i,
                    erpPath: $pathFoto,
                    filename: $filename,
                    erpDate: $erpDate,
                    dryRun: $dryRun,
                    copyFiles: $copyFiles,
                    stats: $stats,
                    metaKey: 'line',
                    metaValue: (string) ($i + 1)
                );
            }

            $swatchFile = $this->trimOrNull($r->FOTOCOLORE01_WEBT02 ?? null);

            if ($swatchFile) {
                $pathAttr = $this->normalizeErpPath($r->PATHFOTOATTR_WEBT02 ?? null);
                $target = $product;
                $isGlobal = false;

                Log::info('ERP Media Sync swatch candidate', [
                    'sku' => $sku,
                    'product_id' => $product->getKey(),
                    'filename' => $swatchFile,
                    'erp_path' => $pathAttr,
                    'raw_erp_path' => $r->PATHFOTOATTR_WEBT02 ?? null,
                ]);

                if ($a09Id) {
                    $valueCode = $this->filenameToValueCode($swatchFile);

                    if ($valueCode) {
                        $attrValue = AttributeValue::query()
                            ->where('attribute_id', $a09Id)
                            ->whereRaw('RTRIM(LTRIM(UPPER(value_code))) = ?', [$valueCode])
                            ->first();

                        if ($attrValue) {
                            $target = $attrValue;
                            $isGlobal = true;

                            Log::info('ERP Media Sync swatch matched attribute value', [
                                'sku' => $sku,
                                'filename' => $swatchFile,
                                'value_code' => $valueCode,
                                'attribute_value_id' => $attrValue->getKey(),
                            ]);
                        } else {
                            $stats['swatch_targets_not_found']++;

                            Log::warning('ERP Media Sync swatch attribute value not found', [
                                'sku' => $sku,
                                'filename' => $swatchFile,
                                'value_code' => $valueCode,
                                'attribute_id' => $a09Id,
                            ]);
                        }
                    }
                }

                Log::info('ERP Media Sync swatch target resolved', [
                    'sku' => $sku,
                    'filename' => $swatchFile,
                    'is_global' => $isGlobal,
                    'target_type' => get_class($target),
                    'target_id' => $target->getKey(),
                    'site_type' => $isGlobal ? 0 : $site,
                    'ditta_cg18' => $isGlobal ? 0 : $ditta,
                ]);

                $stats['assets_upserted'] += $this->upsertAndMaybeQueueCopy(
                    mediable: $target,
                    ditta: $isGlobal ? 0 : $ditta,
                    site: $isGlobal ? 0 : $site,
                    role: MediaAsset::ROLE_SWATCH,
                    sortOrder: 0,
                    erpPath: $pathAttr,
                    filename: $swatchFile,
                    erpDate: $erpDate,
                    dryRun: $dryRun,
                    copyFiles: $copyFiles,
                    stats: $stats,
                    metaKey: 'attr',
                    metaValue: 'A09'
                );
            }

            $pathIcone = $this->normalizeErpPath($r->PATHICONE_WEBT02 ?? null);

            $iconCols = [
                'FOTOICONA01_WEBT02', 'FOTOICONA02_WEBT02', 'FOTOICONA03_WEBT02', 'FOTOICONA04_WEBT02',
                'FOTOICONA05_WEBT02', 'FOTOICONA06_WEBT02', 'FOTOICONA07_WEBT02', 'FOTOICONA08_WEBT02',
                'FOTOICONA09_WEBT02', 'FOTOICONA10_WEBT02', 'FOTOICONA11_WEBT02', 'FOTOICONA12_WEBT02',
            ];

            foreach ($iconCols as $i => $col) {
                $filename = $this->trimOrNull($r->$col ?? null);
                if (!$filename) {
                    continue;
                }

                Log::info('ERP Media Sync icon candidate', [
                    'sku' => $sku,
                    'product_id' => $product->getKey(),
                    'column' => $col,
                    'filename' => $filename,
                    'erp_path' => $pathIcone,
                ]);

                $stats['assets_upserted'] += $this->upsertAndMaybeQueueCopy(
                    mediable: $product,
                    ditta: $ditta,
                    site: $site,
                    role: MediaAsset::ROLE_ICON,
                    sortOrder: $i,
                    erpPath: $pathIcone,
                    filename: $filename,
                    erpDate: $erpDate,
                    dryRun: $dryRun,
                    copyFiles: $copyFiles,
                    stats: $stats
                );
            }

            $pdfCols = ['DOCUMPDF01_WEBT02', 'DOCUMPDF02_WEBT02', 'DOCUMPDF03_WEBT02'];

            foreach ($pdfCols as $i => $col) {
                $filename = $this->trimOrNull($r->$col ?? null);
                if (!$filename) {
                    continue;
                }

                Log::info('ERP Media Sync pdf candidate', [
                    'sku' => $sku,
                    'product_id' => $product->getKey(),
                    'column' => $col,
                    'filename' => $filename,
                    'erp_path' => $pathFoto,
                ]);

                $stats['assets_upserted'] += $this->upsertAndMaybeQueueCopy(
                    mediable: $product,
                    ditta: $ditta,
                    site: $site,
                    role: MediaAsset::ROLE_PDF,
                    sortOrder: $i,
                    erpPath: $pathFoto,
                    filename: $filename,
                    erpDate: $erpDate,
                    dryRun: $dryRun,
                    copyFiles: $copyFiles,
                    stats: $stats
                );
            }
        }
    }

    /**
     * PADRI: dbo.ANAGRARTPADRE_TOT
     */
    private function syncParentMedia(
        array &$stats,
        ?array $onlyDitte,
        ?array $onlySites,
        string $sinceDate,
        bool $dryRun,
        bool $copyFiles,
        ?int $limit
    ): void {
        $q = DB::connection('erp')
            ->table('dbo.ANAGRARTPADRE_TOT')
            ->select([
                'DITTA_CG18',
                'CODARTPADRE_WEBT00',
                'FLG_B2B_B2C_WEBT00',
                'PATHFOTOPADRE_WEBT00',
                'FOTOARTPADRE_WEBT00',
                'DATAULTIMOAGG_WEBT00',
            ])
            ->where('DATAULTIMOAGG_WEBT00', '>=', $sinceDate);

        if (!empty($onlyDitte)) {
            $q->whereIn('DITTA_CG18', $onlyDitte);
        }

        if (!empty($onlySites)) {
            $q->whereIn('FLG_B2B_B2C_WEBT00', $onlySites);
        }

        if ($limit !== null) {
            $q->limit((int) $limit);
        }

        Log::info('ERP Media Sync parent query start', [
            'only_ditte' => $onlyDitte,
            'only_sites' => $onlySites,
            'since_date' => $sinceDate,
            'dry_run' => $dryRun,
            'copy_files' => $copyFiles,
            'limit' => $limit,
        ]);

        foreach ($q->get() as $r) {
            $stats['parent_rows']++;

            $ditta = (int) ($r->DITTA_CG18 ?? 0);
            $site = (int) ($r->FLG_B2B_B2C_WEBT00 ?? 0);
            $sku = $this->trimOrNull($r->CODARTPADRE_WEBT00 ?? null);

            if ($ditta <= 0 || $site <= 0 || !$sku) {
                Log::warning('ERP Media Sync parent row skipped: invalid identity', [
                    'ditta' => $ditta,
                    'site' => $site,
                    'sku' => $sku,
                    'row' => (array) $r,
                ]);
                continue;
            }

            $erpDate = $this->toDate($r->DATAULTIMOAGG_WEBT00 ?? null);

            if (!$erpDate || $erpDate < $sinceDate) {
                $stats['skipped_by_date']++;

                Log::info('ERP Media Sync parent row skipped by date', [
                    'sku' => $sku,
                    'ditta' => $ditta,
                    'site' => $site,
                    'erp_date_raw' => $r->DATAULTIMOAGG_WEBT00 ?? null,
                    'erp_date_normalized' => $erpDate,
                    'since_date' => $sinceDate,
                ]);

                continue;
            }

            $path = $this->normalizeErpPath($r->PATHFOTOPADRE_WEBT00 ?? null);
            $file = $this->trimOrNull($r->FOTOARTPADRE_WEBT00 ?? null);

            if (!$file) {
                Log::warning('ERP Media Sync parent row skipped: empty filename', [
                    'sku' => $sku,
                    'ditta' => $ditta,
                    'site' => $site,
                    'erp_path' => $path,
                ]);
                continue;
            }

            $product = Product::query()
                ->where('ditta_cg18', $ditta)
                ->where('site_type', $site)
                ->where('sku', $sku)
                ->where('type', 'configurable')
                ->first();

            if (!$product) {
                $stats['products_not_found']++;

                Log::warning('ERP Media Sync parent row skipped: configurable product not found locally', [
                    'sku' => $sku,
                    'ditta' => $ditta,
                    'site' => $site,
                    'erp_date' => $erpDate,
                ]);

                continue;
            }

            Log::info('ERP Media Sync parent row processing', [
                'sku' => $sku,
                'product_id' => $product->getKey(),
                'ditta' => $ditta,
                'site' => $site,
                'erp_path' => $path,
                'filename' => $file,
                'erp_date' => $erpDate,
            ]);

            $stats['assets_upserted'] += $this->upsertAndMaybeQueueCopy(
                mediable: $product,
                ditta: $ditta,
                site: $site,
                role: MediaAsset::ROLE_MAIN,
                sortOrder: 0,
                erpPath: $path,
                filename: $file,
                erpDate: $erpDate,
                dryRun: $dryRun,
                copyFiles: $copyFiles,
                stats: $stats
            );
        }
    }

    private function upsertAndMaybeQueueCopy(
        $mediable,
        int $ditta,
        int $site,
        string $role,
        int $sortOrder,
        ?string $erpPath,
        string $filename,
        ?string $erpDate,
        bool $dryRun,
        bool $copyFiles,
        array &$stats,
        string $metaKey = '',
        string $metaValue = ''
    ): int {
        $localRelPath = $this->buildLocalRelPath($role, $filename);

        Log::info('ERP Media Sync upsert start', [
            'mediable_type' => get_class($mediable),
            'mediable_id' => $mediable->getKey(),
            'ditta_cg18' => $ditta,
            'site_type' => $site,
            'role' => $role,
            'sort_order' => $sortOrder,
            'erp_path' => $erpPath,
            'filename' => $filename,
            'local_rel_path' => $localRelPath,
            'dry_run' => $dryRun,
            'copy_files' => $copyFiles,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue,
        ]);

        if ($dryRun) {
            Log::info('ERP Media Sync dry-run skip write', [
                'mediable_type' => get_class($mediable),
                'mediable_id' => $mediable->getKey(),
                'role' => $role,
                'filename' => $filename,
            ]);
            return 1;
        }

        $metaKey = trim($metaKey);
        $metaValue = trim($metaValue);

        $mediaAsset = MediaAsset::query()->updateOrCreate(
            [
                'mediable_type' => get_class($mediable),
                'mediable_id' => $mediable->getKey(),
                'role' => $role,
                'filename' => $filename,
                'meta_key' => $metaKey,
                'meta_value' => $metaValue,
            ],
            [
                'ditta_cg18' => $ditta,
                'site_type' => $site,
                'sort_order' => $sortOrder,
                'erp_path' => $erpPath,
                'local_path' => $localRelPath,
                'erp_lastchange' => $erpDate ? ($erpDate . ' 00:00:00') : null,
            ]
        );

        Log::info('ERP Media Sync upsert done', [
            'media_asset_id' => $mediaAsset->getKey(),
            'mediable_type' => $mediaAsset->mediable_type,
            'mediable_id' => $mediaAsset->mediable_id,
            'role' => $mediaAsset->role,
            'filename' => $mediaAsset->filename,
            'erp_path' => $mediaAsset->erp_path,
            'local_path' => $mediaAsset->local_path,
        ]);

        if (!$copyFiles) {
            Log::info('ERP Media Sync copy skipped: copy_files disabled', [
                'media_asset_id' => $mediaAsset->getKey(),
                'role' => $role,
                'filename' => $filename,
            ]);
            return 1;
        }

        $srcAbs = $this->resolveSourceAbsolutePath($erpPath, $filename, $role);
        $dstRel = ltrim($localRelPath, '/');

        Log::info('ERP Media Sync source resolved', [
            'media_asset_id' => $mediaAsset->getKey(),
            'role' => $role,
            'filename' => $filename,
            'erp_path' => $erpPath,
            'resolved_source' => $srcAbs,
            'destination_relative' => $dstRel,
        ]);

        if (!$srcAbs || !is_file($srcAbs)) {
            $stats['missing_source']++;

            Log::warning('ERP Media Sync missing source', [
                'mediable_type' => get_class($mediable),
                'mediable_id' => $mediable->getKey(),
                'role' => $role,
                'filename' => $filename,
                'erp_path' => $erpPath,
                'resolved_source' => $srcAbs,
            ]);

            Log::info('ERP Media Sync missing source diagnostics', [
                'erp_root' => $this->erpRoot,
                'normalized_erp_path' => $this->normalizeErpPath($erpPath),
                'filename_upper' => strtoupper($filename),
                'filename_lower' => strtolower($filename),
            ]);

            return 1;
        }

        $stats['resolved_sources']++;

        $score = $this->computeSourceScore($srcAbs, $erpDate);
        $prev = $this->bestCopyCandidate[$dstRel] ?? null;

        if (!$prev || $score > $prev['score']) {
            $this->bestCopyCandidate[$dstRel] = [
                'srcAbs' => $srcAbs,
                'score' => $score,
                'erpDate' => $erpDate,
            ];
        }

        $stats['copy_candidates']++;

        Log::info('ERP Media Sync copy candidate queued', [
            'media_asset_id' => $mediaAsset->getKey(),
            'source' => $srcAbs,
            'destination_relative' => $dstRel,
            'score' => $score,
            'erp_date' => $erpDate,
        ]);

        return 1;
    }

    private function resolveSourceAbsolutePath(?string $erpPath, string $filename, string $role): ?string
    {
        $filename = trim($filename);

        if ($filename === '') {
            return null;
        }

        $normalizedPath = $this->normalizeErpPath($erpPath);
        $candidates = [];

        if ($role === MediaAsset::ROLE_SWATCH) {
            $candidates[] = $this->erpRoot . '/Colori/' . $filename;
            $candidates[] = $this->erpRoot . '/colori/' . $filename;
        }

        if ($normalizedPath !== null) {
            $candidates[] = $this->erpRoot . '/' . ltrim($normalizedPath, '/') . '/' . $filename;
        }

        $candidates[] = $this->erpRoot . '/' . $filename;

        if ($role !== MediaAsset::ROLE_SWATCH) {
            $candidates[] = $this->erpRoot . '/Padre/' . $filename;
            $candidates[] = $this->erpRoot . '/padre/' . $filename;
        }

        Log::info('ERP Media Sync resolve source candidates', [
            'role' => $role,
            'filename' => $filename,
            'erp_path_original' => $erpPath,
            'erp_path_normalized' => $normalizedPath,
            'candidates' => $candidates,
        ]);

        foreach ($candidates as $candidate) {
            $resolved = $this->resolveExistingPathCaseInsensitive($candidate);

            Log::info('ERP Media Sync resolve source probe', [
                'candidate' => $candidate,
                'resolved' => $resolved,
                'exists' => $resolved !== null ? is_file($resolved) : false,
            ]);

            if ($resolved !== null && is_file($resolved)) {
                return $resolved;
            }
        }

        return $candidates[0] ?? null;
    }

    private function resolveExistingPathCaseInsensitive(string $path): ?string
    {
        $normalized = str_replace('\\', '/', trim($path));

        if ($normalized === '') {
            return null;
        }

        Log::info('ERP Media Sync case-insensitive resolve start', [
            'input_path' => $path,
            'normalized_path' => $normalized,
        ]);

        if (file_exists($normalized)) {
            Log::info('ERP Media Sync case-insensitive resolve exact hit', [
                'path' => $normalized,
            ]);
            return $normalized;
        }

        $isAbsolute = str_starts_with($normalized, '/');
        $parts = array_values(array_filter(explode('/', $normalized), static fn ($part) => $part !== ''));

        if ($parts === []) {
            return $isAbsolute ? '/' : null;
        }

        $current = $isAbsolute ? '/' : array_shift($parts);

        if (!$isAbsolute && $current !== null && !file_exists($current)) {
            return null;
        }

        foreach ($parts as $part) {
            if (!is_dir($current)) {
                return null;
            }

            $matched = $this->findPathSegmentCaseInsensitive($current, $part);

            if ($matched === null) {
                return null;
            }

            $current = rtrim($current, '/') . '/' . $matched;
        }

        Log::info('ERP Media Sync case-insensitive resolve end', [
            'input_path' => $path,
            'resolved_path' => $current,
            'exists' => file_exists($current),
        ]);

        return file_exists($current) ? $current : null;
    }

    private function findPathSegmentCaseInsensitive(string $directory, string $segment): ?string
    {
        $entries = @scandir($directory);

        if ($entries === false) {
            return null;
        }

        Log::info('ERP Media Sync scandir entries', [
            'directory' => $directory,
            'segment' => $segment,
            'entries_count' => count($entries),
        ]);

        foreach ($entries as $entry) {
            if (strcasecmp($entry, $segment) === 0) {
                Log::info('ERP Media Sync segment matched case-insensitive', [
                    'directory' => $directory,
                    'segment' => $segment,
                    'matched_entry' => $entry,
                ]);
                return $entry;
            }
        }

        return null;
    }

    private function flushBestCopies(array &$stats, bool $force): void
    {
        $disk = Storage::disk('public');

        foreach ($this->bestCopyCandidate as $dstRel => $cand) {
            $srcAbs = $cand['srcAbs'];
            $erpDate = $cand['erpDate'];

            Log::info('ERP Media Sync flush candidate', [
                'destination_relative' => $dstRel,
                'source' => $srcAbs,
                'erp_date' => $erpDate,
                'force' => $force,
            ]);

            $mustCopy = $force;

            if (!$mustCopy) {
                if (!$disk->exists($dstRel)) {
                    $mustCopy = true;
                } else {
                    $dstAbs = $disk->path($dstRel);
                    $dstMtime = @filemtime($dstAbs) ?: 0;

                    if ($erpDate) {
                        $erpTs = strtotime($erpDate . ' 00:00:00') ?: 0;
                        if ($erpTs > $dstMtime) {
                            $mustCopy = true;
                        }
                    }

                    $srcMtime = @filemtime($srcAbs) ?: 0;
                    if ($srcMtime > $dstMtime) {
                        $mustCopy = true;
                    }
                }
            }

            if (!$mustCopy) {
                $stats['files_skipped']++;

                Log::info('ERP Media Sync file copy skipped', [
                    'destination_relative' => $dstRel,
                    'source' => $srcAbs,
                ]);

                continue;
            }

            $disk->makeDirectory(dirname($dstRel));

            $bytes = @file_put_contents($disk->path($dstRel), file_get_contents($srcAbs));

            if ($bytes === false) {
                $stats['files_skipped']++;

                Log::warning('ERP Media Sync file copy failed', [
                    'destination_relative' => $dstRel,
                    'destination_absolute' => $disk->path($dstRel),
                    'source' => $srcAbs,
                ]);
            } else {
                $stats['files_copied']++;

                Log::info('ERP Media Sync file copied', [
                    'destination_relative' => $dstRel,
                    'destination_absolute' => $disk->path($dstRel),
                    'source' => $srcAbs,
                    'bytes' => $bytes,
                ]);
            }
        }
    }

    private function computeSourceScore(string $srcAbs, ?string $erpDate): int
    {
        $erpTs = $erpDate ? (int) (strtotime($erpDate . ' 00:00:00') ?: 0) : 0;
        $srcTs = (int) (@filemtime($srcAbs) ?: 0);

        return ($erpTs * 10000) + $srcTs;
    }

    private function buildLocalRelPath(string $role, string $filename): string
    {
        $filename = trim($filename);

        return match ($role) {
            MediaAsset::ROLE_SWATCH => 'Colori/' . $filename,
            MediaAsset::ROLE_ICON => 'icons/' . $filename,
            MediaAsset::ROLE_PDF => 'pdf/' . $filename,
            default => 'image_product/' . $filename,
        };
    }

    private function normalizeSinceDate(?string $since): string
    {
        if ($since) {
            $s = trim($since);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                return $s;
            }
        }

        return Carbon::now('Europe/Rome')->toDateString();
    }

    private function normalizeErpPath($v): ?string
    {
        $s = trim((string) ($v ?? ''));

        if ($s === '') {
            return null;
        }

        $s = str_replace('\\', '/', $s);
        $s = preg_replace('#/+#', '/', $s) ?? $s;
        $s = preg_replace('#^([A-Za-z]):/#', '', $s) ?? $s;
        $s = preg_replace('#^' . preg_quote($this->erpRoot, '#') . '/?#i', '', $s) ?? $s;
        $s = trim($s, '/');

        Log::info('ERP Media Sync normalize path', [
            'input' => $v,
            'normalized' => $s === '' ? null : $s,
        ]);

        return $s === '' ? null : $s;
    }

    private function trimOrNull($v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }

    private function toDate($v): ?string
    {
        $s = trim((string) ($v ?? ''));

        if ($s === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
            return substr($s, 0, 10);
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
            [$d, $m, $y] = explode('/', $s);
            return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
        }

        return null;
    }

    private function filenameToValueCode(string $filename): ?string
    {
        $base = trim((string) pathinfo($filename, PATHINFO_FILENAME));

        if ($base === '') {
            return null;
        }

        return strtoupper($base);
    }

    private function getA09AttributeId(): ?int
    {
        $id = (int) (Attribute::query()
            ->where('code', 'A09')
            ->orderBy('id')
            ->value('id') ?? 0);

        return $id > 0 ? $id : null;
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