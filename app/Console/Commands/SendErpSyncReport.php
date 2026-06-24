<?php

namespace App\Console\Commands;

use App\Mail\Erp\ErpSyncReportMail;
use App\Models\ErpSyncRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendErpSyncReport extends Command
{
    protected $signature = 'erp:send-report';

    protected $description = 'Invia il report ERP giornaliero e pulisce log e storico sync';

    public function handle(): int
    {
        $pending = ErpSyncRun::query()
            ->whereIn('status', [
                ErpSyncRun::STATUS_QUEUED,
                ErpSyncRun::STATUS_RUNNING,
            ])
            ->exists();

        if ($pending) {
            $this->warn('Sono ancora presenti sincronizzazioni ERP in esecuzione o in coda. Report non inviato. Log non puliti per conservare il dettaglio tecnico.');

            return self::SUCCESS;
        }

        $runs = ErpSyncRun::query()
            ->orderBy('started_at')
            ->get();

        if ($runs->isEmpty()) {
            $this->cleanLogs();
            $this->info('Nessuna sincronizzazione ERP da riportare. Log puliti.');

            return self::SUCCESS;
        }

        $startedAt = optional($runs->min('started_at'))?->toDateTimeString();
        $finishedAt = optional($runs->max('finished_at'))?->toDateTimeString();

        Mail::to('m.peruzzi@intempo.it')->send(
            new ErpSyncReportMail(
                runs: $runs,
                title: 'Report sincronizzazione ERP',
                startedAt: $startedAt,
                finishedAt: $finishedAt,
            )
        );

        ErpSyncRun::query()->delete();
        $this->cleanLogs();

        $this->info('Report ERP inviato e log puliti.');

        return self::SUCCESS;
    }

    private function cleanLogs(): void
    {
        $this->truncateFile(storage_path('logs/erp-scheduler.log'));
        $this->truncateFile(storage_path('logs/erp-orders.log'));
        $this->truncateFile(storage_path('logs/erp-stock.log'));
        $this->truncateFile(storage_path('logs/erp-prices.log'));
        $this->truncateFile(storage_path('logs/erp-price-tiers.log'));
        $this->truncateFile(storage_path('logs/erp-customer-listini.log'));
        $this->truncateFile(storage_path('logs/erp-report.log'));
        $this->truncateFile(storage_path('logs/worker-erp.log'));
        $this->truncateFile(storage_path('logs/worker-default.log'));
        $this->truncateFile(storage_path('logs/laravel.log'));
    }

    private function truncateFile(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        file_put_contents($path, '');
    }
}