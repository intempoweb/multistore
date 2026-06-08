<?php

namespace App\Jobs;

use App\Models\ErpSyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunErpCustomerSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 7200;
    public int $tries = 1;

    public function __construct(public int $erpSyncRunId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $run = ErpSyncRun::query()->find($this->erpSyncRunId);

        if (!$run instanceof ErpSyncRun) {
            Log::warning('ERP sync run not found for queued job', [
                'erp_sync_run_id' => $this->erpSyncRunId,
            ]);

            return;
        }

        $run->forceFill([
            'status' => ErpSyncRun::STATUS_RUNNING,
            'started_at' => now(),
            'finished_at' => null,
            'output' => null,
            'error_message' => null,
        ])->save();

        try {
            $params = $this->normalizeParams($run->params_json);
            $commandName = (string) $run->command_name;

            Log::info('Queued ERP sync started', [
                'erp_sync_run_id' => $run->id,
                'command_key' => $run->command_key,
                'command_name' => $commandName,
                'params' => $params,
            ]);

            $exitCode = Artisan::call($commandName, $params);
            $output = trim((string) Artisan::output());

            $run->forceFill([
                'status' => $exitCode === 0
                    ? ErpSyncRun::STATUS_COMPLETED
                    : ErpSyncRun::STATUS_FAILED,
                'output' => $output !== '' ? $output : null,
                'error_message' => $exitCode === 0
                    ? null
                    : 'Command exited with code ' . $exitCode,
                'finished_at' => now(),
            ])->save();

            Log::info('Queued ERP sync finished', [
                'erp_sync_run_id' => $run->id,
                'command_key' => $run->command_key,
                'command_name' => $commandName,
                'exit_code' => $exitCode,
            ]);
        } catch (Throwable $e) {
            $output = trim((string) Artisan::output());

            Log::error('Queued ERP sync failed', [
                'erp_sync_run_id' => $run->id,
                'command_key' => $run->command_key,
                'command_name' => $run->command_name,
                'params' => $run->params_json,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $run->forceFill([
                'status' => ErpSyncRun::STATUS_FAILED,
                'output' => $output !== '' ? $output : null,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $e;
        }
    }

    private function normalizeParams(mixed $params): array
    {
        if (is_array($params)) {
            return $params;
        }

        if (is_string($params) && trim($params) !== '') {
            $decoded = json_decode($params, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    public function failed(Throwable $exception): void
    {
        $run = ErpSyncRun::query()->find($this->erpSyncRunId);

        if (!$run instanceof ErpSyncRun) {
            return;
        }

        $run->forceFill([
            'status' => ErpSyncRun::STATUS_FAILED,
            'error_message' => $exception->getMessage(),
            'finished_at' => now(),
        ])->save();
    }
}