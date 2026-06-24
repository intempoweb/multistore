<?php

namespace App\Mail\Erp;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ErpSyncReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Collection $runs,
        public string $title,
        public ?string $startedAt = null,
        public ?string $finishedAt = null,
        public ?Store $store = null,
    ) {
    }

    public function build(): self
    {
        $store = null;
        $mailConfig = array_merge(
            config('mail.storefront.default', []),
            config('mail.storefront.stores.intempo', [])
        );

        $this->from(
            (string) ($mailConfig['from_address'] ?? config('mail.from.address')),
            (string) ($mailConfig['from_name'] ?? config('mail.from.name'))
        );

        $failed = $this->runs->where('status', 'failed')->count();
        $completed = $this->runs->where('status', 'completed')->count();
        $running = $this->runs->where('status', 'running')->count();
        $queued = $this->runs->where('status', 'queued')->count();
        $total = $this->runs->count();

        return $this
            ->subject($this->subjectLine($failed))
            ->view('storefront.mail.erp.sync-report')
            ->with([
                'store' => $store,
                'mailConfig' => $mailConfig,
                'title' => $this->title,
                'runs' => $this->runs,
                'total' => $total,
                'completed' => $completed,
                'failed' => $failed,
                'running' => $running,
                'queued' => $queued,
                'startedAt' => $this->startedAt,
                'finishedAt' => $this->finishedAt,
            ]);
    }

    private function subjectLine(int $failed): string
    {
        return $failed > 0
            ? '[ERP] Sync completata con errori'
            : '[ERP] Sync completata correttamente';
    }
}