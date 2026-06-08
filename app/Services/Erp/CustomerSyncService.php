<?php

namespace App\Services\Erp;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerSyncService
{
    private const ERP_QUERY_TIMEOUT_SECONDS = 300;
    private const ERP_LINKED_SERVER = '[135.125.255.90,41433]';
    private const DEFAULT_DITTE = [1, 3];
    private const DEFAULT_SINCE_DAYS = 7;

    private static bool $erpSessionInitialized = false;

    public function sync(
        ?array $onlyDitte = null,
        ?string $since = null,
        bool $dryRun = false,
        ?int $limit = null
    ): array {
        $this->initErpSession();

        @set_time_limit(0);

        $ditte = $this->toIntArray($onlyDitte) ?: self::DEFAULT_DITTE;
        $sinceDate = $this->resolveSinceDate($since);
        $runStartedAt = Carbon::now('Europe/Rome');

        $stats = [
            'local_candidates' => 0,
            'erp_queries' => 0,
            'erp_rows_read' => 0,
            'rows_read' => 0,
            'rows_skipped_by_lastchange' => 0,
            'erp_not_found' => 0,
            'upserts' => 0,
            'deactivated' => 0,
            'ditte_processed' => 0,
            'ditte_failed' => 0,
            'since_used' => $sinceDate,
            'limit' => $limit,
            'strategy' => 'erp_openquery_vta01_lastchange_window_bulk_sync',
        ];

        Log::info('ERP Customer Sync start', [
            'ditte' => $ditte,
            'since' => $sinceDate,
            'dry_run' => $dryRun,
            'limit' => $limit,
            'strategy' => $stats['strategy'],
        ]);

        try {
            $this->syncChangedCustomers(
                ditte: $ditte,
                sinceDate: $sinceDate,
                dryRun: $dryRun,
                runStartedAt: $runStartedAt,
                stats: $stats,
                limit: $limit
            );
        } catch (Throwable $e) {
            $stats['ditte_failed'] = count($ditte);

            Log::error('ERP Customer Sync failed', [
                'ditte' => $ditte,
                'since' => $sinceDate,
                'message' => $e->getMessage(),
            ]);

            DB::connection('erp')->disconnect();
            self::$erpSessionInitialized = false;
            $this->initErpSession();
        }

        Log::info('ERP Customer Sync completed', $stats);

        return $stats;
    }

    private function syncChangedCustomers(
        array $ditte,
        string $sinceDate,
        bool $dryRun,
        Carbon $runStartedAt,
        array &$stats,
        ?int $limit
    ): void {
        Log::info('ERP Customer Sync changed customers start', [
            'ditte' => $ditte,
            'since' => $sinceDate,
            'limit' => $limit,
            'strategy' => $stats['strategy'],
        ]);

        $keys = $this->fetchChangedCustomerKeysFromErp($ditte, $sinceDate, $limit);

        $stats['erp_queries']++;
        $stats['local_candidates'] += count($keys);
        $stats['ditte_processed'] = count($ditte);

        Log::info('ERP Customer Sync changed keys completed', [
            'ditte' => $ditte,
            'since' => $sinceDate,
            'keys' => count($keys),
        ]);

        foreach ($keys as $key) {
            if ($limit !== null && $stats['rows_read'] >= $limit) {
                break;
            }

            $ditta = (int) ($key->DITTA_CG18 ?? 0);
            $clifor = (int) ($key->CLIFOR_CG44 ?? 0);

            if ($ditta <= 0 || $clifor <= 0) {
                continue;
            }

            try {
                Log::info('ERP Customer Sync detail lookup start', [
                    'ditta' => $ditta,
                    'clifor' => $clifor,
                ]);

                $row = $this->fetchExactCustomerFromRemoteErp($ditta, $clifor);

                $stats['erp_queries']++;
            } catch (Throwable $e) {
                Log::error('ERP Customer Sync detail lookup failed', [
                    'ditta' => $ditta,
                    'clifor' => $clifor,
                    'message' => $e->getMessage(),
                ]);

                DB::connection('erp')->disconnect();
                self::$erpSessionInitialized = false;
                $this->initErpSession();

                continue;
            }

            if (!$row) {
                $stats['erp_not_found']++;
                continue;
            }

            $stats['erp_rows_read']++;

            $lastChange = $this->toDate($row->LASTCHANGE ?? null);

            if (!$lastChange || $lastChange < $sinceDate) {
                $stats['rows_skipped_by_lastchange']++;
                continue;
            }

            $this->syncRow($row, $dryRun, $runStartedAt, $stats);
        }

        Log::info('ERP Customer Sync changed customers completed', [
            'ditte' => $ditte,
            'stats' => $stats,
        ]);
    }

