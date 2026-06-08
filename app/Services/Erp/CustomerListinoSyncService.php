<?php

namespace App\Services\Erp;

use App\Models\CustomerListinoAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerListinoSyncService
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
     * Sync ERP customer → listino assignments from dbo.LISTINO_ASSOCCLI
     *
     * @return array{rows_read:int,upserts:int,deactivated:int}
     */
    public function sync(
        ?array $onlyDitte = null,
        ?string $since = null,
        bool $dryRun = false
    ): array {
        $this->initErpSession();

        $stats = [
            'rows_read' => 0,
            'upserts' => 0,
            'deactivated' => 0,
        ];

        $onlyDitte = $this->toIntArray($onlyDitte);
        $sinceDate = $this->normalizeSinceDate($since);

        $runStartedAt = Carbon::now();

        try {

            $q = DB::connection('erp')
                ->table('dbo.LISTINO_ASSOCCLI')
                ->select([
                    'DITTA_CG18_XX74',
                    'CLIFOR_CG44_XX74',
                    'IDLISTINO_XX73_XX74',
                ]);

            if (!empty($onlyDitte)) {
                $q->whereIn('DITTA_CG18_XX74', $onlyDitte);
            }

            $payload = [];

            foreach ($q->cursor() as $row) {

                $stats['rows_read']++;

                $ditta = (int)($row->DITTA_CG18_XX74 ?? 0);
                $clifor = (int)($row->CLIFOR_CG44_XX74 ?? 0);
                $listino = (int)($row->IDLISTINO_XX73_XX74 ?? 0);

                if ($ditta <= 0 || $clifor <= 0 || $listino <= 0) {
                    continue;
                }

                $stats['upserts']++;

                if ($dryRun) {
                    continue;
                }

                $payload[] = [
                    'ditta_cg18' => $ditta,
                    'clifor_cg44' => $clifor,
                    'listino_id' => $listino,
                    'is_active' => true,
                    'erp_last_seen_at' => $runStartedAt,
                    'created_at' => $runStartedAt,
                    'updated_at' => $runStartedAt,
                ];

                if (count($payload) >= self::CHUNK_SIZE) {
                    $this->flushUpsert($payload);
                    $payload = [];
                }
            }

            if (!$dryRun && !empty($payload)) {
                $this->flushUpsert($payload);
            }

            /**
             * Deactivate rows not seen in this run (full sync only)
             */
            if (!$dryRun && !$sinceDate) {
                $deactivated = CustomerListinoAssignment::query()
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

            Log::error('ERP Customer Listino Sync failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Bulk upsert
     */
    private function flushUpsert(array $payload): void
    {
        CustomerListinoAssignment::query()->upsert(
            $payload,
            ['ditta_cg18', 'clifor_cg44', 'listino_id'],
            [
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

        return preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $s)
            ? $s
            : null;
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
