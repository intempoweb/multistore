<?php

namespace App\Services\Newsletters;

use App\Models\Newsletter;
use Illuminate\Support\Facades\DB;

class NewsletterSyncService
{
    public function __construct(
        private NewsletterRenderer $renderer,
        private NewsletterProviderManager $providers,
    ) {
    }

    public function syncDraft(Newsletter $newsletter): Newsletter
    {
        $newsletter->loadMissing(['store', 'products']);
        $html = $this->renderer->render($newsletter);
        $provider = $this->providers->provider($newsletter->provider);
        $settings = $this->providers->settingsFor($newsletter);

        $result = $newsletter->provider_campaign_id
            ? $provider->updateDraft($newsletter, $html, $settings)
            : $provider->createDraft($newsletter, $html, $settings);

        return DB::transaction(function () use ($newsletter, $html, $result) {
            $newsletter->forceFill([
                'status' => Newsletter::STATUS_SYNCED,
                'rendered_html' => $html,
                'provider_campaign_id' => (string) ($result['provider_campaign_id'] ?? $newsletter->provider_campaign_id),
                'provider_web_id' => isset($result['provider_web_id']) ? (string) $result['provider_web_id'] : $newsletter->provider_web_id,
                'provider_edit_url' => $result['provider_edit_url'] ?? $newsletter->provider_edit_url,
                'provider_synced_at' => now(),
            ])->save();

            return $newsletter->fresh(['store', 'products']);
        });
    }
}
