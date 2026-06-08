<?php

namespace App\Console\Commands;

use App\Services\Erp\CustomerListinoSyncService;
use Illuminate\Console\Command;
use Throwable;

class ErpSyncCustomerListini extends Command
{
    /**
     * Artisan command signature.
     */
    protected $signature = 'erp:sync-customer-listini
        {--ditte=* : Limit sync to specific ERP ditte}
        {--since= : Sync only rows changed since YYYY-MM-DD}
        {--dry : Dry run (no DB writes)}';

    /**
     * Command description.
     */
    protected $description = 'Sync ERP customer → listino assignments from LISTINO_ASSOCCLI view';

    /**
     * Execute the console command.
     */
    public function handle(CustomerListinoSyncService $service): int
    {
        $onlyDitte = $this->option('ditte');
        $since     = $this->option('since');
        $dryRun    = (bool) $this->option('dry');

        $this->info('Starting ERP Customer Listini sync...');

        try {
            $stats = $service->sync(
                $onlyDitte ?: null,
                $since ?: null,
                $dryRun
            );

            $this->line('');
            $this->info('ERP Customer Listini Sync completed');
            $this->line('---------------------------------------');
            $this->line('Rows read:      ' . ($stats['rows_read'] ?? 0));
            $this->line('Upserts:        ' . ($stats['upserts'] ?? 0));
            $this->line('Deactivated:    ' . ($stats['deactivated'] ?? 0));

            if ($dryRun) {
                $this->warn('Dry run enabled: no database changes were written.');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('ERP Customer Listini Sync failed');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
