<?php

namespace App\Services\Storefront\Home\Presenters;

use App\Data\Storefront\HomePageInput;
use App\Models\Store;
use App\Services\Storefront\Home\HomePagePresenter;
use Illuminate\Support\Collection;

final class TeknikoHomePagePresenter implements HomePagePresenter
{
    public function supports(Store $store): bool
    {
        return $store->isB2C()
            && in_array(strtolower(trim((string) $store->theme)), ['tekniko', 'teknikoshop'], true);
    }

    public function present(HomePageInput $input): array
    {
        $hero = $this->block($input->storefrontPageBlocks, ['hero'], ['home_hero']);

        return [
            'heroMedia' => $this->heroMedia($hero),
        ];
    }

    private function block(Collection $blocks, array $types, array $names): mixed
    {
        return $blocks->first(fn ($block) => in_array($block->type, $types, true) || in_array($block->name, $names, true));
    }

    private function heroMedia(mixed $hero): Collection
    {
        $media = collect($hero?->activeMedia ?? [])->map(fn ($item) => [
            'type' => $item->media_type ?: 'image',
            'desktop' => media_url($item->desktop_path),
            'mobile' => media_url($item->mobile_path),
            'poster' => media_url($item->poster_path),
            'alt' => $item->alt_text,
        ])->filter(fn ($item) => filled($item['desktop']))->values();

        if ($media->isEmpty() && (filled($hero?->image_path) || filled($hero?->video_path))) {
            return collect([[
                'type' => filled($hero?->video_path) ? 'video' : 'image',
                'desktop' => media_url($hero?->video_path ?: $hero?->image_path),
                'mobile' => media_url($hero?->mobile_image_path),
                'poster' => media_url($hero?->image_path),
                'alt' => $this->blockImageAlt($hero),
            ]]);
        }

        return $media;
    }

    private function blockImageAlt(mixed $block): string
    {
        $settings = is_array($block?->settings ?? null) ? $block->settings : [];
        $alt = trim((string) data_get($settings, 'image_alt', ''));

        return $alt !== '' ? $alt : trim((string) ($block?->title ?? ''));
    }
}
