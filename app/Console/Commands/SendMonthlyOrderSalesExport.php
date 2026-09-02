<?php

namespace App\Console\Commands;

use App\Mail\Admin\Orders\MonthlyOrderSalesExportMail;
use App\Services\Admin\Orders\OrderSalesExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendMonthlyOrderSalesExport extends Command
{
    protected $signature = 'orders:send-monthly-sales-export
        {--month= : Mese da esportare in formato YYYY-MM. Se vuoto invia il mese precedente.}
        {--to= : Destinatario email. Se vuoto usa ORDER_SALES_EXPORT_RECIPIENT.}
        {--store_id= : Limita export a uno store specifico.}';

    protected $description = 'Invia via email l export Excel mensile dei corrispettivi ordini.';

    public function handle(OrderSalesExportService $exportService): int
    {
        $timezone = (string) config('order_sales_exports.timezone', 'Europe/Rome');
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m', (string) $this->option('month'), $timezone)->startOfMonth()
            : now($timezone)->subMonthNoOverflow()->startOfMonth();
        $recipient = trim((string) ($this->option('to') ?: config('order_sales_exports.recipient')));
        $storeId = $this->option('store_id') !== null ? (int) $this->option('store_id') : null;

        if ($recipient === '') {
            $this->error('Destinatario mancante. Configura ORDER_SALES_EXPORT_RECIPIENT o usa --to.');

            return self::FAILURE;
        }

        $path = $exportService->build($month, $storeId);
        $filename = $exportService->filename($month, $storeId);
        $ordersCount = $exportService->queryForMonth($month, $storeId)->count();

        Mail::to($recipient)->send(new MonthlyOrderSalesExportMail(
            path: $path,
            filename: $filename,
            month: $month,
            ordersCount: $ordersCount,
        ));

        if (file_exists($path)) {
            @unlink($path);
        }

        $this->info(sprintf(
            'Export corrispettivi %s inviato a %s (%d ordini).',
            $month->format('Y-m'),
            $recipient,
            $ordersCount
        ));

        return self::SUCCESS;
    }
}
