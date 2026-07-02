<?php

namespace App\Services\Storefront\Integrations;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramFeedService
{
    public function latest(?int $limit = null): Collection
    {
        $token = trim((string) config('services.instagram.access_token', ''));

        if ($token === '') {
            return collect();
        }

        $limit ??= (int) config('services.instagram.limit', 6);
        $limit = max(1, min($limit, 60));
        $ttl = max(60, (int) config('services.instagram.cache_ttl', 3600));

        return Cache::remember('storefront.instagram.feed.' . md5($this->endpoint() . '|' . $limit . '|' . $this->fields()), now()->addSeconds($ttl), function () use ($limit) {
            try {
                $response = Http::timeout(8)
                    ->retry(2, 200)
                    ->get($this->endpoint(), [
                        'fields' => $this->fields(),
                        'limit' => $limit,
                        'access_token' => config('services.instagram.access_token'),
                    ]);

                if (! $response->successful()) {
                    Log::warning('Instagram feed request failed.', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return collect();
                }

                return collect($response->json('data', []))
                    ->map(fn (array $item) => $this->normalise($item))
                    ->filter(fn (array $item) => filled($item['desktop']))
                    ->take($limit)
                    ->values();
            } catch (\Throwable $exception) {
                Log::warning('Instagram feed request exception.', [
                    'message' => $exception->getMessage(),
                ]);

                return collect();
            }
        });
    }

    private function endpoint(): string
    {
        $baseUrl = rtrim((string) config('services.instagram.base_url', 'https://graph.instagram.com'), '/');
        $userId = trim((string) config('services.instagram.user_id', ''));

        if ($userId !== '') {
            return $baseUrl . '/' . $userId . '/media';
        }

        return $baseUrl . '/me/media';
    }

    private function fields(): string
    {
        $fields = [
            'id',
            'caption',
            'media_type',
            'media_url',
            'permalink',
            'thumbnail_url',
            'timestamp',
        ];

        if ($this->supportsMetrics()) {
            $fields[] = 'like_count';
            $fields[] = 'comments_count';
        }

        return implode(',', $fields);
    }

    private function supportsMetrics(): bool
    {
        if (filter_var(config('services.instagram.include_metrics'), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return str_contains((string) config('services.instagram.base_url'), 'graph.facebook.com');
    }

    private function normalise(array $item): array
    {
        $mediaType = strtoupper((string) ($item['media_type'] ?? 'IMAGE'));
        $isVideo = $mediaType === 'VIDEO';
        $image = $isVideo
            ? ($item['thumbnail_url'] ?? $item['media_url'] ?? null)
            : ($item['media_url'] ?? $item['thumbnail_url'] ?? null);

        return [
            'type' => $isVideo ? 'video' : 'image',
            'desktop' => $isVideo ? ($item['media_url'] ?? null) : $image,
            'mobile' => null,
            'poster' => $isVideo ? $image : null,
            'alt' => $this->caption($item),
            'permalink' => $item['permalink'] ?? null,
            'timestamp' => $item['timestamp'] ?? null,
            'likes' => isset($item['like_count']) ? (int) $item['like_count'] : null,
            'comments' => isset($item['comments_count']) ? (int) $item['comments_count'] : null,
            'source' => 'instagram',
        ];
    }

    private function caption(array $item): string
    {
        $caption = trim((string) ($item['caption'] ?? ''));

        if ($caption === '') {
            return 'Instagram';
        }

        return mb_substr($caption, 0, 140);
    }
}
