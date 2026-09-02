<?php

namespace App\Services\Newsletters\Providers;

use App\Contracts\Newsletters\NewsletterProviderInterface;
use App\Models\Newsletter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MailchimpNewsletterProvider implements NewsletterProviderInterface
{
    public function key(): string
    {
        return Newsletter::PROVIDER_MAILCHIMP;
    }

    public function test(array $settings): array
    {
        $response = $this->client($settings)->get($this->baseUrl($settings) . '/');

        if (! $response->successful()) {
            throw new RuntimeException('Connessione Mailchimp non riuscita: HTTP ' . $response->status());
        }

        return [
            'ok' => true,
            'provider' => $this->key(),
            'account_name' => $response->json('account_name'),
        ];
    }

    public function createDraft(Newsletter $newsletter, string $html, array $settings): array
    {
        $audienceId = $this->required($settings, 'audience_id', 'MAILCHIMP_AUDIENCE_ID');

        $response = $this->client($settings)->post($this->baseUrl($settings) . '/campaigns', [
            'type' => 'regular',
            'recipients' => [
                'list_id' => $audienceId,
            ],
            'settings' => [
                'subject_line' => $newsletter->subject,
                'preview_text' => $newsletter->preview_text,
                'title' => $newsletter->name,
                'from_name' => $this->required($settings, 'from_name', 'MAILCHIMP_FROM_NAME'),
                'reply_to' => $this->required($settings, 'reply_to', 'MAILCHIMP_REPLY_TO'),
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Creazione bozza Mailchimp non riuscita: ' . $this->safeBody($response->body()));
        }

        $campaign = $response->json() ?? [];
        $campaignId = (string) data_get($campaign, 'id');

        if ($campaignId === '') {
            throw new RuntimeException('Mailchimp non ha restituito un campaign id.');
        }

        $this->setContent($campaignId, $html, $settings);

        return [
            'provider_campaign_id' => $campaignId,
            'provider_web_id' => data_get($campaign, 'web_id'),
            'provider_edit_url' => data_get($campaign, 'edit_url'),
            'payload' => $campaign,
        ];
    }

    public function updateDraft(Newsletter $newsletter, string $html, array $settings): array
    {
        $campaignId = trim((string) $newsletter->provider_campaign_id);

        if ($campaignId === '') {
            return $this->createDraft($newsletter, $html, $settings);
        }

        $response = $this->client($settings)->patch($this->baseUrl($settings) . '/campaigns/' . $campaignId, [
            'settings' => [
                'subject_line' => $newsletter->subject,
                'preview_text' => $newsletter->preview_text,
                'title' => $newsletter->name,
                'from_name' => $this->required($settings, 'from_name', 'MAILCHIMP_FROM_NAME'),
                'reply_to' => $this->required($settings, 'reply_to', 'MAILCHIMP_REPLY_TO'),
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Aggiornamento bozza Mailchimp non riuscito: ' . $this->safeBody($response->body()));
        }

        $this->setContent($campaignId, $html, $settings);

        return [
            'provider_campaign_id' => $campaignId,
            'provider_web_id' => data_get($response->json(), 'web_id', $newsletter->provider_web_id),
            'provider_edit_url' => data_get($response->json(), 'edit_url', $newsletter->provider_edit_url),
            'payload' => $response->json() ?? [],
        ];
    }

    private function setContent(string $campaignId, string $html, array $settings): void
    {
        $response = $this->client($settings)->put($this->baseUrl($settings) . '/campaigns/' . $campaignId . '/content', [
            'html' => $html,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Invio HTML a Mailchimp non riuscito: ' . $this->safeBody($response->body()));
        }
    }

    private function client(array $settings): PendingRequest
    {
        $apiKey = $this->required($settings, 'api_key', 'MAILCHIMP_API_KEY');

        return Http::withBasicAuth('newsletter', $apiKey)
            ->timeout((int) ($settings['timeout'] ?? 20))
            ->retry((int) ($settings['retry_times'] ?? 2), (int) ($settings['retry_sleep_ms'] ?? 250))
            ->acceptJson()
            ->asJson();
    }

    private function baseUrl(array $settings): string
    {
        $prefix = $this->required($settings, 'server_prefix', 'MAILCHIMP_SERVER_PREFIX');

        return 'https://' . $prefix . '.api.mailchimp.com/3.0';
    }

    private function required(array $settings, string $key, string $label): string
    {
        $value = trim((string) ($settings[$key] ?? ''));

        if ($value === '') {
            throw new RuntimeException("Configurazione {$label} mancante.");
        }

        return $value;
    }

    private function safeBody(string $body): string
    {
        return mb_substr($body, 0, 1000);
    }
}
