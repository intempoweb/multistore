<?php

namespace App\Mail\Storefront\Documents;

use App\Models\Store;
use App\Services\Storefront\Mail\StorefrontMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerDocumentRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Store $store,
        public string $type,
        public mixed $requestModel,
    ) {
    }

    public function build(): self
    {
        $mailService = app(StorefrontMailService::class);
        $mailService->applyStoreSender($this, $this->store);

        $isReturn = $this->type === 'return';
        $requestNumber = $isReturn
            ? (string) ($this->requestModel->request_number ?? '')
            : (string) ($this->requestModel->ticket_number ?? '');

        $subject = ($isReturn ? '[Reso documento] ' : '[Ticket documento] ')
            . ($requestNumber !== '' ? $requestNumber : 'Nuova richiesta')
            . ' - '
            . ($this->store->name ?? 'Store');

        return $this
            ->subject($subject)
            ->view('storefront.mail.documents.customer-request')
            ->with([
                'store' => $this->store,
                'mailConfig' => $mailService->configForStore($this->store),
                'type' => $this->type,
                'requestModel' => $this->requestModel,
                'isReturn' => $isReturn,
            ]);
    }
}
