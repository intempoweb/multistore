<?php

namespace App\Mail\Storefront\Inquiries;

use App\Models\Store;
use App\Services\Storefront\Mail\StorefrontMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactInquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Store $store,
        public array $payload,
    ) {
    }

    public function build(): self
    {
        $mailService = app(StorefrontMailService::class);
        $mailService->applyStoreSender($this, $this->store);

        $fullName = trim(($this->payload['first_name'] ?? '') . ' ' . ($this->payload['last_name'] ?? ''));
        $subjectPrefix = trim((string) ($this->payload['subject'] ?? ''));

        return $this
            ->subject('[Contatti] ' . ($subjectPrefix !== '' ? $subjectPrefix : 'Nuova richiesta') . ' - ' . ($fullName !== '' ? $fullName : ($this->store->name ?? 'Store')))
            ->view('storefront.mail.inquiries.contact')
            ->with([
                'store' => $this->store,
                'mailConfig' => $mailService->configForStore($this->store),
                'payload' => $this->payload,
            ]);
    }
}
