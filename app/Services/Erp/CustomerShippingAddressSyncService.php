<?php

namespace App\Services\Erp;

use App\Models\CustomerShippingAddress;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerShippingAddressSyncService
{
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

    public function sync(
        ?array $onlyDitte = null,
        ?int $clifor = null,
        ?int $tipocf = null,
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
                ->table('dbo.ANAGRALTRADES_TOT as a')
                ->select([
                    'a.DITTA_CG18',
                    'a.TIPOCF_CG44',
                    'a.CLIFOR_CG44',
                    'a.CODDESTIN_MG22',
                    'a.DESTRAGSOC_MG22',
                    'a.DESTIND_MG22',
                    'a.DESTCAP_MG22',
                    'a.DESTCITTA_MG22',
                    'a.DESTPROV_MG22',
                    'a.DESTTEL_MG22',
                    'a.DESTCELL_MG22',
                    'a.DESTEMAIL_MG22',
                    'a.DESTFAX_MG22',
                    'a.DESTNOTE_MG22',
                    'a.ALIQRID_CG28',
                    'a.STATOEST_CG07',
                    'a.VETT1_MG14',
                    'a.LASTCHANGE',
                ]);

            if (!empty($onlyDitte)) {
                $query->whereIn('a.DITTA_CG18', $onlyDitte);
            }

            if ($clifor !== null && $clifor > 0) {
                $query->where('a.CLIFOR_CG44', $clifor);
            }

            if ($tipocf !== null) {
                $query->where('a.TIPOCF_CG44', $tipocf);
            }

            if ($sinceDate) {
                $query->where('a.LASTCHANGE', '>=', $sinceDate);
            }

            $rows = $query
                ->orderBy('a.DITTA_CG18')
                ->orderBy('a.TIPOCF_CG44')
                ->orderBy('a.CLIFOR_CG44')
                ->orderBy('a.CODDESTIN_MG22')
                ->get();

            foreach ($rows as $row) {
                $stats['rows_read']++;

                $ditta = (int) ($row->DITTA_CG18 ?? 0);
                $tipoCf = (int) ($row->TIPOCF_CG44 ?? 0);
                $cliente = (int) ($row->CLIFOR_CG44 ?? 0);
                $codDestin = (int) ($row->CODDESTIN_MG22 ?? 0);

                if ($ditta <= 0 || $cliente <= 0) {
                    continue;
                }

                $data = [
                    'destragsoc_mg22' => $this->trimOrNull($row->DESTRAGSOC_MG22 ?? null),
                    'destind_mg22' => $this->trimOrNull($row->DESTIND_MG22 ?? null),
                    'destcap_mg22' => $this->trimOrNull($row->DESTCAP_MG22 ?? null),
                    'destcitta_mg22' => $this->trimOrNull($row->DESTCITTA_MG22 ?? null),
                    'destprov_mg22' => $this->trimOrNull($row->DESTPROV_MG22 ?? null),
                    'desttel_mg22' => $this->trimOrNull($row->DESTTEL_MG22 ?? null),
                    'destcell_mg22' => $this->trimOrNull($row->DESTCELL_MG22 ?? null),
                    'destemail_mg22' => $this->trimOrNull($row->DESTEMAIL_MG22 ?? null),
                    'destfax_mg22' => $this->trimOrNull($row->DESTFAX_MG22 ?? null),
                    'destnote_mg22' => $this->trimOrNull($row->DESTNOTE_MG22 ?? null),
                    'aliqrid_cg28' => $this->trimOrNull($row->ALIQRID_CG28 ?? null),
                    'statoest_cg07' => $this->toInt($row->STATOEST_CG07 ?? null),
                    'vett1_mg14' => $this->trimOrNull($row->VETT1_MG14 ?? null),
                    'erp_lastchange' => $this->toDate($row->LASTCHANGE ?? null),
                    'erp_last_seen_at' => $runStartedAt,
                    'is_active' => true,
                ];

                if ($dryRun) {
                    $stats['upserts']++;
                    continue;
                }

                CustomerShippingAddress::updateOrCreate(
                    [
                        'ditta_cg18' => $ditta,
                        'tipocf_cg44' => $tipoCf,
                        'clifor_cg44' => $cliente,
                        'coddestin_mg22' => $codDestin,
                    ],
                    $data
                );

                $stats['upserts']++;
            }

            if (!$dryRun && $sinceDate === null) {

    $deactivateQuery = CustomerShippingAddress::query()

        ->when(!empty($onlyDitte), fn ($q) => $q->whereIn('ditta_cg18', $onlyDitte))

        ->when($clifor !== null && $clifor > 0, fn ($q) => $q->where('clifor_cg44', $clifor))

        ->when($tipocf !== null, fn ($q) => $q->where('tipocf_cg44', $tipocf))

        ->where(function ($q) use ($runStartedAt) {

            $q->whereNull('erp_last_seen_at')

                ->orWhere('erp_last_seen_at', '<', $runStartedAt);

        })

        ->where('is_active', true);

    $stats['deactivated'] = $deactivateQuery->update([

        'is_active' => false,

        'updated_at' => now(),

    ]);

}

            return $stats;
        } catch (Throwable $e) {
            Log::error('ERP Customer Shipping Address Sync failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function trimOrNull(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));
        return $s === '' ? null : $s;
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function toDate(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));

        if ($s === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
            return substr($s, 0, 10);
        }

        return null;
    }

    private function normalizeSinceDate(?string $since): ?string
    {
        if (!$since) {
            return null;
        }

        $s = trim($since);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return $s;
        }

        return null;
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