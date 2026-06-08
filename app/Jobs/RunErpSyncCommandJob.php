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

class RunErpSyncCommandJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 14400;
    public int $tries = 1;

    public function __construct(public int $erpSyncRunId)
    {
        $this->onQueue('erp');
    }

    public function handle(): void
    {
        $run = ErpSyncRun::query()->find($this->erpSyncRunId);

        if (!$run instanceof ErpSyncRun) {
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
            $params = is_array($run->params_json) ? $run->params_json : [];
            $exitCode = Artisan::call((string) $run->command_name, $params);
            $output = trim((string) Artisan::output());

            $run->forceFill([
                'status' => $exitCode === 0 ? ErpSyncRun::STATUS_COMPLETED : ErpSyncRun::STATUS_FAILED,
                'output' => $output !== '' ? $output : null,
                'error_message' => $exitCode === 0 ? null : 'Command exited with code ' . $exitCode,
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            Log::error('ERP queued sync failed', [
                'run_id' => $run->id,
                'command' => $run->command_name,
                'message' => $e->getMessage(),
            ]);

            $run->forceFill([
                'status' => ErpSyncRun::STATUS_FAILED,
                'output' => trim((string) Artisan::output()) ?: null,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $e;
        }
    }
}