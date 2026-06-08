<?php

namespace App\Services\Erp;

use App\Models\GroupDescription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GroupDescriptionSyncService
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
     *   rows:int,
     *   upserts:int,
     *   skipped_locale:int
     * }
     */
    public function sync(
        ?array $onlyDitte = null,
        ?array $onlySites = null,
        bool $dryRun = false,
        ?int $limit = null
    ): array {
        $this->initErpSession();

        $stats = [
            'rows' => 0,
            'upserts' => 0,
            'skipped_locale' => 0,
        ];

        $onlyDitte = $this->toIntArray($onlyDitte);
        $onlySites = $this->toIntArray($onlySites);

        try {
            $this->syncRows(
                $this->fetchFamiglie($onlyDitte, $onlySites, $limit),
                $stats,
                $dryRun,
                'famiglia'
            );

            $this->syncRows(
                $this->fetchSottofamiglie($onlyDitte, $onlySites, $limit),
                $stats,
                $dryRun,
                'sottofamiglia'
            );

            $this->syncRows(
                $this->fetchGruppi($onlyDitte, $onlySites, $limit),
                $stats,
                $dryRun,
                'gruppo'
            );

            return $stats;
        } catch (Throwable $e) {
            Log::error('ERP GroupDescription Sync failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function fetchFamiglie(?array $onlyDitte, ?array $onlySites, ?int $limit): Collection
    {
        $q = DB::connection('erp')
            ->table('dbo.FAMIGLIE_WEBT07')
            ->select([
                'DITTA_CG18',
                'FAM_WEBT07',
                'LINGUA_WEBT07',
                'FLG_B2B_B2C_WEBT07',
                'DESCRFAM_WEBT07',
                'FLG_FAM_ATTIVA_WEBT07',
            ])
            ->when(!empty($onlyDitte), fn ($x) => $x->whereIn('DITTA_CG18', $onlyDitte))
            ->when(!empty($onlySites), fn ($x) => $x->whereIn('FLG_B2B_B2C_WEBT07', $onlySites))
            ->orderBy('DITTA_CG18')
            ->orderBy('FLG_B2B_B2C_WEBT07')
            ->orderBy('FAM_WEBT07')
            ->orderBy('LINGUA_WEBT07');

        if ($limit !== null) {
            $q->limit((int) $limit);
        }

        return $q->get()->map(function ($row) {
            return [
                'level' => 'famiglia',
                'ditta_cg18' => (int) ($row->DITTA_CG18 ?? 0),
                'site_type' => (int) ($row->FLG_B2B_B2C_WEBT07 ?? 0),
                'locale' => $this->mapLocale($row->LINGUA_WEBT07 ?? null),
                'fam_code' => $this->trimOrNull($row->FAM_WEBT07 ?? null),
                'sfam_code' => null,
                'gruppo_code' => null,
                'description' => $this->trimOrNull($row->DESCRFAM_WEBT07 ?? null),
                'is_active' => $this->toBool($row->FLG_FAM_ATTIVA_WEBT07 ?? null, true),
            ];
        });
    }

    private function fetchSottofamiglie(?array $onlyDitte, ?array $onlySites, ?int $limit): Collection
    {
        $q = DB::connection('erp')
            ->table('dbo.SFAMIGLIE_WEBT08')
            ->select([
                'DITTA_CG18',
                'FAM_WEBT08',
                'SFAM_WEBT08',
                'LINGUA_WEBT08',
                'FLG_B2B_B2C_WEBT08',
                'DESCRSFAM_WEBT08',
                'FLG_SFAM_ATTIVA_WEBT08',
            ])
            ->when(!empty($onlyDitte), fn ($x) => $x->whereIn('DITTA_CG18', $onlyDitte))
            ->when(!empty($onlySites), fn ($x) => $x->whereIn('FLG_B2B_B2C_WEBT08', $onlySites))
            ->orderBy('DITTA_CG18')
            ->orderBy('FLG_B2B_B2C_WEBT08')
            ->orderBy('FAM_WEBT08')
            ->orderBy('SFAM_WEBT08')
            ->orderBy('LINGUA_WEBT08');

        if ($limit !== null) {
            $q->limit((int) $limit);
        }

        return $q->get()->map(function ($row) {
            return [
                'level' => 'sottofamiglia',
                'ditta_cg18' => (int) ($row->DITTA_CG18 ?? 0),
                'site_type' => (int) ($row->FLG_B2B_B2C_WEBT08 ?? 0),
                'locale' => $this->mapLocale($row->LINGUA_WEBT08 ?? null),
                'fam_code' => $this->trimOrNull($row->FAM_WEBT08 ?? null),
                'sfam_code' => $this->trimOrNull($row->SFAM_WEBT08 ?? null),
                'gruppo_code' => null,
                'description' => $this->trimOrNull($row->DESCRSFAM_WEBT08 ?? null),
                'is_active' => $this->toBool($row->FLG_SFAM_ATTIVA_WEBT08 ?? null, true),
            ];
        });
    }

    private function fetchGruppi(?array $onlyDitte, ?array $onlySites, ?int $limit): Collection
    {
        $q = DB::connection('erp')
            ->table('dbo.GRUPP0_WEBT09')
            ->select([
                'DITTA_CG18',
                'FAM_WEBT09',
                'SFAM_WEBT09',
                'GRUPPO_WEBT09',
                'LINGUA_WEBT09',
                'FLG_B2B_B2C_WEBT09',
                'DESCRGRUPPO_WEBT09',
                'FLG_GR_ATTIVO_WEBT09',
            ])
            ->when(!empty($onlyDitte), fn ($x) => $x->whereIn('DITTA_CG18', $onlyDitte))
            ->when(!empty($onlySites), fn ($x) => $x->whereIn('FLG_B2B_B2C_WEBT09', $onlySites))
            ->orderBy('DITTA_CG18')
            ->orderBy('FLG_B2B_B2C_WEBT09')
            ->orderBy('FAM_WEBT09')
            ->orderBy('SFAM_WEBT09')
            ->orderBy('GRUPPO_WEBT09')
            ->orderBy('LINGUA_WEBT09');

        if ($limit !== null) {
            $q->limit((int) $limit);
        }

        return $q->get()->map(function ($row) {
            return [
                'level' => 'gruppo',
                'ditta_cg18' => (int) ($row->DITTA_CG18 ?? 0),
                'site_type' => (int) ($row->FLG_B2B_B2C_WEBT09 ?? 0),
                'locale' => $this->mapLocale($row->LINGUA_WEBT09 ?? null),
                'fam_code' => $this->trimOrNull($row->FAM_WEBT09 ?? null),
                'sfam_code' => $this->trimOrNull($row->SFAM_WEBT09 ?? null),
                'gruppo_code' => $this->trimOrNull($row->GRUPPO_WEBT09 ?? null),
                'description' => $this->trimOrNull($row->DESCRGRUPPO_WEBT09 ?? null),
                'is_active' => $this->toBool($row->FLG_GR_ATTIVO_WEBT09 ?? null, true),
            ];
        });
    }

    private function syncRows(Collection $rows, array &$stats, bool $dryRun, string $level): void
    {
        foreach ($rows as $payload) {
            $stats['rows']++;

            if (($payload['locale'] ?? null) === null) {
                $stats['skipped_locale']++;
                continue;
            }

            if (
                ($payload['ditta_cg18'] ?? 0) <= 0 ||
                ($payload['site_type'] ?? 0) <= 0
            ) {
                continue;
            }

            if ($level === 'famiglia' && empty($payload['fam_code'])) {
                continue;
            }

            if ($level === 'sottofamiglia' && (empty($payload['fam_code']) || empty($payload['sfam_code']))) {
                continue;
            }

            if ($level === 'gruppo' && empty($payload['gruppo_code'])) {
                continue;
            }

            if (!$dryRun) {
                GroupDescription::updateOrCreate(
                    [
                        'ditta_cg18' => $payload['ditta_cg18'],
                        'site_type' => $payload['site_type'],
                        'locale' => $payload['locale'],
                        'fam_code' => $payload['fam_code'],
                        'sfam_code' => $payload['sfam_code'],
                        'gruppo_code' => $payload['gruppo_code'],
                    ],
                    [
                        'description' => $payload['description'],
                        'is_active' => $payload['is_active'],
                    ]
                );
            }

            $stats['upserts']++;
        }
    }

    private function mapLocale($value): ?string
    {
        $lang = strtoupper(trim((string) ($value ?? '')));
        if ($lang === '') {
            return null;
        }

        return self::LOCALE_MAP[$lang] ?? null;
    }

    private function trimOrNull($value): ?string
    {
        $s = trim((string) ($value ?? ''));
        return $s === '' ? null : $s;
    }

    private function toBool($value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        $s = strtoupper(trim((string) $value));
        if ($s === '') {
            return $default;
        }

        return in_array($s, ['1', 'Y', 'YES', 'TRUE', 'T', '9'], true);
    }

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