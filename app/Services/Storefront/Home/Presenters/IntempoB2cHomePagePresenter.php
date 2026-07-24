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
        $isReady = strtolower(trim((string) $input->store->theme)) === 'ready';
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

        $aboutSection = $this->aboutSection($about, $isReady, $contextParams);

        return [
            'hero' => $this->displayHero($hero, $isReady),
            'heroButtonUrl' => $this->buttonUrl($hero, $contextParams),
            'heroMedia' => $this->heroMedia($hero, $isReady),
            'aboutSection' => $aboutSection,
            'catalogueUrl' => route('storefront.catalog.index', $contextParams),
            'locatorUrl' => route('storefront.store-locator.index', $contextParams),
            'storyTitle' => $aboutSection['block']->title ?: __('themes_b2c.intempo.about_us'),
            'storyContent' => $this->cleanText($aboutSection['block']->content, __('themes_b2c.intempo.story_intro')),
            'intempoAreas' => $this->intempoAreas($homeCategories, $contextParams, $isReady),
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

    private function displayHero(mixed $hero, bool $isReady = false): object
    {
        $display = $hero ? clone $hero : (object) [];

        $display->subtitle = $this->cleanText($display->subtitle ?? null, $isReady ? 'Ready' : __('themes_b2c.intempo.hero_eyebrow'));
        $display->title = $this->cleanText($display->title ?? null, $isReady ? 'Plein Air' : __('themes_b2c.intempo.hero_title'));
        $display->content = $this->cleanText($display->content ?? null, $isReady ? "Vivi l'outdoor senza pensieri" : __('themes_b2c.intempo.hero_intro'));
        $display->button_label = $this->cleanText($display->button_label ?? null, $isReady ? 'Acquista ora' : __('themes_b2c.intempo.discover_collection'));
        $display->button_url = $display->button_url ?? '/catalog';
        $display->button_new_tab = (bool) ($display->button_new_tab ?? false);

        return $display;
    }

    private function aboutSection(mixed $about, bool $isReady = false, array $contextParams = []): array
    {
        $block = $about ? clone $about : (object) [];
        $block->subtitle = $this->cleanText($block->subtitle ?? null, $isReady ? 'Be smart, be ready' : __('themes_b2c.intempo.about_us'));
        $block->title = $this->cleanText($block->title ?? null, $isReady ? 'Accessori per la tua vita in movimento' : __('themes_b2c.intempo.about_us'));
        $block->content = $this->cleanText($block->content ?? null, $isReady ? "Se sei sempre in movimento, hai bisogno di accessori che siano pronti quanto te. Ready e' il brand di accessori smart e funzionali, progettati per semplificarti la vita, senza rinunciare allo stile. La nostra filosofia si basa su linee pulite, materiali resistenti e un design intuitivo che risponde con prontezza a ogni esigenza. Scegli l'innovazione e la praticita'!" : __('themes_b2c.intempo.story_intro'));
        $block->button_label = $this->cleanText($block->button_label ?? null, $isReady ? 'Scopri Ready' : __('themes_b2c.intempo.explore_intempo_world'));
        $block->button_url = $block->button_url ?? '/about';

        return [
            'block' => $block,
            'image' => media_url($block->image_path ?? null),
            'mobile_image' => media_url($block->mobile_image_path ?? null),
            'image_alt' => $this->blockImageAlt($block),
            'button_url' => $this->buttonUrl($block, $contextParams),
        ];
    }

    private function intempoAreas(Collection $categories, array $contextParams, bool $isReady = false): Collection
    {
        if ($isReady) {
            return collect([
                [
                    'label' => 'Collezione',
                    'title' => 'Tempo libero',
                    'content' => 'Accessori leggeri e compatti per giornate dinamiche.',
                    'icon' => null,
                    'url' => $this->findCategoryUrl($categories, ['tempo libero', 'lifestyle', 'accessor'], $contextParams),
                ],
                [
                    'label' => 'Collezione',
                    'title' => 'Sport',
                    'content' => "Soluzioni pratiche per muoversi con ordine e liberta'.",
                    'icon' => null,
                    'url' => $this->findCategoryUrl($categories, ['sport', 'dynamo', 'borsa sport'], $contextParams),
                ],
                [
                    'label' => 'Collezione',
                    'title' => 'Outdoor',
                    'content' => "Prodotti antipioggia e accessori pronti per l'aria aperta.",
                    'icon' => null,
                    'url' => $this->findCategoryUrl($categories, ['outdoor', 'plein', 'antipioggia', 'poncho'], $contextParams),
                ],
            ]);
        }

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

    private function heroMedia(mixed $hero, bool $isReady = false): Collection
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

        if ($media->isEmpty() && $isReady) {
            return collect([[
                'type' => 'image',
                'desktop' => 'https://ready-to.it/wp-content/uploads/2026/04/ready-_plein-air_banner-desktop-1.jpg',
                'mobile' => 'https://ready-to.it/wp-content/uploads/2026/04/banner1_plein-air_mobile.jpg',
                'poster' => null,
                'alt' => 'Ready Plein Air',
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

    private function buttonUrl(mixed $block, array $contextParams = []): string
    {
        $url = trim((string) ($block?->button_url ?? ''));

        if ($url === '' || in_array($url, ['/catalog', 'catalog'], true)) {
            return route('storefront.catalog.index', $contextParams);
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
