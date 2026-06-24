<?php

namespace App\Services\Storefront\Home\Presenters;

use App\Data\Storefront\HomePageInput;
use App\Models\Store;
use App\Services\Storefront\Home\HomePagePresenter;
use App\Services\Storefront\Integrations\InstagramFeedService;
use Illuminate\Support\Collection;

final class CiakHomePagePresenter implements HomePagePresenter
{
    public function __construct(
        private InstagramFeedService $instagramFeed,
    ) {}

    public function supports(Store $store): bool
    {
        return ! $store->is_b2b && strtolower(trim((string) $store->theme)) === 'ciak';
    }

    public function present(HomePageInput $input): array
    {
        $hero = $this->block($input->storefrontPageBlocks, ['hero'], ['home_hero']);
        $about = $this->block($input->storefrontPageBlocks, ['about'], ['home_about', 'chi_siamo']);
        $editorial = $this->block($input->storefrontPageBlocks, ['editorial'], ['home_story']);
        $banner = $this->block($input->storefrontPageBlocks, ['editorial_banner'], ['home_banner']);
        $vision = $this->block($input->storefrontPageBlocks, ['vision'], ['home_vision']);
        $instagram = $this->block($input->storefrontPageBlocks, ['instagram_gallery', 'gallery'], ['home_instagram', 'instagram']);
        $products = collect(method_exists($input->products, 'items') ? $input->products->items() : $input->products);
        $featured = $products->filter(fn ($product) => (bool) ($product->flgnovita_webt01 ?? false))->take(4);

        if ($featured->isEmpty()) {
            $featured = $products->shuffle()->take(4);
        }

        $agendaCategory = $this->findCategory($input->rootCategories, ['agenda']);
        $notebookCategory = $this->findCategory($input->rootCategories, ['taccuin', 'quadern']);

        return [
            'hero' => $hero,
            'heroButtonUrl' => $this->buttonUrl($hero),
            'heroMedia' => $this->heroMedia($hero),
            'aboutSection' => $this->sectionDataWhenFilled($about),
            'formatGroups' => $this->formatGroups($input->rootCategories, $agendaCategory, $notebookCategory),
            'featuredRows' => $featured->map(fn ($product) => [
                'product' => $product,
                'listingCard' => collect($input->listingCardsByProductSku->get((string) $product->sku, [])),
            ])->values(),
            'editorialSection' => $this->editorialSection($editorial, $banner),
            'visionSection' => $this->sectionDataWhenFilled($vision),
            'instagramSection' => $this->instagramSection($instagram),
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
                'alt' => $hero?->title,
            ]]);
        }

        return $media;
    }

    private function formatGroups(Collection $categories, mixed $agendaCategory, mixed $notebookCategory): Collection
    {
        return collect([
            'agende' => [
                'label' => __('Agende'),
                'category' => $agendaCategory,
                'items' => [
                    ['label' => __('Agenda giornaliera'), 'terms' => ['giornal'], 'image' => asset('images/themes/b2c/ciak/formats/agenda-giornaliera.png')],
                    ['label' => __('Agenda settimanale'), 'terms' => ['settiman'], 'image' => asset('images/themes/b2c/ciak/formats/agenda-settimanale.png')],
                ],
            ],
            'taccuini' => [
                'label' => __('Taccuini'),
                'category' => $notebookCategory,
                'items' => [
                    ['label' => __('Pagine a puntini'), 'terms' => ['puntin'], 'image' => asset('images/themes/b2c/ciak/formats/taccuino-puntini.png')],
                    ['label' => __('Pagine a righe'), 'terms' => ['righe'], 'image' => asset('images/themes/b2c/ciak/formats/taccuino-righe.png')],
                    ['label' => __('Pagine bianche'), 'terms' => ['bianch', 'vuote'], 'image' => asset('images/themes/b2c/ciak/formats/taccuino-pagine-bianche.png')],
                ],
            ],
        ])->map(function ($group, $key) use ($categories) {
                $group['key'] = $key;
                $group['available'] = (bool) $group['category'];
                $group['items'] = collect($group['items'])->map(function ($item) use ($categories, $group) {
                    $target = $this->findCategory($categories, $item['terms']) ?: $group['category'];

                    return [
                        'label' => $item['label'],
                        'group' => $group['label'],
                        'group_key' => $group['key'],
                        'available' => $group['available'],
                        'image' => $item['image'],
                        'url' => $target ? route('storefront.category.show', $target['slug']) : null,
                    ];
                });

                return $group;
            });
    }

    private function findCategory(Collection $categories, array $terms): mixed
    {
        return $categories->first(function ($category) use ($terms) {
            $haystack = mb_strtolower(trim(($category['label'] ?? '').' '.($category['slug'] ?? '')));

            return collect($terms)->contains(fn ($term) => str_contains($haystack, $term));
        });
    }

    private function editorialSection(mixed $editorial, mixed $banner): ?array
    {
        if ($editorial && filled($editorial->image_path)) {
            return $this->sectionData($editorial);
        }

        if ($banner && filled($banner->image_path)) {
            return $this->sectionData($banner);
        }

        return null;
    }

    private function sectionData(mixed $block): array
    {
        return [
            'block' => $block,
            'image' => media_url($block->image_path),
            'mobile_image' => media_url($block->mobile_image_path),
            'button_url' => $this->buttonUrl($block),
        ];
    }

    private function sectionDataWhenFilled(mixed $block): ?array
    {
        if (! $block || (! filled($block->title) && ! filled($block->content) && ! filled($block->image_path))) {
            return null;
        }

        return $this->sectionData($block);
    }

    private function instagramSection(mixed $block): ?array
    {
        if (! $block) {
            return null;
        }

        $items = $this->instagramFeed->latest();

        if ($items->isEmpty()) {
            $items = $this->instagramFallbackItems($block);
        }

        if ($items->isEmpty() && ! filled($block->title) && ! filled($block->content)) {
            return null;
        }

        return [
            'block' => $block,
            'items' => $items,
            'button_url' => $this->buttonUrl($block),
        ];
    }

    private function instagramFallbackItems(mixed $block): Collection
    {
        $items = collect($block->activeMedia ?? [])
            ->map(fn ($item) => [
                'type' => $item->media_type ?: 'image',
                'desktop' => media_url($item->desktop_path),
                'mobile' => media_url($item->mobile_path),
                'poster' => media_url($item->poster_path),
                'alt' => $item->alt_text ?: ($block->title ?: 'Instagram'),
                'permalink' => null,
                'source' => 'manual',
            ])
            ->filter(fn ($item) => filled($item['desktop']))
            ->values();

        if ($items->isEmpty() && filled($block->image_path)) {
            $items = collect([[
                'type' => filled($block->video_path) ? 'video' : 'image',
                'desktop' => media_url($block->video_path ?: $block->image_path),
                'mobile' => media_url($block->mobile_image_path),
                'poster' => media_url($block->image_path),
                'alt' => $block->title ?: 'Instagram',
                'permalink' => null,
                'source' => 'manual',
            ]]);
        }

        return $items;
    }

    private function buttonUrl(mixed $block): string
    {
        $url = trim((string) ($block?->button_url ?? ''));

        if ($url === '' || in_array($url, ['/catalog', 'catalog'], true)) {
            return route('storefront.catalog.index');
        }

        return str_starts_with($url, '/') ? url($url) : $url;
    }
}
