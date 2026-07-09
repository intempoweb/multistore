<?php

namespace App\Mail\Storefront\Inquiries;

use App\Models\Store;
use App\Services\Storefront\Mail\StorefrontMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CorporateGiftInquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array{name:string,mime:string,content:string}> $mailAttachments
     */
    public function __construct(
        public Store $store,
        public array $payload,
        public array $mailAttachments = [],
    ) {
    }

    public function build(): self
    {
        $mailService = app(StorefrontMailService::class);
        $mailService->applyStoreSender($this, $this->store);

        $fullName = trim(($this->payload['first_name'] ?? '') . ' ' . ($this->payload['last_name'] ?? ''));
        $product = (string) ($this->payload['product_type'] ?? '-');

        $mail = $this
            ->subject('[Regalistica Aziendale] ' . strtoupper($product) . ' - ' . ($fullName !== '' ? $fullName : ($this->store->name ?? 'Store')))
            ->view('storefront.mail.inquiries.corporate-gift')
            ->with([
                'store' => $this->store,
                'mailConfig' => $mailService->configForStore($this->store),
                'payload' => $this->payload,
            ]);

        foreach ($this->mailAttachments as $attachment) {
            if (!isset($attachment['content'], $attachment['name'])) {
                continue;
            }

            $mail->attachData((string) $attachment['content'], (string) $attachment['name'], [
                'mime' => (string) ($attachment['mime'] ?? 'application/octet-stream'),
            ]);
        }

        return $mail;
    }
}
