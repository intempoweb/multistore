<?php

namespace App\Console\Commands;

use App\Services\Storefront\StoreLocator\StoreLocatorSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncStoreLocatorLocations extends Command
{
    protected $signature = 'store-locator:sync
                            {--store= : ID store B2C da sincronizzare}
                            {--geocode : Geocodifica le location nuove o con indirizzo cambiato}
                            {--limit= : Limite clienti per store, utile per test}';

    protected $description = 'Sincronizza le location tecniche dello store locator dai clienti B2B e dalle destinazioni ERP';

    public function handle(): int
    {
        $store = $this->option('store');
        $limit = $this->option('limit');

        try {
            $stats = app(StoreLocatorSyncService::class)->sync(
                storeId: $store !== null && $store !== '' ? (int) $store : null,
                geocode: (bool) $this->option('geocode'),
                limit: $limit !== null && $limit !== '' ? (int) $limit : null,
            );

            $this->info('Store locator sync completata.');
            $this->table(
                ['Metric', 'Value'],
                collect($stats)->map(fn ($value, $key) => [$key, $value])->values()->toArray()
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Store locator sync fallita: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
