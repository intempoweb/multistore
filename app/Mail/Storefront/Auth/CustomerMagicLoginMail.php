<?php

namespace App\Mail\Storefront\Auth;

use App\Models\Customer;
use App\Models\Store;
use App\Services\Storefront\Mail\StorefrontMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerMagicLoginMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Store $store,
        public Customer $customer,
        public string $signedUrl,
        public int $expireMinutes,
    ) {
    }

    public function build(): self
    {
        $mailService = app(StorefrontMailService::class);
        $mailService->applyStoreSender($this, $this->store);

        return $this
            ->subject('Link di accesso - ' . ($this->store->name ?? 'Store'))
            ->view('storefront.mail.auth.magic-login')
            ->with([
                'store' => $this->store,
                'customer' => $this->customer,
                'signedUrl' => $this->signedUrl,
                'expireMinutes' => $this->expireMinutes,
                'mailConfig' => $mailService->configForStore($this->store),
            ]);
    }
}