    private function fetchChangedCustomerKeysFromErp(array $ditte, string $sinceDate, ?int $limit): array
    {
        $ditte = $this->toIntArray($ditte) ?: self::DEFAULT_DITTE;
        $ditteSql = implode(', ', $ditte);
        $top = $limit !== null ? 'TOP ' . max(1, (int) $limit) . ' ' : '';

        $fromDate = Carbon::parse($sinceDate, 'Europe/Rome')->startOfDay();
        $toDate = Carbon::now('Europe/Rome')->addDay()->startOfDay();

        $fromLiteral = $fromDate->format('Y-m-d H:i:s');
        $toLiteral = $toDate->format('Y-m-d H:i:s');

        $remoteSql = "
            SELECT {$top}
                VTA01_DITTA_CG18 AS DITTA_CG18,
                VTA01_CLIFOR_CG44 AS CLIFOR_CG44,
                VTA01_LASTCHANGE_RIEP AS LASTCHANGE
            FROM WEBGAMMA.dbo.VTA01_ANAGRCLI_TOT
            WHERE VTA01_DITTA_CG18 IN ({$ditteSql})
              AND VTA01_LASTCHANGE_RIEP >= {ts '{$fromLiteral}'}
              AND VTA01_LASTCHANGE_RIEP < {ts '{$toLiteral}'}
            ORDER BY VTA01_DITTA_CG18, VTA01_LASTCHANGE_RIEP ASC
        ";

        $sql = "SELECT * FROM OPENQUERY(" . self::ERP_LINKED_SERVER . ", '" . $this->escapeOpenQuerySql($remoteSql) . "')";

        return DB::connection('erp')->select($sql);
    }

