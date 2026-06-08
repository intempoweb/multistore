<?php

namespace App\Console\Commands;

use App\Services\Erp\CustomerAclSyncService;
use Illuminate\Console\Command;
use Throwable;

class ErpSyncCustomerAcl extends Command
{
    protected $signature = 'erp:sync-customer-acl
                            {--ditte=* : Filter by one or more DITTA_CG18 values}
                            {--since= : Sync only rows with DATAULTIMOAGG_XX32 >= YYYY-MM-DD (if present)}
                            {--dry : Dry run (no database writes)}';

    protected $description = 'Sync customer ACL groups from ERP table GRUPPIFISCLIFOR_XX32 into customer_visible_groups';

    public function handle(): int
    {
        $this->info('Starting ERP Customer ACL Sync...');

        $onlyDitte = (array) $this->option('ditte');
        $since     = $this->option('since');
        $dryRun    = (bool) $this->option('dry');

        try {
            /** @var CustomerAclSyncService $service */
            $service = app(CustomerAclSyncService::class);

            $stats = $service->sync(
                onlyDitte: $onlyDitte,
                since: $since,
                dryRun: $dryRun
            );

            $this->info('ERP Customer ACL Sync completed successfully.');

            $this->table(
                ['Metric', 'Value'],
                collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->toArray()
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('ERP Customer ACL Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}