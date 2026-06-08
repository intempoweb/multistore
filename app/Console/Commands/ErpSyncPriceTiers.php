<?php

namespace App\Console\Commands;

use App\Services\Erp\PriceTierSyncService;
use Illuminate\Console\Command;
use Throwable;

class ErpSyncPriceTiers extends Command
{
    protected $signature = 'erp:sync-price-tiers
                            {--ditte=* : Filter by one or more DITTA_CG18 values}
                            {--listini=* : Sync only these ERP LISTINO IDs}
                            {--sku= : Sync only one SKU/CODART}
                            {--since= : Sync only rows with DATAULTAGG_XX73 >= YYYY-MM-DD}
                            {--dry : Dry run (no database writes)}';

    protected $description = 'Sync B2B price tiers from ERP LISTINOCLI_RAGG into price_tiers';

    public function handle(): int
    {
        $this->info('Starting ERP B2B Price Tier Sync...');

        $onlyDitte   = (array) $this->option('ditte');
        $onlyListini = (array) $this->option('listini');
        $onlySku     = $this->option('sku');
        $since       = $this->option('since');
        $dryRun      = (bool) $this->option('dry');

        try {
            /** @var PriceTierSyncService $service */
            $service = app(PriceTierSyncService::class);

            $stats = $service->sync(
                onlyDitte: $onlyDitte,
                onlyListini: $onlyListini,
                onlySku: $onlySku,
                since: $since,
                dryRun: $dryRun
            );

            $this->info('ERP B2B Price Tier Sync completed successfully.');

            $this->table(
                ['Metric', 'Value'],
                collect($stats)->map(fn ($value, $metric) => [$metric, $value])->values()->toArray()
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('ERP B2B Price Tier Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}