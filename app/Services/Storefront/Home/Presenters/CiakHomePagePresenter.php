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
        return ! $store->is_b2b && in_array(strtolower(trim((string) $store->theme)), [
            'ciak',
            'ready',
            'intemposhop',
            'teknikoshop',
            'tekniko',
        ], true);
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
            'aboutSection' => $this->aboutSection($about),
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
                'label' => __('themes_b2c.ciak.formats.diaries'),
                'category' => $agendaCategory,
                'items' => [
                    [
                        'label' => __('themes_b2c.ciak.formats.daily_agenda'),
                        'terms' => ['giornal'],
                        'image' => asset('images/themes/b2c/ciak/formats/agenda-giornaliera.png'),
                        'color_image' => asset('images/themes/b2c/ciak/formats/agenda-giornaliera-color.png'),
                        'detail_image' => asset('images/themes/b2c/ciak/formats/agenda-giornaliera-color.png'),
                        'description' => __('themes_b2c.ciak.formats.daily_agenda_description'),
                        'specs' => [__('themes_b2c.ciak.formats.daily_view'), __('themes_b2c.ciak.formats.note_space'), __('themes_b2c.ciak.formats.ideal_for_planning')],
                    ],
                    [
                        'label' => __('themes_b2c.ciak.formats.weekly_agenda'),
                        'terms' => ['settiman'],
                        'image' => asset('images/themes/b2c/ciak/formats/agenda-settimanale.png'),
                        'color_image' => asset('images/themes/b2c/ciak/formats/agenda-settimanale-color.png'),
                        'detail_image' => asset('images/themes/b2c/ciak/formats/agenda-settimanale-color.png'),
                        'description' => __('themes_b2c.ciak.formats.weekly_agenda_description'),
                        'specs' => [__('themes_b2c.ciak.formats.weekly_view'), __('themes_b2c.ciak.formats.compact'), __('themes_b2c.ciak.formats.perfect_in_bag')],
                    ],
                ],
            ],
            'taccuini' => [
                'label' => __('themes_b2c.ciak.formats.notebooks'),
                'category' => $notebookCategory,
                'items' => [
                    [
                        'label' => __('themes_b2c.ciak.formats.dotted_pages'),
                        'terms' => ['puntin'],
                        'image' => asset('images/themes/b2c/ciak/formats/taccuino-puntini.png'),
                        'color_image' => asset('images/themes/b2c/ciak/formats/taccuino-puntini-color.png'),
                        'detail_image' => asset('images/themes/b2c/ciak/formats/taccuino-puntini-color.png'),
                        'description' => __('themes_b2c.ciak.formats.dotted_pages_description'),
                        'specs' => [__('themes_b2c.ciak.formats.dot_grid'), __('themes_b2c.ciak.formats.free_drawing'), __('themes_b2c.ciak.formats.max_flexibility')],
                    ],
                    [
                        'label' => __('themes_b2c.ciak.formats.lined_pages'),
                        'terms' => ['righe'],
                        'image' => asset('images/themes/b2c/ciak/formats/taccuino-righe.png'),
                        'color_image' => asset('images/themes/b2c/ciak/formats/taccuino-righe-color.png'),
                        'detail_image' => asset('images/themes/b2c/ciak/formats/taccuino-righe-color.png'),
                        'description' => __('themes_b2c.ciak.formats.lined_pages_description'),
                        'specs' => [__('themes_b2c.ciak.formats.light_lines'), __('themes_b2c.ciak.formats.guided_writing'), __('themes_b2c.ciak.formats.daily_use')],
                    ],
                    [
                        'label' => __('themes_b2c.ciak.formats.blank_pages'),
                        'terms' => ['bianch', 'vuote'],
                        'image' => asset('images/themes/b2c/ciak/formats/taccuino-pagine-bianche.png'),
                        'color_image' => asset('images/themes/b2c/ciak/formats/taccuino-pagine-bianche-color.png'),
                        'detail_image' => asset('images/themes/b2c/ciak/formats/taccuino-pagine-bianche-color.png'),
                        'description' => __('themes_b2c.ciak.formats.blank_pages_description'),
                        'specs' => [__('themes_b2c.ciak.formats.neutral_pages'), __('themes_b2c.ciak.formats.sketch_notes'), __('themes_b2c.ciak.formats.total_creativity')],
                    ],
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
                        'color_image' => $item['color_image'] ?? $item['detail_image'] ?? $item['image'],
                        'detail_image' => $item['detail_image'],
                        'description' => $item['description'],
                        'specs' => $item['specs'],
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

    private function aboutSection(mixed $block): array
    {
        $section = $this->sectionDataWhenFilled($block);

        if ($section) {
            return $section;
        }

        return $this->sectionData((object) [
            'subtitle' => __('themes_b2c.ciak.story'),
            'title' => __('themes_b2c.ciak.about'),
            'content' => __('themes_b2c.ciak.about_fallback'),
            'button_label' => null,
            'button_url' => null,
            'button_new_tab' => false,
            'image_path' => null,
            'mobile_image_path' => null,
        ]);
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

        $items = $this->instagramFeed->latest(36);

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
