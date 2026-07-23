<?php

namespace App\Services\Storefront\Home\Presenters;

use App\Data\Storefront\HomePageInput;
use App\Models\Store;
use App\Services\Storefront\Home\HomePagePresenter;
use Illuminate\Support\Collection;

final class IntempoB2cHomePagePresenter implements HomePagePresenter
{
    private const LEGACY_CIAK_TEXT = [
        'CIAK Firenze',
        'Agende e taccuini per ogni giorno',
        'Oggetti quotidiani per scrivere, pianificare e portare con te le idee.',
        'Dal cuore di Firenze, CIAK crea agende e taccuini pensati per accompagnare idee, progetti e giornate piene di dettagli.',
        'Ciak celebra la bellezza della carta e la trasforma in esperienze di valore. Ogni prodotto nasce da attenzione, ricerca e passione artigianale.',
        'Scopri chi siamo',
    ];

    public function supports(Store $store): bool
    {
        return $store->isB2C() && in_array(strtolower(trim((string) $store->theme)), [
            'intemposhop',
            'ready',
        ], true);
    }

    public function present(HomePageInput $input): array
    {
        $hero = $this->block($input->storefrontPageBlocks, ['hero'], ['home_hero']);
        $about = $this->block($input->storefrontPageBlocks, ['about'], ['home_about']);
        $products = collect(method_exists($input->products, 'items') ? $input->products->items() : $input->products);
        $featured = $products->filter(fn ($product) => (bool) ($product->flgnovita_webt01 ?? false))->take(4);

        if ($featured->isEmpty()) {
            $featured = $products->shuffle()->take(4);
        }

        $contextId = (string) $input->request->input('agent_context', '');
        $contextParams = $contextId !== '' ? ['agent_context' => $contextId] : [];
        $homeCategories = $input->rootCategories
            ->filter(fn ($category) => filled($category['label'] ?? null) && filled($category['slug'] ?? null))
            ->values();

        $aboutSection = $this->aboutSection($about);

        return [
            'hero' => $this->displayHero($hero),
            'heroButtonUrl' => $this->buttonUrl($hero),
            'heroMedia' => $this->heroMedia($hero),
            'aboutSection' => $aboutSection,
            'catalogueUrl' => route('storefront.catalog.index', $contextParams),
            'locatorUrl' => route('storefront.store-locator.index', $contextParams),
            'storyTitle' => $aboutSection['block']->title ?: __('themes_b2c.intempo.about_us'),
            'storyContent' => $this->cleanText($aboutSection['block']->content, __('themes_b2c.intempo.story_intro')),
            'intempoAreas' => $this->intempoAreas($homeCategories, $contextParams),
            'featuredRows' => $featured->map(fn ($product) => [
                'product' => $product,
                'listingCard' => collect($input->listingCardsByProductSku->get((string) $product->sku, [])),
            ])->values(),
        ];
    }

    private function block(Collection $blocks, array $types, array $names): mixed
    {
        return $blocks->first(fn ($block) => in_array($block->type, $types, true) || in_array($block->name, $names, true));
    }

    private function displayHero(mixed $hero): object
    {
        $display = $hero ? clone $hero : (object) [];

        $display->subtitle = $this->cleanText($display->subtitle ?? null, __('themes_b2c.intempo.hero_eyebrow'));
        $display->title = $this->cleanText($display->title ?? null, __('themes_b2c.intempo.hero_title'));
        $display->content = $this->cleanText($display->content ?? null, __('themes_b2c.intempo.hero_intro'));
        $display->button_label = $this->cleanText($display->button_label ?? null, __('themes_b2c.intempo.discover_collection'));
        $display->button_url = $display->button_url ?? '/catalog';
        $display->button_new_tab = (bool) ($display->button_new_tab ?? false);

        return $display;
    }

    private function aboutSection(mixed $about): array
    {
        $block = $about ? clone $about : (object) [];
        $block->subtitle = $this->cleanText($block->subtitle ?? null, __('themes_b2c.intempo.about_us'));
        $block->title = $this->cleanText($block->title ?? null, __('themes_b2c.intempo.about_us'));
        $block->content = $this->cleanText($block->content ?? null, __('themes_b2c.intempo.story_intro'));
        $block->button_label = $this->cleanText($block->button_label ?? null, __('themes_b2c.intempo.explore_intempo_world'));
        $block->button_url = $block->button_url ?? '/about';

        return [
            'block' => $block,
            'image' => media_url($block->image_path ?? null),
            'mobile_image' => media_url($block->mobile_image_path ?? null),
            'image_alt' => $this->blockImageAlt($block),
            'button_url' => $this->buttonUrl($block),
        ];
    }

    private function intempoAreas(Collection $categories, array $contextParams): Collection
    {
        return collect([
            [
                'label' => __('themes_b2c.intempo.areas_diaries_label'),
                'title' => __('themes_b2c.intempo.areas_diaries_title'),
                'content' => __('themes_b2c.intempo.areas_diaries_content'),
                'icon' => b2c_theme_asset_url('intempo/icons/intempo-diaries-icons.png'),
                'url' => $this->findCategoryUrl($categories, ['diar', 'agenda', 'agende'], $contextParams),
            ],
            [
                'label' => __('themes_b2c.intempo.areas_lifestyle_label'),
                'title' => __('themes_b2c.intempo.areas_lifestyle_title'),
                'content' => __('themes_b2c.intempo.areas_lifestyle_content'),
                'icon' => b2c_theme_asset_url('intempo/icons/intempo-pelletteria-icons.png'),
                'url' => $this->findCategoryUrl($categories, ['lifestyle', 'pelletter', 'accessor'], $contextParams),
            ],
            [
                'label' => __('themes_b2c.intempo.areas_home_office_label'),
                'title' => __('themes_b2c.intempo.areas_home_office_title'),
                'content' => __('themes_b2c.intempo.areas_home_office_content'),
                'icon' => b2c_theme_asset_url('intempo/icons/intempo-home-office-icons.png'),
                'url' => $this->findCategoryUrl($categories, ['home', 'office', 'ufficio', 'arredo', 'casa'], $contextParams),
            ],
        ]);
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

    private function findCategoryUrl(Collection $categories, array $terms, array $contextParams): string
    {
        $category = $categories->first(function ($category) use ($terms) {
            $haystack = mb_strtolower(trim((string) (($category['label'] ?? '').' '.($category['slug'] ?? '').' '.($category['description'] ?? ''))));

            return collect($terms)->contains(fn ($term) => str_contains($haystack, $term));
        });

        return $category && filled($category['slug'] ?? null)
            ? route('storefront.category.show', array_merge(['slug' => $category['slug']], $contextParams))
            : route('storefront.catalog.index', $contextParams);
    }

    private function buttonUrl(mixed $block): string
    {
        $url = trim((string) ($block?->button_url ?? ''));

        if ($url === '' || in_array($url, ['/catalog', 'catalog'], true)) {
            return route('storefront.catalog.index');
        }

        return str_starts_with($url, '/') ? url($url) : $url;
    }

    private function cleanText(mixed $value, string $fallback): string
    {
        $text = trim((string) $value);

        return $text === '' || in_array($text, self::LEGACY_CIAK_TEXT, true) ? $fallback : $text;
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
