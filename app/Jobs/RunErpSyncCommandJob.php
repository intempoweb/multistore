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
                'error_message' => $exitCode === 0 ? null : $this->humanErrorMessage($run, $output, $exitCode),
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $output = trim((string) Artisan::output());

            Log::error('ERP queued sync failed', [
                'run_id' => $run->id,
                'command' => $run->command_name,
                'message' => $e->getMessage(),
            ]);

            $run->forceFill([
                'status' => ErpSyncRun::STATUS_FAILED,
                'output' => $output !== '' ? $output : null,
                'error_message' => $this->humanErrorMessage($run, $output, null, $e),
                'finished_at' => now(),
            ])->save();

            throw $e;
        }
    }

    private function humanErrorMessage(ErpSyncRun $run, ?string $output = null, ?int $exitCode = null, ?Throwable $exception = null): string
    {
        $command = (string) $run->command_name;
        $technicalMessage = trim((string) ($exception?->getMessage() ?: $output));

        $base = match ($command) {
            'erp:sync-attributes' => 'La sincronizzazione attributi globali non è stata completata.',
            'erp:sync-products' => 'La sincronizzazione prodotti non è stata completata.',
            'erp:sync-product-comparisons' => 'La sincronizzazione articoli comparativi non è stata completata.',
            'erp:sync-product-attribute-values' => 'La sincronizzazione attributi prodotto non è stata completata.',
            'erp:sync-group-descriptions' => 'La sincronizzazione descrizioni catalogo non è stata completata.',
            'erp:sync-customers' => 'La sincronizzazione clienti non è stata completata.',
            'erp:sync-customer-acl' => 'La sincronizzazione permessi clienti non è stata completata.',
            'erp:sync-customer-shipping-addresses' => 'La sincronizzazione indirizzi di spedizione clienti non è stata completata.',
            'erp:sync-customer-listini' => 'La sincronizzazione listini clienti non è stata completata.',
            'erp:sync-store-visible-groups' => 'La sincronizzazione gruppi visibili per store non è stata completata.',
            'erp:sync-public-prices' => 'La sincronizzazione prezzi pubblici non è stata completata.',
            'erp:sync-price-tiers' => 'La sincronizzazione fasce prezzo B2B non è stata completata.',
            'erp:sync-stock' => 'La sincronizzazione giacenze non è stata completata.',
            'erp:sync-media' => 'La sincronizzazione media non è stata completata.',
            'erp:export-orders' => 'L’esportazione ordini verso ERP non è stata completata.',
            default => 'La sincronizzazione ERP non è stata completata.',
        };

        return trim($base . ' ' . $this->humanReason($technicalMessage, $exitCode));
    }

    private function humanReason(?string $message = null, ?int $exitCode = null): string
    {
        $message = trim((string) $message);
        $lower = strtolower($message);

        if (str_contains($lower, 'sqlstate') || str_contains($lower, 'connection') || str_contains($lower, 'could not connect')) {
            return 'Possibile problema di connessione o query verso il database ERP.';
        }

        if (str_contains($lower, 'timeout') || str_contains($lower, 'timed out')) {
            return 'Il processo ha superato il tempo massimo di esecuzione.';
        }

        if (str_contains($lower, 'duplicate') || str_contains($lower, 'integrity constraint')) {
            return 'Sono presenti dati duplicati o non coerenti.';
        }

        if (str_contains($lower, 'permission denied') || str_contains($lower, 'access denied')) {
            return 'Permessi insufficienti per completare l’operazione.';
        }

        if (str_contains($lower, 'data too long') || str_contains($lower, 'right truncated')) {
            return 'Uno o più campi ricevuti dall’ERP superano la lunghezza prevista dal database locale.';
        }

        if (str_contains($lower, 'undefined method') || str_contains($lower, 'class not found')) {
            return 'Errore applicativo: metodo o classe non disponibile nel codice deployato.';
        }

        if ($exitCode !== null) {
            return 'Il comando è terminato con codice errore ' . $exitCode . '.';
        }

        if ($message !== '') {
            return 'Dettaglio: ' . mb_strimwidth($message, 0, 220, '...');
        }

        return 'Controllare il dettaglio del processo ERP.';
    }
}