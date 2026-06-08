<?php

namespace App\Console\Commands;

use App\Services\Erp\CustomerShippingAddressSyncService;
use Illuminate\Console\Command;
use Throwable;

class ErpSyncCustomerShippingAddresses extends Command
{
    /**
     * Command signature
     */
    protected $signature = 'erp:sync-customer-shipping-addresses
                            {--ditte=* : Filter by one or more DITTA_CG18 values}
                            {--clifor= : Filter by CLIFOR_CG44}
                            {--tipocf= : Filter by TIPOCF_CG44}
                            {--since= : Sync only rows changed since YYYY-MM-DD}
                            {--dry : Dry run (no database writes)}';

    /**
     * Command description
     */
    protected $description = 'Sync customer shipping addresses from ERP table ANAGRALTRADES_TOT into local customer_shipping_addresses table';

    public function handle(): int
    {
        $this->info('Starting ERP Customer Shipping Addresses sync...');

        $onlyDitte = $this->option('ditte');
        $clifor = $this->option('clifor');
        $tipocf = $this->option('tipocf');
        $since = $this->option('since');
        $dryRun = (bool) $this->option('dry');

        try {
            /** @var CustomerShippingAddressSyncService $service */
            $service = app(CustomerShippingAddressSyncService::class);

            $stats = $service->sync(
                onlyDitte: $onlyDitte,
                clifor: $clifor !== null && $clifor !== '' ? (int) $clifor : null,
                tipocf: $tipocf !== null && $tipocf !== '' ? (int) $tipocf : null,
                since: $since,
                dryRun: $dryRun,
            );

            $this->info('ERP Customer Shipping Addresses sync completed successfully.');

            $this->table(
                ['Metric', 'Value'],
                collect($stats)->map(fn ($value, $key) => [$key, $value])->values()->toArray()
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('ERP Customer Shipping Addresses sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}