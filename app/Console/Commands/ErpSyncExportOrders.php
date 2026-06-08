<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Erp\OrderExportService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ErpSyncExportOrders extends Command
{
    protected $signature = 'erp:export-orders
                            {--limit=50 : Numero massimo ordini da esportare}
                            {--order_id= : Esporta un solo ordine tramite ID interno}';

    protected $description = 'Esporta verso ERP gli ordini B2B e gli ordini B2C con fattura richiesta';

    public function handle(OrderExportService $exportService): int
    {
        @set_time_limit(0);

        $query = Order::query()
            ->with(['items', 'store', 'customer'])
            ->requiresErpExport()
            ->whereIn('erp_export_status', ['pending', 'failed'])
            ->whereNotNull('placed_at')
            ->whereNull('erp_web_numreg')
            ->whereNull('erp_web_id')
            ->orderBy('id');

        if ($this->option('order_id')) {
            $query->where('id', (int) $this->option('order_id'));
        }

        $orders = $query
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Nessun ordine da esportare verso ERP.');

            return self::SUCCESS;
        }

        $exported = 0;
        $failed = 0;
        $usedNumregs = [];

        foreach ($orders as $order) {
            try {
                $this->line(sprintf('[#%d] Export ordine %s...', $order->id, $order->order_number));

                $exportService->export($order);

                $order->refresh();

                $numreg = trim((string) $order->erp_web_numreg);

                if ($numreg === '') {
                    throw new RuntimeException('Export ERP completato senza NUMREG salvato sull’ordine locale.');
                }

                if (isset($usedNumregs[$numreg])) {
                    throw new RuntimeException(sprintf(
                        'NUMREG duplicato nel batch: %s già usato dall’ordine locale #%d.',
                        $numreg,
                        $usedNumregs[$numreg]
                    ));
                }

                $duplicateLocalOrderId = Order::query()
                    ->where('id', '!=', $order->id)
                    ->where('erp_web_numreg', $numreg)
                    ->value('id');

                if ($duplicateLocalOrderId !== null) {
                    throw new RuntimeException(sprintf(
                        'NUMREG duplicato nel database locale: %s già associato all’ordine #%d.',
                        $numreg,
                        $duplicateLocalOrderId
                    ));
                }

                $usedNumregs[$numreg] = $order->id;
                $exported++;

                $this->info(sprintf('[#%d] OK ERP WEB NUMREG: %s', $order->id, $numreg));
            } catch (Throwable $exception) {
                $failed++;

                report($exception);

                $order->forceFill([
                    'erp_export_status' => 'failed',
                    'erp_export_error' => mb_substr($exception->getMessage(), 0, 65535),
                ])->save();

                $this->error(sprintf('[#%d] ERRORE: %s', $order->id, $exception->getMessage()));

                break;
            }
        }

        $this->newLine();

        $this->info(sprintf(
            'Export ERP ordini completato. Successi: %d - Errori: %d',
            $exported,
            $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}