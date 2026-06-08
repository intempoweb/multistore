<?php

namespace App\Console\Commands;

use App\Services\Erp\StoreSyncService;
use Illuminate\Console\Command;
use Throwable;

class ErpSyncStores extends Command
{
    protected $signature = 'erp:sync-stores';
    protected $description = 'Sincronizza gli store da ERP';

    public function handle(StoreSyncService $service): int
    {
        $this->info('Sync stores from ERP...');

        try {
            $count = $service->sync();
            $this->info("Sincronizzati {$count} store.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('ERP sync fallito: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}