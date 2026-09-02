<?php

namespace App\Mail\Admin\Orders;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class MonthlyOrderSalesExportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $path,
        public string $filename,
        public Carbon $month,
        public int $ordersCount,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Corrispettivi ordini ' . $this->month->format('m/Y'))
            ->view('emails.admin.orders.monthly_sales_export')
            ->with([
                'month' => $this->month,
                'ordersCount' => $this->ordersCount,
            ])
            ->attach($this->path, [
                'as' => $this->filename,
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
