<?php

namespace App\Console\Commands;

use App\Jobs\RunErpSyncCommandJob;
use App\Models\ErpSyncRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class DispatchDailyErpSyncs extends Command
{
    protected $signature = 'erp:dispatch-daily-syncs
        {--since= : Data YYYY-MM-DD. Default ieri}
        {--dry : Dry run}';

    protected $description = 'Accoda la sincronizzazione ERP giornaliera completa';

    private const COMMANDS = [
        'attributes' => ['erp:sync-attributes', []],
        'products' => ['erp:sync-products', ['--ditte' => [1, 3]]],
        'product_comparisons' => ['erp:sync-product-comparisons', ['--ditte' => [1, 3]]],
        'product_attribute_values' => ['erp:sync-product-attribute-values', ['--ditte' => [1, 3]]],
        'group_descriptions' => ['erp:sync-group-descriptions', ['--ditte' => [1, 3]]],
        'customers' => ['erp:sync-customers', ['--ditte' => [1, 3]]],
        'customer_acl' => ['erp:sync-customer-acl', ['--ditte' => [1, 3]]],
        'customer_shipping_addresses' => ['erp:sync-customer-shipping-addresses', ['--ditte' => [1, 3]]],
        'store_visible_groups' => ['erp:sync-store-visible-groups', ['--ditte' => [1, 3]]],
        'store_locator_locations' => ['store-locator:sync', ['--geocode' => true]],
        'media' => ['erp:sync-media', ['--ditte' => [1, 3], '--copy' => true, '--force' => true]],
    ];

    public function handle(): int
    {
        $since = $this->option('since') ?: now('Europe/Rome')->subDay()->toDateString();
        $dry = (bool) $this->option('dry');

        $jobs = [];

        foreach (self::COMMANDS as $key => [$command, $params]) {
            if ($this->supportsSince($command)) {
                $params['--since'] = $since;
            }

            if ($dry && $this->supportsDry($command)) {
                $params['--dry'] = true;
            }

            $run = ErpSyncRun::query()->create([
                'command_key' => $key,
                'command_name' => $command,
                'status' => ErpSyncRun::STATUS_QUEUED,
                'params_json' => $params,
            ]);

            $jobs[] = new RunErpSyncCommandJob($run->id);
        }

        Bus::chain($jobs)->onQueue('erp')->dispatch();

        $this->info('Sync ERP giornaliera accodata: ' . count($jobs) . ' job.');

        return self::SUCCESS;
    }

    private function supportsSince(string $command): bool
    {
        return in_array($command, [
            'erp:sync-products',
            'erp:sync-product-comparisons',
            'erp:sync-product-attribute-values',
            'erp:sync-customers',
            'erp:sync-customer-shipping-addresses',
            'erp:sync-media',
        ], true);
    }

    private function supportsDry(string $command): bool
    {
        return ! in_array($command, [
            'erp:export-orders',
            'store-locator:sync',
        ], true);
    }
}
