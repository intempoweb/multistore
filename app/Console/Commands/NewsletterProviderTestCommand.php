<?php

namespace App\Console\Commands;

use App\Models\Newsletter;
use App\Services\Newsletters\NewsletterProviderManager;
use Illuminate\Console\Command;

class NewsletterProviderTestCommand extends Command
{
    protected $signature = 'newsletter:provider-test {provider? : mailchimp|brevo} {--context= : Chiave ditta:site_type, es. 1:1}';

    protected $description = 'Verifica credenziali provider newsletter senza inviare campagne';

    public function handle(NewsletterProviderManager $manager): int
    {
        $providerKey = (string) ($this->argument('provider') ?: config('newsletters.default_provider', Newsletter::PROVIDER_MAILCHIMP));
        $context = (string) ($this->option('context') ?: '1:1');
        [$ditta, $site] = array_pad(explode(':', $context, 2), 2, 0);

        $newsletter = new Newsletter([
            'provider' => $providerKey,
            'ditta_cg18' => (int) $ditta,
            'site_type' => (int) $site,
        ]);

        $result = $manager->provider($providerKey)->test($manager->settingsFor($newsletter));

        $this->info('Provider newsletter raggiungibile.');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
