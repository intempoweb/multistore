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
        return $store->isB2C() && in_array(strtolower(trim((string) $store->theme)), [
            'ciak',
            'ready',
        ], true);
    }

    public function present(HomePageInput $input): array
    {
        $hero = $this->block($input->storefrontPageBlocks, ['hero'], ['home_hero']);
        $aboutIntro = $this->block($input->storefrontPageBlocks, ['section_intro'], ['home_about_intro']);
        $about = $this->block($input->storefrontPageBlocks, ['about'], ['home_about', 'chi_siamo']);
        $editorial = $this->block($input->storefrontPageBlocks, ['editorial'], ['home_story']);
        $banner = $this->block($input->storefrontPageBlocks, ['editorial_banner'], ['home_banner']);
        $vision = $this->block($input->storefrontPageBlocks, ['vision'], ['home_vision']);
        $valuesIntro = $this->block($input->storefrontPageBlocks, ['section_intro'], ['home_values_intro']);
        $instagram = $this->block($input->storefrontPageBlocks, ['instagram_gallery', 'gallery'], ['home_instagram', 'instagram']);
        $products = collect(method_exists($input->products, 'items') ? $input->products->items() : $input->products);
        $featured = $products->filter(fn ($product) => (bool) ($product->flgnovita_webt01 ?? false))->take(4);
        $aboutSection = $this->aboutSection($about);
        $visionSection = $this->sectionDataWhenFilled($vision);
        $isCiakTheme = strtolower(trim((string) $input->store->theme)) === 'ciak';

        if ($featured->isEmpty()) {
            $featured = $products->shuffle()->take(4);
        }

        $agendaCategory = $this->findCategory($input->rootCategories, ['agenda']);
        $notebookCategory = $this->findCategory($input->rootCategories, ['taccuin', 'quadern']);
        $formatGroups = $this->formatGroups(
            $input->rootCategories,
            $agendaCategory,
            $notebookCategory,
            $input->storefrontPageBlocks,
            $input->locale,
        );

        return [
            'hero' => $hero,
            'heroButtonUrl' => $this->buttonUrl($hero),
            'heroMedia' => $this->heroMedia($hero),
            'aboutSection' => $aboutSection,
            ...$this->aboutVisionData($aboutIntro, $aboutSection, $visionSection, $valuesIntro, $input->storefrontPageBlocks),
            'formatGroups' => $formatGroups,
            'formatItems' => $formatGroups->flatMap(fn ($group) => collect($group['items']))->values(),
            'featuredRows' => $featured->map(fn ($product) => [
                'product' => $product,
                'listingCard' => collect($input->listingCardsByProductSku->get((string) $product->sku, [])),
            ])->values(),
            'editorialSection' => $this->editorialSection($editorial, $banner),
            'visionSection' => $visionSection,
            'instagramSection' => $isCiakTheme ? $this->instagramSection($instagram, $input->store) : null,
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

    private function formatGroups(Collection $categories, mixed $agendaCategory, mixed $notebookCategory, Collection $blocks, string $locale): Collection
    {
        $formatBlocks = $blocks
            ->filter(fn ($block) => (string) ($block->type ?? '') === 'format' || str_starts_with((string) ($block->name ?? ''), 'home_format_'))
            ->keyBy(fn ($block) => (string) $block->name);

        return collect([
            'agende' => [
                'label' => __('themes_b2c.ciak.formats.diaries'),
                'category' => $agendaCategory,
                'items' => [
                    [
                        'name' => 'home_format_daily',
                        'key' => 'daily',
                        'label' => __('themes_b2c.ciak.formats.daily_agenda'),
                        'terms' => ['giornal'],
                        'image' => b2c_theme_asset_url('ciak/formats/agenda-giornaliera.png'),
                        'outline_image' => b2c_theme_asset_url('ciak/formats/agenda-giornaliera-outline.svg'),
                        'color_image' => b2c_theme_asset_url('ciak/formats/agenda-giornaliera-color.png'),
                        'detail_image' => b2c_theme_asset_url('ciak/formats/agenda-giornaliera-color.png'),
                        'description' => __('themes_b2c.ciak.formats.daily_agenda_description'),
                        'specs' => [__('themes_b2c.ciak.formats.daily_view'), __('themes_b2c.ciak.formats.note_space'), __('themes_b2c.ciak.formats.ideal_for_planning')],
                    ],
                    [
                        'name' => 'home_format_weekly',
                        'key' => 'weekly',
                        'label' => __('themes_b2c.ciak.formats.weekly_agenda'),
                        'terms' => ['settiman'],
                        'image' => b2c_theme_asset_url('ciak/formats/agenda-settimanale.png'),
                        'outline_image' => b2c_theme_asset_url('ciak/formats/agenda-settimanale-outline.svg'),
                        'color_image' => b2c_theme_asset_url('ciak/formats/agenda-settimanale-color.png'),
                        'detail_image' => b2c_theme_asset_url('ciak/formats/agenda-settimanale-color.png'),
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
                        'name' => 'home_format_dotted',
                        'key' => 'dotted',
                        'label' => __('themes_b2c.ciak.formats.dotted_pages'),
                        'terms' => ['puntin'],
                        'image' => b2c_theme_asset_url('ciak/formats/taccuino-puntini.png'),
                        'outline_image' => b2c_theme_asset_url('ciak/formats/taccuino-puntini-outline.svg'),
                        'color_image' => b2c_theme_asset_url('ciak/formats/taccuino-puntini-color.png'),
                        'detail_image' => b2c_theme_asset_url('ciak/formats/taccuino-puntini-color.png'),
                        'description' => __('themes_b2c.ciak.formats.dotted_pages_description'),
                        'specs' => [__('themes_b2c.ciak.formats.dot_grid'), __('themes_b2c.ciak.formats.free_drawing'), __('themes_b2c.ciak.formats.max_flexibility')],
                    ],
                    [
                        'name' => 'home_format_lined',
                        'key' => 'lined',
                        'label' => __('themes_b2c.ciak.formats.lined_pages'),
                        'terms' => ['righe'],
                        'image' => b2c_theme_asset_url('ciak/formats/taccuino-righe.png'),
                        'outline_image' => b2c_theme_asset_url('ciak/formats/taccuino-righe-outline.svg'),
                        'color_image' => b2c_theme_asset_url('ciak/formats/taccuino-righe-color.png'),
                        'detail_image' => b2c_theme_asset_url('ciak/formats/taccuino-righe-color.png'),
                        'description' => __('themes_b2c.ciak.formats.lined_pages_description'),
                        'specs' => [__('themes_b2c.ciak.formats.light_lines'), __('themes_b2c.ciak.formats.guided_writing'), __('themes_b2c.ciak.formats.daily_use')],
                    ],
                    [
                        'name' => 'home_format_blank',
                        'key' => 'blank',
                        'label' => __('themes_b2c.ciak.formats.blank_pages'),
                        'terms' => ['bianch', 'vuote'],
                        'image' => b2c_theme_asset_url('ciak/formats/taccuino-pagine-bianche.png'),
                        'outline_image' => b2c_theme_asset_url('ciak/formats/taccuino-pagine-bianche-outline.svg'),
                        'color_image' => b2c_theme_asset_url('ciak/formats/taccuino-pagine-bianche-color.png'),
                        'detail_image' => b2c_theme_asset_url('ciak/formats/taccuino-pagine-bianche-color.png'),
                        'description' => __('themes_b2c.ciak.formats.blank_pages_description'),
                        'specs' => [__('themes_b2c.ciak.formats.neutral_pages'), __('themes_b2c.ciak.formats.sketch_notes'), __('themes_b2c.ciak.formats.total_creativity')],
                    ],
                ],
            ],
        ])->map(function ($group, $key) use ($categories, $formatBlocks, $locale) {
                $group['key'] = $key;
                $group['available'] = (bool) $group['category'];
                $group['items'] = collect($group['items'])->map(function ($item) use ($categories, $group, $formatBlocks, $locale) {
                    $target = $this->findCategory($categories, $item['terms']) ?: $group['category'];
                    $block = $formatBlocks->get($item['name']);
                    $buttonUrl = $block && filled($block->button_url) ? $this->buttonUrl($block) : null;
                    $categoryUrl = $target ? route('storefront.category.show', $target['slug']) : null;
                    $url = $buttonUrl ?: $categoryUrl;
                    $image = filled($block?->image_path) ? media_url($block->image_path) : $item['image'];
                    $specs = $this->blockSpecs($block, $locale);

                    return [
                        'key' => $item['key'],
                        'label' => $block?->title ?: $item['label'],
                        'group' => $block?->subtitle ?: $group['label'],
                        'group_key' => $group['key'],
                        'available' => filled($url),
                        'image' => $image,
                        'outline_image' => $item['outline_image'] ?? $item['image'],
                        'color_image' => filled($block?->image_path) ? $image : ($item['color_image'] ?? $item['detail_image'] ?? $item['image']),
                        'detail_image' => filled($block?->image_path) ? $image : $item['detail_image'],
                        'description' => $block?->content ?: $item['description'],
                        'specs' => $specs ?: $item['specs'],
                        'paper_label' => __('themes_b2c.ciak.formats.ivory_paper'),
                        'paper_color' => '#f3ead8',
                        'button_label' => $block?->button_label ?: ($item['button_label'] ?? __('themes_b2c.ciak.discover_selection')),
                        'url' => $url,
                    ];
                })->filter(fn ($item) => filled($item['label']))->values();

                return $group;
            });
    }

    private function blockSpecs(mixed $block, string $locale): array
    {
        $settings = is_array($block?->settings ?? null) ? $block->settings : [];
        $specs = data_get($settings, 'specs.' . strtolower($locale));

        if ($specs === null) {
            $specs = data_get($settings, 'specs');
        }

        if (is_string($specs)) {
            $specs = preg_split('/\r\n|\r|\n/', $specs) ?: [];
        }

        if (! is_array($specs)) {
            return [];
        }

        if (! array_is_list($specs)) {
            return [];
        }

        return collect($specs)
            ->map(fn ($spec) => trim((string) $spec))
            ->filter()
            ->values()
            ->all();
    }

    private function findCategory(Collection $categories, array $terms): mixed
    {
        return $categories->first(function ($category) use ($terms) {
            $haystack = mb_strtolower(trim(($category['label'] ?? '').' '.($category['slug'] ?? '')));

            return collect($terms)->contains(fn ($term) => str_contains($haystack, $term));
        });
    }

    private function aboutVisionData(mixed $aboutIntro, array $aboutSection, ?array $visionSection, mixed $valuesIntro, Collection $blocks): array
    {
        return [
            'aboutVisionPanels' => collect([
                $aboutSection ? [
                    'key' => 'about',
                    'label' => __('themes_b2c.ciak.about'),
                    'number' => '01',
                    'fallback_title' => __('themes_b2c.ciak.about'),
                    'section' => $aboutSection,
                ] : null,
                $visionSection ? [
                    'key' => 'vision',
                    'label' => __('themes_b2c.ciak.vision'),
                    'number' => '02',
                    'fallback_title' => __('themes_b2c.ciak.vision'),
                    'section' => $visionSection,
                ] : null,
            ])->filter()->values(),
            'aboutVisionHeading' => $aboutIntro?->title,
            'aboutVisionIntro' => $aboutIntro?->content,
            'aboutVisionEyebrow' => $aboutIntro?->subtitle,
            'aboutBody' => $aboutSection['block']->content ?? null,
            'visionBody' => $visionSection['block']->content ?? null,
            'valuesTitle' => $valuesIntro?->title,
            'aboutHighlights' => $this->highlightRows($blocks, 'about_highlight'),
            'visionHighlights' => $this->highlightRows($blocks, 'vision_highlight'),
            'values' => $this->highlightRows($blocks, 'value'),
            'aboutCtaUrl' => $aboutSection['button_url'] ?? route('storefront.about'),
            'visionCtaUrl' => $visionSection['button_url'] ?? route('storefront.vision'),
            'aboutImage' => $aboutSection['image'] ?? b2c_theme_asset_url('ciak/formats/taccuino-puntini-color.png'),
            'aboutMobileImage' => $aboutSection['mobile_image'] ?? null,
            'aboutImageAlt' => $aboutSection['image_alt'] ?? ($aboutSection['block']->title ?? ''),
            'visionImage' => $visionSection['image'] ?? ($aboutSection['image'] ?? b2c_theme_asset_url('ciak/formats/taccuino-puntini-color.png')),
            'visionMobileImage' => $visionSection['mobile_image'] ?? ($aboutSection['mobile_image'] ?? null),
            'visionImageAlt' => $visionSection['image_alt'] ?? ($visionSection['block']->title ?? ''),
        ];
    }

    private function highlightRows(Collection $blocks, string $type): Collection
    {
        return $blocks
            ->filter(fn ($block) => (string) ($block->type ?? '') === $type)
            ->map(fn ($block) => [
                'icon' => $block->subtitle ?: 'circle',
                'title' => $block->title,
                'text' => $block->content,
            ])
            ->filter(fn ($item) => filled($item['title']) || filled($item['text']))
            ->values();
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
            'image_alt' => $this->blockImageAlt($block),
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

    private function instagramSection(mixed $block, Store $store): ?array
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

        $displayBlock = $block;

        if (strtolower(trim((string) $store->theme)) === 'ciak') {
            $displayBlock = clone $block;
            $displayBlock->subtitle = __('themes_b2c.ciak.instagram_eyebrow');
            $displayBlock->title = __('themes_b2c.ciak.instagram_title');
            $displayBlock->content = __('themes_b2c.ciak.instagram_intro');
            $displayBlock->button_label = __('themes_b2c.ciak.instagram_button');
        }

        return [
            'block' => $displayBlock,
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
                'alt' => $this->blockImageAlt($block, 'Instagram'),
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

    private function blockImageAlt(mixed $block, string $fallback = ''): string
    {
        $settings = is_array($block?->settings ?? null) ? $block->settings : [];
        $alt = trim((string) data_get($settings, 'image_alt', ''));

        if ($alt !== '') {
            return $alt;
        }

        return trim((string) ($block?->title ?: $fallback));
    }
}
