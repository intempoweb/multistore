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
        if (self::$erpSessionInitialized) {
            return;
        }

        $conn = DB::connection('erp');
        $conn->statement('SET ANSI_NULLS ON');
        $conn->statement('SET ANSI_WARNINGS ON');

        self::$erpSessionInitialized = true;
    }

    /**
     * Sync ERP customer → listino assignments from dbo.LISTINO_ASSOCCLI.
     *
     * IMPORTANT:
     * - ERP is the source of truth.
     * - For every synced customer, keep active only the listini currently returned by ERP.
     * - Old/stale local associations for the same ditta+clifor are deactivated.
     * - Full sync can also deactivate customers no longer seen in ERP for the selected ditte.
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
            $query = DB::connection('erp')
                ->table('dbo.LISTINO_ASSOCCLI')
                ->select([
                    'DITTA_CG18_XX74',
                    'CLIFOR_CG44_XX74',
                    'IDLISTINO_XX73_XX74',
                ]);

            if (!empty($onlyDitte)) {
                $query->whereIn('DITTA_CG18_XX74', $onlyDitte);
            }

            $payload = [];
            $seenListiniByCustomer = [];
            $seenCustomerKeys = [];

            foreach ($query->cursor() as $row) {
                $stats['rows_read']++;

                $ditta = (int) ($row->DITTA_CG18_XX74 ?? 0);
                $clifor = (int) ($row->CLIFOR_CG44_XX74 ?? 0);
                $listino = (int) ($row->IDLISTINO_XX73_XX74 ?? 0);

                if ($ditta <= 0 || $clifor <= 0 || $listino <= 0) {
                    continue;
                }

                $customerKey = $this->customerKey($ditta, $clifor);
                $seenCustomerKeys[$customerKey] = [
                    'ditta_cg18' => $ditta,
                    'clifor_cg44' => $clifor,
                ];
                $seenListiniByCustomer[$customerKey] ??= [];
                $seenListiniByCustomer[$customerKey][$listino] = $listino;

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

            if (!$dryRun) {
                $stats['deactivated'] += $this->deactivateStaleListiniForSeenCustomers($seenListiniByCustomer);
            }

            /**
             * Full sync only: deactivate customers no longer returned by ERP.
             * Since sync is partial/incremental, it must not deactivate unseen customers.
             */
            if (!$dryRun && !$sinceDate) {
                $stats['deactivated'] += $this->deactivateCustomersNotSeenInFullSync(
                    onlyDitte: $onlyDitte,
                    seenCustomerKeys: $seenCustomerKeys
                );
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
     * @param array<int,array<string,mixed>> $payload
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

    /**
     * @param array<string,array<int,int>> $seenListiniByCustomer
     */
    private function deactivateStaleListiniForSeenCustomers(array $seenListiniByCustomer): int
    {
        $deactivated = 0;

        foreach ($seenListiniByCustomer as $customerKey => $listini) {
            [$ditta, $clifor] = array_map('intval', explode(':', $customerKey, 2));
            $activeListini = array_values(array_unique(array_filter($listini, fn ($value) => (int) $value > 0)));

            if ($ditta <= 0 || $clifor <= 0 || empty($activeListini)) {
                continue;
            }

            $deactivated += CustomerListinoAssignment::query()
                ->where('ditta_cg18', $ditta)
                ->where('clifor_cg44', $clifor)
                ->where('is_active', true)
                ->whereNotIn('listino_id', $activeListini)
                ->update(['is_active' => false]);
        }

        return $deactivated;
    }

    /**
     * @param array<int,int>|null $onlyDitte
     * @param array<string,array{ditta_cg18:int,clifor_cg44:int}> $seenCustomerKeys
     */
    private function deactivateCustomersNotSeenInFullSync(?array $onlyDitte, array $seenCustomerKeys): int
    {
        $query = CustomerListinoAssignment::query()
            ->when(!empty($onlyDitte), fn ($q) => $q->whereIn('ditta_cg18', $onlyDitte))
            ->where('is_active', true);

        if (!empty($seenCustomerKeys)) {
            $query->where(function ($outer) use ($seenCustomerKeys) {
                foreach ($seenCustomerKeys as $seen) {
                    $outer->where(function ($sub) use ($seen) {
                        $sub->where('ditta_cg18', '<>', $seen['ditta_cg18'])
                            ->orWhere('clifor_cg44', '<>', $seen['clifor_cg44']);
                    });
                }
            });
        }

        return $query->update(['is_active' => false]);
    }

    private function normalizeSinceDate(?string $since): ?string
    {
        if (!$since) {
            return null;
        }

        $since = trim($since);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $since) ? $since : null;
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

    private function customerKey(int $ditta, int $clifor): string
    {
        return $ditta . ':' . $clifor;
    }
}
