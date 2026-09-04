<?php

namespace App\Services\Erp;

use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StoreSyncService
{
    /**
     * Mapping ESPLICITO: (ditta, site_id) => [domain_config, company_code, site_code, is_b2b]
     */
    private const STORES_MAP = [
        // DITTA 1
        '1:1' => [
            'domain_config' => 'stores.domains.intempodistribution',
            'company_code' => 'INTEMPO',
            'site_code' => 'INTEMPO_B2B',
            'is_b2b' => true,
            'theme' => 'intempodistribution',
        ],
        '1:2' => [
            'domain_config' => 'stores.domains.intemposhop',
            'company_code' => 'INTEMPO',
            'site_code' => 'INTEMPO_B2C',
            'is_b2b' => false,
            'theme' => 'intemposhop',
        ],
        '1:5' => [
            'domain_config' => 'stores.domains.ciak',
            'company_code' => 'INTEMPO',
            'site_code' => 'CIAK',
            'is_b2b' => false,
            'theme' => 'ciak',
        ],
        '1:6' => [
            'domain_config' => 'stores.domains.teknikoshop',
            'company_code' => 'INTEMPO',
            'site_code' => 'TEKNIKO',
            'is_b2b' => false,
            'theme' => 'tekniko',
        ],
        '1:7' => [
            'domain_config' => 'stores.domains.ready',
            'company_code' => 'INTEMPO',
            'site_code' => 'READY',
            'is_b2b' => false,
            'theme' => 'ready',
        ],

        // DITTA 3
        '3:1' => [
            'domain_config' => 'stores.domains.fipell',
            'company_code' => 'FIPELL',
            'site_code' => 'FIPELL_B2B',
            'is_b2b' => true,
            'theme' => 'fipell',
        ],

        // DITTA 5
        '5:1' => [
            'domain_config' => 'stores.domains.diarpell',
            'company_code' => 'DIARPELL',
            'site_code' => 'AZDIARPELL_B2B',
            'is_b2b' => true,
            'theme' => 'diarpell',
        ],

        // DITTA 9
        '9:1' => [
            'domain_config' => 'stores.domains.papiro',
            'company_code' => 'PAPIRO',
            'site_code' => 'PAPIRO_B2B',
            'is_b2b' => true,
            'theme' => 'papiro',
        ],
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

    public function sync(): int
    {
        $this->initErpSession();

        $count = 0;

        try {
            $rows = DB::connection('erp')
                ->table('dbo.SITIWEBB2BEB2C')
                ->select(['DITTA_CG18', 'FLG_B2B_B2C', 'DESCRIZSITO'])
                ->get();

            foreach ($rows as $row) {
                $ditta = (int) ($row->DITTA_CG18 ?? 0);
                $siteId = (int) ($row->FLG_B2B_B2C ?? 0);

                if ($ditta <= 0 || $siteId <= 0) {
                    continue;
                }

                $key = "{$ditta}:{$siteId}";

                // importa SOLO quelli che ti interessano
                if (! isset(self::STORES_MAP[$key])) {
                    continue;
                }

                $m = self::STORES_MAP[$key];

                $domain = (string) config($m['domain_config']);

                $name = trim((string) ($row->DESCRIZSITO ?? ''))
                    ?: $m['site_code'];

                Store::updateOrCreate(
                    [
                        'ditta_cg18' => $ditta,
                        'erp_site_code' => $siteId,
                    ],
                    [
                        'company_code' => $m['company_code'],
                        'site_code' => $m['site_code'],
                        'domain' => $domain,
                        'name' => $name,
                        'is_b2b' => (bool) $m['is_b2b'],
                        'theme' => $m['theme'],
                        'default_locale' => 'it',
                        'supported_locales' => ['it', 'en'],
                        'is_active' => true,
                    ]
                );

                $count++;
            }
        } catch (Throwable $e) {
            Log::error('ERP Store Sync failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $count;
    }
}
