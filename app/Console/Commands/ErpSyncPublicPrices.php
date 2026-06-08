<?php

namespace App\Console\Commands;

use App\Services\Erp\PublicPriceSyncService;
use Illuminate\Console\Command;
use Throwable;

class ErpSyncPublicPrices extends Command
{
    protected $signature = 'erp:sync-public-prices
                            {--ditte=* : Filtra per ditte (es: --ditte=1)}
                            {--sku= : Filtra per uno SKU specifico}
                            {--dry : Dry-run: NON scrive su DB}';

    protected $description = 'Sincronizza i prezzi pubblici B2C da ERP LISTARTIC_TOT verso products';

    public function handle(PublicPriceSyncService $service): int
    {
        $ditte = $this->option('ditte') ?: null;
        $sku   = $this->option('sku');
        $dry   = (bool) $this->option('dry');

        $this->info('Starting ERP Public Price Sync...' . ($dry ? ' (DRY RUN)' : ''));
        $this->line('ditte=' . json_encode($ditte) . ' sku=' . json_encode($sku));

        try {
            $stats = $service->sync(
                onlyDitte: $ditte,
                onlySku: $sku,
                dryRun: $dry
            );

            $this->info('ERP Public Price Sync completed successfully.');

            $this->table(
                ['Metric', 'Value'],
                collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->toArray()
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('ERP Public Price Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}