<?php

namespace App\Services\Newsletters;

use App\Contracts\Newsletters\NewsletterProviderInterface;
use App\Models\Newsletter;
use App\Services\Newsletters\Providers\BrevoNewsletterProvider;
use App\Services\Newsletters\Providers\MailchimpNewsletterProvider;
use InvalidArgumentException;

class NewsletterProviderManager
{
    public function provider(string $key): NewsletterProviderInterface
    {
        return match ($key) {
            'mailchimp' => app(MailchimpNewsletterProvider::class),
            'brevo' => app(BrevoNewsletterProvider::class),
            default => throw new InvalidArgumentException("Newsletter provider non supportato: {$key}"),
        };
    }

    public function settingsFor(Newsletter $newsletter): array
    {
        $provider = trim((string) $newsletter->provider);
        $context = (array) config('newsletters.contexts.' . $newsletter->contextKey(), []);
        $global = (array) config('newsletters.providers.' . $provider, []);
        $contextProvider = (array) ($context[$provider] ?? []);

        return array_replace_recursive($global, $contextProvider, [
            'context_key' => $newsletter->contextKey(),
            'timeout' => (int) config('newsletters.timeout', 20),
            'retry_times' => (int) config('newsletters.retry_times', 2),
            'retry_sleep_ms' => (int) config('newsletters.retry_sleep_ms', 250),
        ]);
    }
}
