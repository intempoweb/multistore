<?php

namespace App\Console\Commands;

use App\Services\Erp\CustomerSyncService;
use Illuminate\Console\Command;
use Throwable;

class ErpSyncCustomers extends Command
{
    protected $signature = 'erp:sync-customers
                            {--ditte=* : Filtra una o più ditte ERP, es. --ditte=1 --ditte=3}
                            {--since= : Sincronizza clienti modificati da YYYY-MM-DD}
                            {--limit= : Limita le righe lette da ERP dopo filtro LASTCHANGE}
                            {--dry : Dry run, non scrive nel database locale}';

    protected $description = 'Sync clienti da ERP view ANAGRCLI_TOT usando LASTCHANGE';

    public function handle(): int
    {
        $this->info('Starting ERP Customer Sync...');

        $ditte = $this->option('ditte');
        $since = $this->option('since');
        $limitOption = $this->option('limit');
        $dryRun = (bool) $this->option('dry');

        $limit = null;

        if ($limitOption !== null && $limitOption !== '') {
            $limit = (int) $limitOption;

            if ($limit <= 0) {
                $this->error('Il parametro --limit deve essere maggiore di zero.');

                return self::FAILURE;
            }
        }

        if ($since !== null && $since !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $since)) {
            $this->error('Il parametro --since deve essere nel formato YYYY-MM-DD.');

            return self::FAILURE;
        }

        try {
            /** @var CustomerSyncService $service */
            $service = app(CustomerSyncService::class);

            $stats = $service->sync(
                onlyDitte: is_array($ditte) ? $ditte : null,
                since: $since !== null && $since !== '' ? (string) $since : null,
                dryRun: $dryRun,
                limit: $limit
            );

            $this->table(
                ['Metric', 'Value'],
                collect($stats)
                    ->map(fn ($value, $key) => [$key, is_array($value) ? json_encode($value) : $value])
                    ->values()
                    ->toArray()
            );

            if (($stats['ditte_failed'] ?? 0) > 0) {
                $this->error('ERP Customer Sync completed with errors.');

                return self::FAILURE;
            }

            $this->info('ERP Customer Sync completed successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('ERP Customer Sync failed: ' . $e->getMessage());

            report($e);

            return self::FAILURE;
        }
    }
}