    private function fetchExactCustomerFromRemoteErp(int $ditta, int $clifor): ?object
    {
        $ditta = (int) $ditta;
        $clifor = (int) $clifor;

        $remoteSql = "
            SELECT TOP 1
                VTA01_DITTA_CG18 AS DITTA_CG18,
                VTA01_TIPOCF_CG44 AS TIPOCF_CG44,
                VTA01_CLIFOR_CG44 AS CLIFOR_CG44,
                VTA01_CODICE_CG16 AS CODICE_CG16,
                VTA01_RAGSOANAG_CG16 AS RAGSOANAG_CG16,
                VTA01_PARTIVA_CG16 AS PARTIVA_CG16,
                VTA01_CODFISCALE_CG16 AS CODFISCALE_CG16,
                VTA01_COGNOMECONNWEB AS COGNOMECONNWEB,
                VTA01_NOMECONNWEB AS NOMECONNWEB,
                VTA01_INDEMAIL_CG16 AS INDEMAIL_CG16,
                VTA01_INDEMAILPERFATT_CG16 AS INDEMAILPERFATT_CG16,
                VTA01_TEL1NUM_CG16 AS TEL1NUM_CG16,
                VTA01_TEL2NUM_CG16 AS TEL2NUM_CG16,
                VTA01_FAXNUM_CG16 AS FAXNUM_CG16,
                VTA01_CELLNUM_CG16 AS CELLNUM_CG16,
                VTA01_INDWEB_CG16 AS INDWEB_CG16,
                VTA01_EMAIL_PEC_CG16 AS EMAIL_PEC_CG16,
                VTA01_INDIRIZZO_CG16 AS INDIRIZZO_CG16,
                VTA01_CAP_CG16 AS CAP_CG16,
                VTA01_CITTA_CG16 AS CITTA_CG16,
                VTA01_PROV_CG16 AS PROV_CG16,
                VTA01_RAGSOCOR_CG16 AS RAGSOCOR_CG16,
                VTA01_INDIRCOR_CG16 AS INDIRCOR_CG16,
                VTA01_CAPCOR_CG16 AS CAPCOR_CG16,
                VTA01_CITTACOR_CG16 AS CITTACOR_CG16,
                VTA01_PROVCOR_CG16 AS PROVCOR_CG16,
                VTA01_CODPAG_CG62 AS CODPAG_CG62,
                VTA01_DESCRIZPAG_CG62 AS DESCRIZPAG_CG62,
                VTA01_AGENTE_MG17 AS AGENTE_MG17,
                VTA01_RAGSOANAG_VWEBDCG44 AS RAGSOANAG_VWEBDCG44,
                VTA01_INDEMAIL_VWEBDCG44 AS INDEMAIL_VWEBDCG44,
                VTA01_CODICE_CG28 AS CODICE_CG28,
                VTA01_DESCR_CG28 AS DESCR_CG28,
                VTA01_PERCIVA_CG28 AS PERCIVA_CG28,
                VTA01_CODLISTINODED AS CODLISTINODED,
                VTA01_CODRIFALF_MG19 AS CODRIFALF_MG19,
                VTA01_CCABI_MG35 AS CCABI_MG35,
                VTA01_CCCAB_MG35 AS CCCAB_MG35,
                VTA01_DESBANCA_CG12_CG13 AS DESBANCA_CG12_CG13,
                VTA01_IBAN_MG35 AS IBAN_MG35,
                VTA01_FILTROESTR AS FILTROESTR,
                VTA01_LASTCHANGE_RIEP AS LASTCHANGE
            FROM WEBGAMMA.dbo.VTA01_ANAGRCLI_TOT
            WHERE VTA01_DITTA_CG18 = {$ditta}
              AND VTA01_CLIFOR_CG44 = {$clifor}
        ";

        $sql = "SELECT * FROM OPENQUERY(" . self::ERP_LINKED_SERVER . ", '" . $this->escapeOpenQuerySql($remoteSql) . "')";
        $rows = DB::connection('erp')->select($sql);

        return $rows[0] ?? null;
    }
    private function syncRow(object $row, bool $dryRun, Carbon $runStartedAt, array &$stats): void
    {
        $ditta = (int) ($row->DITTA_CG18 ?? 0);
        $tipoCf = (int) ($row->TIPOCF_CG44 ?? 0);
        $clifor = (int) ($row->CLIFOR_CG44 ?? 0);

        if ($ditta <= 0 || $clifor <= 0) {
            return;
        }

        $stats['rows_read']++;

        $isPt = strtoupper(trim((string) ($row->CODRIFALF_MG19 ?? ''))) === 'PT';

        if ($dryRun) {
            if (!$isPt) {
                $stats['deactivated']++;
            }

            $stats['upserts']++;

            Log::info('ERP Customer Sync dry row matched', [
                'ditta' => $ditta,
                'tipocf' => $tipoCf,
                'clifor' => $clifor,
                'ragione_sociale' => $this->trimOrNull($row->RAGSOANAG_CG16 ?? null),
                'lastchange' => $this->toDate($row->LASTCHANGE ?? null),
                'codrifalf_mg19' => $this->trimOrNull($row->CODRIFALF_MG19 ?? null),
                'action' => $isPt ? 'update_active' : 'update_inactive',
            ]);

            return;
        }

        Customer::updateOrCreate(
            [
                'ditta_cg18' => $ditta,
                'tipocf_cg44' => $tipoCf,
                'clifor_cg44' => $clifor,
            ],
            [
                'codice_cg16' => $this->toInt($row->CODICE_CG16 ?? null),
                'ragsoanag_cg16' => $this->trimOrNull($row->RAGSOANAG_CG16 ?? null),
                'partiva_cg16' => $this->trimOrNull($row->PARTIVA_CG16 ?? null),
                'codfiscale_cg16' => $this->trimOrNull($row->CODFISCALE_CG16 ?? null),
                'cognomeconnweb' => $this->trimOrNull($row->COGNOMECONNWEB ?? null),
                'nomeconnweb' => $this->trimOrNull($row->NOMECONNWEB ?? null),
                'indemail_cg16' => $this->trimOrNull($row->INDEMAIL_CG16 ?? null),
                'indemailperfatt_cg16' => $this->trimOrNull($row->INDEMAILPERFATT_CG16 ?? null),
                'tel1num_cg16' => $this->trimOrNull($row->TEL1NUM_CG16 ?? null),
                'tel2num_cg16' => $this->trimOrNull($row->TEL2NUM_CG16 ?? null),
                'faxnum_cg16' => $this->trimOrNull($row->FAXNUM_CG16 ?? null),
                'cellnum_cg16' => $this->trimOrNull($row->CELLNUM_CG16 ?? null),
                'indweb_cg16' => $this->trimOrNull($row->INDWEB_CG16 ?? null),
                'email_pec_cg16' => $this->trimOrNull($row->EMAIL_PEC_CG16 ?? null),
                'indirizzo_cg16' => $this->trimOrNull($row->INDIRIZZO_CG16 ?? null),
                'cap_cg16' => $this->trimOrNull($row->CAP_CG16 ?? null),
                'citta_cg16' => $this->trimOrNull($row->CITTA_CG16 ?? null),
                'prov_cg16' => $this->trimOrNull($row->PROV_CG16 ?? null),
                'ragsocor_cg16' => $this->trimOrNull($row->RAGSOCOR_CG16 ?? null),
                'indircor_cg16' => $this->trimOrNull($row->INDIRCOR_CG16 ?? null),
                'capcor_cg16' => $this->trimOrNull($row->CAPCOR_CG16 ?? null),
                'cittacor_cg16' => $this->trimOrNull($row->CITTACOR_CG16 ?? null),
                'provcor_cg16' => $this->trimOrNull($row->PROVCOR_CG16 ?? null),
                'codpag_cg62' => $this->trimOrNull($row->CODPAG_CG62 ?? null),
                'descrizpag_cg62' => $this->trimOrNull($row->DESCRIZPAG_CG62 ?? null),
                'agente_mg17' => $this->trimOrNull($row->AGENTE_MG17 ?? null),
                'ragsoanag_vwebdcg44' => $this->trimOrNull($row->RAGSOANAG_VWEBDCG44 ?? null),
                'indeemail_vwebdcg44' => $this->trimOrNull($row->INDEMAIL_VWEBDCG44 ?? null),
                'codice_cg28' => $this->trimOrNull($row->CODICE_CG28 ?? null),
                'descr_cg28' => $this->trimOrNull($row->DESCR_CG28 ?? null),
                'perciva_cg28' => $this->toFloat($row->PERCIVA_CG28 ?? null),
                'codlistinoded' => $this->toInt($row->CODLISTINODED ?? null),
                'codrifalf_mg19' => $this->trimOrNull($row->CODRIFALF_MG19 ?? null),
                'ccabi_mg35' => $this->toInt($row->CCABI_MG35 ?? null),
                'cccab_mg35' => $this->toInt($row->CCCAB_MG35 ?? null),
                'desbanca_cg12_cg13' => $this->trimOrNull($row->DESBANCA_CG12_CG13 ?? null),
                'iban_mg35' => $this->trimOrNull($row->IBAN_MG35 ?? null),
                'filtroestr' => $this->toInt($row->FILTROESTR ?? null),
                'erp_lastchange' => $this->toDate($row->LASTCHANGE ?? null),
                'erp_last_seen_at' => $runStartedAt,
                'is_active' => $isPt,
            ]
        );

        $stats['upserts']++;

        if (!$isPt) {
            $stats['deactivated']++;
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

        try {
            $pdo = $conn->getPdo();

            if (defined('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')) {
                $pdo->setAttribute(\PDO::SQLSRV_ATTR_QUERY_TIMEOUT, self::ERP_QUERY_TIMEOUT_SECONDS);
            }

            if (defined('PDO::ATTR_TIMEOUT')) {
                $pdo->setAttribute(\PDO::ATTR_TIMEOUT, self::ERP_QUERY_TIMEOUT_SECONDS);
            }
        } catch (Throwable $e) {
            Log::warning('Unable to set ERP PDO timeout attributes', [
                'message' => $e->getMessage(),
            ]);
        }

        self::$erpSessionInitialized = true;
    }

    private function resolveSinceDate(?string $since): string
    {
        return $this->normalizeSinceDate($since)
            ?: Carbon::now('Europe/Rome')->subDays(self::DEFAULT_SINCE_DAYS)->toDateString();
    }

    private function normalizeSinceDate(?string $since): ?string
    {
        $s = trim((string) $since);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
    }

    private function trimOrNull($v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s === '' ? null : $s;
    }

    private function toInt($v): ?int
    {
        return $v === null || $v === '' ? null : (int) $v;
    }

    private function toFloat($v): ?float
    {
        if ($v === null) {
            return null;
        }

        $s = str_replace(',', '.', (string) $v);

        return is_numeric($s) ? (float) $s : null;
    }

    private function toDate($v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $s) ? substr($s, 0, 10) : null;
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

    private function escapeOpenQuerySql(string $sql): string
    {
        return str_replace("'", "''", $sql);
    }
}