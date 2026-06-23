<?php

namespace App\Services\Storefront\Home\Presenters;

use App\Data\Storefront\HomePageInput;
use App\Models\Store;
use App\Services\Storefront\Home\HomePagePresenter;
use Illuminate\Support\Collection;

final class CiakHomePagePresenter implements HomePagePresenter
{
    public function supports(Store $store): bool
    {
        return ! $store->is_b2b && strtolower(trim((string) $store->theme)) === 'ciak';
    }

    public function present(HomePageInput $input): array
    {
        $hero = $this->block($input->storefrontPageBlocks, ['hero'], ['home_hero']);
        $editorial = $this->block($input->storefrontPageBlocks, ['editorial'], ['home_story']);
        $banner = $this->block($input->storefrontPageBlocks, ['editorial_banner'], ['home_banner']);
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
            'formatGroups' => $this->formatGroups($input->rootCategories, $agendaCategory, $notebookCategory),
            'featuredRows' => $featured->map(fn ($product) => [
                'product' => $product,
                'listingCard' => collect($input->listingCardsByProductSku->get((string) $product->sku, [])),
            ])->values(),
            'editorialSection' => $this->editorialSection($editorial, $banner),
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
                    ['label' => __('Agenda giornaliera'), 'terms' => ['giornal'], 'image' => asset('images/themes/b2c/ciak/formats/agenda-giornaliera.jpg')],
                    ['label' => __('Agenda settimanale'), 'terms' => ['settiman'], 'image' => asset('images/themes/b2c/ciak/formats/agenda-settimanale.jpg')],
                ],
            ],
            'taccuini' => [
                'label' => __('Taccuini'),
                'category' => $notebookCategory,
                'items' => [
                    ['label' => __('Pagine a puntini'), 'terms' => ['puntin'], 'image' => asset('images/themes/b2c/ciak/formats/taccuino-puntini.jpg')],
                    ['label' => __('Pagine a righe'), 'terms' => ['righe'], 'image' => asset('images/themes/b2c/ciak/formats/taccuino-righe.jpg')],
                    ['label' => __('Pagine bianche'), 'terms' => ['bianch', 'vuote'], 'image' => asset('images/themes/b2c/ciak/formats/taccuino-pagine-bianche.jpg')],
                ],
            ],
        ])->filter(fn ($group) => (bool) $group['category'])
            ->map(function ($group) use ($categories) {
                $group['items'] = collect($group['items'])->map(function ($item) use ($categories, $group) {
                    $target = $this->findCategory($categories, $item['terms']) ?: $group['category'];

                    return [
                        'label' => $item['label'],
                        'image' => $item['image'],
                        'url' => route('storefront.category.show', $target['slug']),
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

    private function buttonUrl(mixed $block): string
    {
        $url = trim((string) ($block?->button_url ?? ''));

        if ($url === '' || in_array($url, ['/catalog', 'catalog'], true)) {
            return route('storefront.catalog.index');
        }

        return str_starts_with($url, '/') ? url($url) : $url;
    }
}
