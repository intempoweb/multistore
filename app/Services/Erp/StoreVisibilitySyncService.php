<?php

namespace App\Services\Erp;

use App\Models\StoreVisibleGroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StoreVisibilitySyncService
{
    private const CHUNK_SIZE = 500;

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
     * Sync da ERP dbo.ANAGRAMARCLIVIS -> store_visible_groups
     *
     * ERP columns:
     * - DITTA_CG18 numeric(5,0)
     * - CODICESITO int
     * - CODICE_XX32 char(25)
     * - DESCRIZIONE_XX32 char(60)
     *
     * @return array{rows_read:int, upserts:int}
     */
    public function sync(
        ?array $onlyDitte = null,
        ?array $onlySites = null,
        bool $dryRun = false
    ): array {
        $this->initErpSession();

        $stats = [
            'rows_read' => 0,
            'upserts' => 0,
        ];

        $onlyDitte = $this->toIntArray($onlyDitte);
        $onlySites = $this->toIntArray($onlySites);

        $runStartedAt = Carbon::now();
        $nowTs = Carbon::now();

        try {
            $q = DB::connection('erp')
                ->table('dbo.ANAGRAMARCLIVIS')
                ->select([
                    'DITTA_CG18',
                    'CODICESITO',
                    'CODICE_XX32',
                    'DESCRIZIONE_XX32',
                ]);

            if (!empty($onlyDitte)) $q->whereIn('DITTA_CG18', $onlyDitte);
            if (!empty($onlySites)) $q->whereIn('CODICESITO', $onlySites);

            $payload = [];

            foreach ($q->cursor() as $row) {
                $stats['rows_read']++;

                $ditta = (int) ($row->DITTA_CG18 ?? 0);
                $site  = (int) ($row->CODICESITO ?? 0);

                $code = $this->trimOrNull($row->CODICE_XX32 ?? null);        // char(25)
                $desc = $this->trimOrNull($row->DESCRIZIONE_XX32 ?? null);   // char(60)

                if ($ditta <= 0 || $site <= 0 || !$code) continue;

                if ($dryRun) {
                    $stats['upserts']++;
                    continue;
                }

                $payload[] = [
                    'ditta_cg18'       => $ditta,
                    'site_type'        => $site,
                    'codice_xx32'      => $code,
                    'descrizione_xx32' => $desc,
                    'erp_last_seen_at' => $runStartedAt,
                    'created_at'       => $nowTs,
                    'updated_at'       => $nowTs,
                ];

                $stats['upserts']++;

                if (count($payload) >= self::CHUNK_SIZE) {
                    StoreVisibleGroup::query()->upsert(
                        $payload,
                        ['ditta_cg18', 'site_type', 'codice_xx32'],
                        ['descrizione_xx32', 'erp_last_seen_at', 'updated_at']
                    );
                    $payload = [];
                }
            }

            if (!$dryRun && !empty($payload)) {
                StoreVisibleGroup::query()->upsert(
                    $payload,
                    ['ditta_cg18', 'site_type', 'codice_xx32'],
                    ['descrizione_xx32', 'erp_last_seen_at', 'updated_at']
                );
            }

            return $stats;
        } catch (Throwable $e) {
            Log::error('ERP Store Visibility Sync failed', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    private function trimOrNull($v): ?string
    {
        $s = trim((string)($v ?? ''));
        return $s === '' ? null : $s;
    }

    private function toIntArray(?array $v): ?array
    {
        if (empty($v)) return null;

        $out = [];
        foreach ($v as $x) {
            $n = (int)$x;
            if ($n > 0) $out[] = $n;
        }

        $out = array_values(array_unique($out));
        return empty($out) ? null : $out;
    }
}