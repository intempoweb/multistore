<?php

namespace App\Console\Commands;

use App\Services\Erp\StoreVisibilitySyncService;
use Illuminate\Console\Command;
use Throwable;

class ErpSyncStoreVisibleGroups extends Command
{
    protected $signature = 'erp:sync-store-visible-groups
                            {--ditte=* : Filter by one or more DITTA_CG18 values}
                            {--sites=* : Filter by one or more CODICESITO values}
                            {--dry : Dry run (no database writes)}';

    protected $description = 'Sync store visible physical groups from ERP table ANAGRAMARCLIVIS into store_visible_groups';

    public function handle(): int
    {
        $this->info('Starting ERP Store Visibility Sync...');

        $onlyDitte = (array) $this->option('ditte');
        $onlySites = (array) $this->option('sites');
        $dryRun    = (bool) $this->option('dry');

        try {
            /** @var StoreVisibilitySyncService $service */
            $service = app(StoreVisibilitySyncService::class);

            $stats = $service->sync(
                onlyDitte: $onlyDitte,
                onlySites: $onlySites,
                dryRun: $dryRun
            );

            $this->info('ERP Store Visibility Sync completed successfully.');

            $this->table(
                ['Metric', 'Value'],
                collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->toArray()
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('ERP Store Visibility Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}