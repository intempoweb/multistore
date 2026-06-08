<?php

namespace App\Mail\Storefront\Auth;

use App\Models\Customer;
use App\Models\Store;
use App\Services\Storefront\Mail\StorefrontMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Store $store,
        public Customer $customer,
        public string $resetUrl,
    ) {
    }

    public function build(): self
    {
        $mailService = app(StorefrontMailService::class);
        $mailService->applyStoreSender($this, $this->store);

        return $this
            ->subject('Reset password - ' . ($this->store->name ?? 'Store'))
            ->view('storefront.mail.auth.password-reset')
            ->with([
                'mailConfig' => $mailService->configForStore($this->store),
                'expiresMinutes' => (int) config('auth.passwords.customers.expire', 60),
            ]);
    }
}