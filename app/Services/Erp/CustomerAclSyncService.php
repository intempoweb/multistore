<?php

namespace App\Services\Erp;

use App\Models\CustomerVisibleGroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerAclSyncService
{
    /**
     * Quante righe per batch in upsert su MySQL.
     * (Se vuoi renderlo configurabile: env('ERP_SYNC_CHUNK', 500))
     */
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
     * Sync from ERP table dbo.GRUPPIFISCLIFOR_XX32
     *
     * IMPORTANT:
     * - We sync ALL rows read from ERP, but compute is_active = (FLGATTIVO_XX32 == 1)
     * - Full sync (no --since): rows not seen in this run -> set is_active=false (within filtered scope)
     * - Delta sync (--since): we DO NOT deactivate (because we are not reading the full dataset)
     *
     * NOTE (performance):
     * - Stream rows from SQL Server (cursor) to avoid loading all in memory.
     * - NO ORDER BY on ERP to avoid heavy sorts/timeouts.
     * - Bulk upsert on MySQL in CHUNK_SIZE batches.
     *
     * @return array{rows_read:int, upserts:int, deactivated:int}
     */
    public function sync(
        ?array $onlyDitte = null,
        ?string $since = null,
        bool $dryRun = false
    ): array {
        $this->initErpSession();

        $stats = [
            'rows_read'   => 0,
            'upserts'     => 0,
            'deactivated' => 0,
        ];

        $onlyDitte = $this->toIntArray($onlyDitte);
        $sinceDate = $this->normalizeSinceDate($since);

        // un solo "now" per coerenza timestamps
        $runStartedAt = Carbon::now();

        try {
            $q = DB::connection('erp')
                ->table('dbo.GRUPPIFISCLIFOR_XX32')
                ->select([
                    'DITTA_CG18',
                    'FLG_B2B_B2C_WEBT81',
                    'TIPOCF_CG44',
                    'CLIFOR_CG44',
                    'CODICE_XX32',
                    'DESCRIZIONE_XX32',
                    'FLGATTIVO_XX32',
                    'DATAULTIMOAGG_XX32',
                ]);

            if (!empty($onlyDitte)) {
                $q->whereIn('DITTA_CG18', $onlyDitte);
            }

            // DATAULTIMOAGG_XX32 is char(10) nullable
            // Se since presente: prendiamo le righe con NULL (sconosciuto) O >= since
            if ($sinceDate) {
                $q->where(function ($sub) use ($sinceDate) {
                    $sub->whereNull('DATAULTIMOAGG_XX32')
                        ->orWhere('DATAULTIMOAGG_XX32', '>=', $sinceDate);
                });
            }

            $payload = [];

            foreach ($q->cursor() as $row) {
                $stats['rows_read']++;

                $ditta  = (int)($row->DITTA_CG18 ?? 0);
                $tipo   = (int)($row->TIPOCF_CG44 ?? 0);
                $clifor = (int)($row->CLIFOR_CG44 ?? 0);

                // varchar(1)
                $flagSite = $this->trimOrNull($row->FLG_B2B_B2C_WEBT81 ?? null);

                // char(25) / char(60) => trim fondamentale
                $code = $this->trimOrNull($row->CODICE_XX32 ?? null);
                $desc = $this->trimOrNull($row->DESCRIZIONE_XX32 ?? null);

                $flgAttivo = $this->toInt($row->FLGATTIVO_XX32 ?? null) ?? 0;
                $isActive  = ($flgAttivo === 1);

                // char(10) -> YYYY-MM-DD (nullable)
                $dataUltAgg = $this->toDate($row->DATAULTIMOAGG_XX32 ?? null);

                // Dati minimi per chiave
                if ($ditta <= 0 || $clifor <= 0 || $tipo < 0 || !$flagSite || !$code) {
                    continue;
                }

                $stats['upserts']++;

                if ($dryRun) {
                    continue;
                }

                $payload[] = [
                    // key
                    'ditta_cg18'         => $ditta,
                    'flg_b2b_b2c_webt81' => $flagSite,
                    'tipocf_cg44'        => $tipo,
                    'clifor_cg44'        => $clifor,
                    'codice_xx32'        => $code,

                    // data
                    'descrizione_xx32'   => $desc,
                    'flgattivo_xx32'     => $flgAttivo,
                    'dataultimoagg_xx32' => $dataUltAgg,
                    'is_active'          => $isActive,
                    'erp_last_seen_at'   => $runStartedAt,

                    'created_at'         => $runStartedAt,
                    'updated_at'         => $runStartedAt,
                ];

                if (count($payload) >= self::CHUNK_SIZE) {
                    $this->flushUpsert($payload);
                    $payload = [];
                }
            }

            // Flush last batch
            if (!$dryRun && !empty($payload)) {
                $this->flushUpsert($payload);
            }

            /**
             * Deactivate:
             * - SOLO in full sync (since assente), altrimenti disattiveresti righe non lette nel delta.
             */
            if (!$dryRun && !$sinceDate) {
                $deactivated = CustomerVisibleGroup::query()
                    ->when(!empty($onlyDitte), fn ($qq) => $qq->whereIn('ditta_cg18', $onlyDitte))
                    ->where('is_active', true)
                    ->where(function ($qq) use ($runStartedAt) {
                        $qq->whereNull('erp_last_seen_at')
                           ->orWhere('erp_last_seen_at', '<', $runStartedAt);
                    })
                    ->update(['is_active' => false]);

                $stats['deactivated'] = $deactivated;
            }

            return $stats;
        } catch (Throwable $e) {
            Log::error('ERP Customer ACL Sync failed', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Upsert bulk su MySQL.
     *
     * @param array<int,array<string,mixed>> $payload
     */
    private function flushUpsert(array $payload): void
    {
        CustomerVisibleGroup::query()->upsert(
            $payload,
            ['ditta_cg18', 'flg_b2b_b2c_webt81', 'tipocf_cg44', 'clifor_cg44', 'codice_xx32'],
            [
                'descrizione_xx32',
                'flgattivo_xx32',
                'dataultimoagg_xx32',
                'is_active',
                'erp_last_seen_at',
                'updated_at',
            ]
        );
    }

    private function normalizeSinceDate(?string $since): ?string
    {
        if (!$since) return null;
        $s = trim($since);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
    }

    private function trimOrNull($v): ?string
    {
        $s = trim((string)($v ?? ''));
        return $s === '' ? null : $s;
    }

    private function toInt($v): ?int
    {
        if ($v === null) return null;
        $s = trim((string)$v);
        if ($s === '') return null;
        return (int)$s;
    }

    /**
     * Accetta:
     * - "YYYY-MM-DD"
     * - "YYYY-MM-DD ...."
     */
    private function toDate($v): ?string
    {
        $s = trim((string)($v ?? ''));
        if ($s === '') return null;

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return substr($s, 0, 10);

        return null;
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