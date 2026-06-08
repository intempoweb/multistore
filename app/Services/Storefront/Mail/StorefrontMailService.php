<?php

namespace App\Services\Storefront\Mail;

use App\Models\Store;
use Illuminate\Mail\Mailable;

class StorefrontMailService
{
    public function applyStoreSender(Mailable $mail, Store $store): Mailable
    {
        $config = $this->configForStore($store);

        $fromAddress = trim((string) ($config['from_address'] ?? ''));
        $fromName = trim((string) ($config['from_name'] ?? ''));

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        return $mail;
    }

    public function configForStore(Store $store): array
    {
        $storeKey = $this->storeConfigKey($store);

        $default = config('mail.storefront.default', []);
        $storeConfig = config('mail.storefront.stores.' . $storeKey, []);

        return array_merge(
            is_array($default) ? $default : [],
            is_array($storeConfig) ? $storeConfig : []
        );
    }

    public function internalRecipientForStore(Store $store): ?string
    {
        $config = $this->configForStore($store);
        $recipient = trim((string) ($config['to_address'] ?? $config['orders_to_address'] ?? $config['admin_to_address'] ?? ''));

        return $recipient !== '' ? $recipient : null;
    }

    public function hasInternalRecipient(Store $store): bool
    {
        return $this->internalRecipientForStore($store) !== null;
    }

    private function storeConfigKey(Store $store): string
{
    $theme = strtolower(trim((string) ($store->theme ?? '')));

    if ($theme !== '' && $theme !== 'default') {
        return $theme;
    }

    $siteCode = strtolower(trim((string) ($store->site_code ?? '')));
    $companyCode = strtolower(trim((string) ($store->company_code ?? '')));
    $domain = strtolower(trim((string) ($store->domain ?? '')));
    $name = strtolower(trim((string) ($store->name ?? '')));

    $haystack = implode(' ', array_filter([
        $siteCode,
        $companyCode,
        $domain,
        $name,
    ]));

    return match (true) {
        str_contains($haystack, 'ciak') => 'ciak',
        str_contains($haystack, 'tekniko') || str_contains($haystack, 'teknikoshop') => 'teknikoshop',
        str_contains($haystack, 'fipell') => 'fipell',
        str_contains($haystack, 'intempo') => 'intempodistribution',
        default => 'default',
    };
}
}