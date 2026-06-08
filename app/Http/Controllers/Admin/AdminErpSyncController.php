<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunErpSyncCommandJob;
use App\Models\ErpSyncRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class AdminErpSyncController extends Controller
{
    private const COMMANDS = [
        'stores' => 'erp:sync-stores',
        'attributes' => 'erp:sync-attributes',
        'products' => 'erp:sync-products',
        'product_comparisons' => 'erp:sync-product-comparisons',
        'product_attribute_values' => 'erp:sync-product-attribute-values',
        'group_descriptions' => 'erp:sync-group-descriptions',
        'customers' => 'erp:sync-customers',
        'customer_acl' => 'erp:sync-customer-acl',
        'customer_shipping_addresses' => 'erp:sync-customer-shipping-addresses',
        'customer_listini' => 'erp:sync-customer-listini',
        'store_visible_groups' => 'erp:sync-store-visible-groups',
        'public_prices' => 'erp:sync-public-prices',
        'price_tiers' => 'erp:sync-price-tiers',
        'stock' => 'erp:sync-stock',
        'media' => 'erp:sync-media',
        'export_orders' => 'erp:export-orders',
    ];

    private const QUEUED_COMMANDS = [
        'erp:sync-stores',
        'erp:sync-attributes',
        'erp:sync-products',
        'erp:sync-product-comparisons',
        'erp:sync-product-attribute-values',
        'erp:sync-group-descriptions',
        'erp:sync-customers',
        'erp:sync-customer-acl',
        'erp:sync-customer-shipping-addresses',
        'erp:sync-customer-listini',
        'erp:sync-store-visible-groups',
        'erp:sync-public-prices',
        'erp:sync-price-tiers',
        'erp:sync-stock',
        'erp:sync-media',
        'erp:export-orders',
    ];

    public function index(): View
    {
        $this->cleanupZombieRuns();

        return view('admin.erp-sync.index', [
            'commands' => self::COMMANDS,
            'result' => session('erp_sync_result'),
            'recentRuns' => ErpSyncRun::query()
                ->latest('id')
                ->limit(50)
                ->get(),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'command' => ['required', 'string'],
            'ditte' => ['nullable', 'string'],
            'sites' => ['nullable', 'string'],
            'since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1'],
            'sku' => ['nullable', 'string'],
            'listini' => ['nullable', 'string'],
            'dry' => ['nullable', 'boolean'],
            'copy' => ['nullable', 'boolean'],
            'force' => ['nullable', 'boolean'],
            'keep_old' => ['nullable', 'boolean'],
            'order_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $commandKey = (string) $data['command'];

        $command = self::COMMANDS[$commandKey] ?? null;

        abort_unless($command !== null, 404);

        $params = $this->buildCommandParameters($command, $data);

        if ($this->shouldQueueCommand($command)) {
            return $this->dispatchQueuedCommand(
                $commandKey,
                $command,
                $params
            );
        }

        try {
            @set_time_limit(0);

            $run = ErpSyncRun::query()->create([
                'command_key' => $commandKey,
                'command_name' => $command,
                'status' => ErpSyncRun::STATUS_RUNNING,
                'params_json' => $params,
                'started_at' => now(),
            ]);

            $exitCode = Artisan::call($command, $params);

            $output = trim((string) Artisan::output());

            if ($exitCode === 0) {
                $run->markCompleted($output);
            } else {
                $run->markFailed($output ?: 'Command failed.');
            }

            return redirect()
                ->route('admin.erp-sync.index')
                ->with('erp_sync_result', [
                    'status' => $exitCode === 0 ? 'completed' : 'failed',
                    'command' => $command,
                    'params' => $params,
                    'output' => $output,
                    'run_id' => $run->id,
                ]);
        } catch (Throwable $e) {
            Log::error('Admin ERP sync command failed', [
                'command' => $command,
                'params' => $params,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect()
                ->route('admin.erp-sync.index')
                ->withInput()
                ->with('erp_sync_result', [
                    'status' => 'failed',
                    'command' => $command,
                    'params' => $params,
                    'output' => $e->getMessage(),
                ]);
        }
    }

    private function buildCommandParameters(string $command, array $data): array
    {
        $params = [];

        if (!empty($data['ditte']) && $this->commandSupportsOption($command, 'ditte')) {
            $params['--ditte'] = $this->csvToArray($data['ditte']);
        }

        if (!empty($data['sites']) && $this->commandSupportsOption($command, 'sites')) {
            $params['--sites'] = $this->csvToArray($data['sites']);
        }

        if (!empty($data['since']) && $this->commandSupportsOption($command, 'since')) {
            $params['--since'] = (string) $data['since'];
        }

        if (!empty($data['limit']) && $this->commandSupportsOption($command, 'limit')) {
            $params['--limit'] = (int) $data['limit'];
        }

        if (!empty($data['sku']) && $this->commandSupportsOption($command, 'sku')) {
            $params['--sku'] = trim((string) $data['sku']);
        }

        if (!empty($data['listini']) && $this->commandSupportsOption($command, 'listini')) {
            $params['--listini'] = $this->csvToArray($data['listini']);
        }

        if (!empty($data['order_id']) && $command === 'erp:export-orders') {
            $params['--order-id'] = (int) $data['order_id'];
        }

        if (!empty($data['dry']) && $this->commandSupportsOption($command, 'dry')) {
            $params['--dry'] = true;
        }

        if (!empty($data['copy']) && $command === 'erp:sync-media') {
            $params['--copy'] = true;
        }

        if (!empty($data['force']) && $command === 'erp:sync-media') {
            $params['--force'] = true;
        }

        if (!empty($data['keep_old']) && $command === 'erp:sync-product-attribute-values') {
            $params['--keep-old'] = true;
        }

        return $params;
    }

    private function commandSupportsOption(string $command, string $option): bool
    {
        return match ($command) {
            'erp:sync-stores' => in_array($option, ['ditte', 'dry'], true),

            'erp:sync-products' => in_array($option, [
                'ditte',
                'sites',
                'since',
                'limit',
                'dry',
            ], true),
            'erp:sync-product-comparisons' => in_array($option, [
                'ditte',
                'sites',
                'since',
                'limit',
                'dry',
            ], true),

            'erp:sync-attributes' => in_array($option, [
                'dry',
            ], true),

            'erp:sync-product-attribute-values' => in_array($option, [
                'ditte',
                'sites',
                'since',
                'limit',
                'dry',
            ], true),

            'erp:sync-group-descriptions' => in_array($option, [
                'ditte',
                'sites',
                'limit',
                'dry',
            ], true),

            'erp:sync-stock' => in_array($option, [
                'ditte',
                'sites',
                'since',
                'limit',
                'dry',
            ], true),

            'erp:sync-customers' => in_array($option, [
                'ditte',
                'since',
                'limit',
                'dry',
            ], true),

            'erp:sync-customer-acl' => in_array($option, [
                'ditte',
                'dry',
            ], true),

            'erp:sync-customer-shipping-addresses' => in_array($option, [
                'ditte',
                'since',
                'dry',
            ], true),

            'erp:sync-customer-listini' => in_array($option, [
                'ditte',
                'since',
                'dry',
            ], true),

            'erp:sync-store-visible-groups' => in_array($option, [
                'ditte',
                'dry',
            ], true),

            'erp:sync-public-prices' => in_array($option, [
                'ditte',
                'sku',
                'dry',
            ], true),

            'erp:sync-price-tiers' => in_array($option, [
                'ditte',
                'listini',
                'sku',
                'since',
                'dry',
            ], true),

            'erp:sync-media' => in_array($option, [
                'ditte',
                'sites',
                'since',
                'limit',
                'dry',
            ], true),

            'erp:export-orders' => in_array($option, [
                'limit',
            ], true),

            default => false,
        };
    }

    private function shouldQueueCommand(string $command): bool
    {
        return in_array($command, self::QUEUED_COMMANDS, true);
    }

    private function dispatchQueuedCommand(
        string $commandKey,
        string $command,
        array $params
    ): RedirectResponse {
        try {
            $run = ErpSyncRun::query()->create([
                'command_key' => $commandKey,
                'command_name' => $command,
                'status' => ErpSyncRun::STATUS_QUEUED,
                'params_json' => $params,
                'output' => null,
                'error_message' => null,
                'started_at' => null,
                'finished_at' => null,
            ]);

            RunErpSyncCommandJob::dispatch($run->id)
                ->onQueue('erp');

            return redirect()
                ->route('admin.erp-sync.index')
                ->with('erp_sync_result', [
                    'status' => 'queued',
                    'command' => $command,
                    'params' => $params,
                    'output' => 'Job ERP accodato correttamente.',
                    'run_id' => $run->id,
                ]);
        } catch (Throwable $e) {
            Log::error('Admin ERP sync queue dispatch failed', [
                'command' => $command,
                'params' => $params,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.erp-sync.index')
                ->withInput()
                ->with('erp_sync_result', [
                    'status' => 'failed',
                    'command' => $command,
                    'params' => $params,
                    'output' => $e->getMessage(),
                ]);
        }
    }

    private function cleanupZombieRuns(): void
    {
        ErpSyncRun::query()
            ->where('status', 'running')
            ->whereNotNull('started_at')
            ->where('started_at', '<', now()->subHours(2))
            ->update([
                'status' => 'failed',
                'error_message' => 'Job interrotto o worker terminato.',
                'finished_at' => now(),
            ]);
    }

    private function csvToArray(?string $value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $item) => trim($item))
            ->filter(fn (string $item) => $item !== '')
            ->unique()
            ->values()
            ->all();
    }
}