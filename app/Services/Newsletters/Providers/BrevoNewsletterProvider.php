<?php

namespace App\Services\Newsletters\Providers;

use App\Contracts\Newsletters\NewsletterProviderInterface;
use App\Models\Newsletter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BrevoNewsletterProvider implements NewsletterProviderInterface
{
    public function key(): string
    {
        return Newsletter::PROVIDER_BREVO;
    }

    public function test(array $settings): array
    {
        $response = $this->client($settings)->get($this->baseUrl() . '/account');

        if (! $response->successful()) {
            throw new RuntimeException('Connessione Brevo non riuscita: HTTP ' . $response->status());
        }

        return [
            'ok' => true,
            'provider' => $this->key(),
            'email' => $response->json('email'),
            'company_name' => $response->json('companyName'),
        ];
    }

    public function createDraft(Newsletter $newsletter, string $html, array $settings): array
    {
        $response = $this->client($settings)->post($this->baseUrl() . '/emailCampaigns', $this->payload($newsletter, $html, $settings));

        if (! $response->successful()) {
            throw new RuntimeException('Creazione bozza Brevo non riuscita: ' . $this->safeBody($response->body()));
        }

        $id = (string) data_get($response->json(), 'id');

        if ($id === '') {
            throw new RuntimeException('Brevo non ha restituito un campaign id.');
        }

        return [
            'provider_campaign_id' => $id,
            'provider_web_id' => $id,
            'provider_edit_url' => null,
            'payload' => $response->json() ?? [],
        ];
    }

    public function updateDraft(Newsletter $newsletter, string $html, array $settings): array
    {
        $campaignId = trim((string) $newsletter->provider_campaign_id);

        if ($campaignId === '') {
            return $this->createDraft($newsletter, $html, $settings);
        }

        $response = $this->client($settings)->put($this->baseUrl() . '/emailCampaigns/' . $campaignId, $this->payload($newsletter, $html, $settings));

        if (! $response->successful()) {
            throw new RuntimeException('Aggiornamento bozza Brevo non riuscito: ' . $this->safeBody($response->body()));
        }

        return [
            'provider_campaign_id' => $campaignId,
            'provider_web_id' => $newsletter->provider_web_id ?: $campaignId,
            'provider_edit_url' => $newsletter->provider_edit_url,
            'payload' => $response->json() ?? [],
        ];
    }

    private function payload(Newsletter $newsletter, string $html, array $settings): array
    {
        $sender = [];
        $senderId = trim((string) ($settings['sender_id'] ?? ''));

        if ($senderId !== '') {
            $sender['id'] = (int) $senderId;
        } else {
            $sender['email'] = $this->required($settings, 'sender_email', 'BREVO_SENDER_EMAIL');
            $sender['name'] = $this->required($settings, 'sender_name', 'BREVO_SENDER_NAME');
        }

        return [
            'name' => $newsletter->name,
            'subject' => $newsletter->subject,
            'previewText' => $newsletter->preview_text,
            'sender' => $sender,
            'replyTo' => $this->required($settings, 'reply_to', 'BREVO_REPLY_TO'),
            'recipients' => [
                'listIds' => $this->listIds($settings),
            ],
            'htmlContent' => $html,
        ];
    }

    private function listIds(array $settings): array
    {
        $ids = collect($settings['list_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($ids === []) {
            throw new RuntimeException('Configurazione BREVO_LIST_IDS mancante.');
        }

        return $ids;
    }

    private function client(array $settings): PendingRequest
    {
        return Http::withHeaders([
                'api-key' => $this->required($settings, 'api_key', 'BREVO_API_KEY'),
            ])
            ->timeout((int) ($settings['timeout'] ?? 20))
            ->retry((int) ($settings['retry_times'] ?? 2), (int) ($settings['retry_sleep_ms'] ?? 250))
            ->acceptJson()
            ->asJson();
    }

    private function baseUrl(): string
    {
        return 'https://api.brevo.com/v3';
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
