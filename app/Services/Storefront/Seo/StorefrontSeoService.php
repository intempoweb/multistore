<?php

namespace App\Services\Storefront\Seo;

use App\Models\Product;
use App\Models\Store;
use App\Models\StorefrontSeoEntry;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class StorefrontSeoService
{
    public function __construct(private ProductSeoGenerator $productGenerator)
    {
    }

    public static function categoryKey(array $path): string
    {
        return collect([
            !empty($path['fam']) ? 'fam:' . $path['fam'] : null,
            !empty($path['sfam']) ? 'sfam:' . $path['sfam'] : null,
            !empty($path['gruppo']) ? 'gruppo:' . $path['gruppo'] : null,
            !empty($path['sgruppo']) ? 'sgruppo:' . $path['sgruppo'] : null,
        ])->filter()->implode('|');
    }

    public function catalog(Store $store, string $locale): array
    {
        return $this->entry($store, $locale, 'catalog', 'catalog', [
            'title' => 'Catalogo | ' . $store->name,
            'description' => 'Scopri il catalogo ' . $store->name . '.',
        ]);
    }

    public function category(Store $store, string $locale, array $path, array $category): array
    {
        $label = trim((string) ($category['label'] ?? 'Categoria'));

        return $this->entry($store, $locale, 'collection', self::categoryKey($path), [
            'title' => $label . ' | ' . $store->name,
            'description' => 'Scopri la collezione ' . $label . ' di ' . $store->name . '.',
            'heading' => $label,
        ]);
    }

    public function product(Store $store, string $locale, Product $product, array $context = []): array
    {
        $translation = $product->translationOrFallback($locale);
        $generated = $this->productGenerator->generate(
            $product,
            $locale,
            $translation?->name,
            $translation?->short_description ?: $translation?->description
        );
        $title = $translation?->seo_title ?: $generated['seo_title'];
        $description = $translation?->seo_description ?: $generated['seo_description'];
        $images = collect($context['images'] ?? [])->filter()->values();
        $price = $context['price'] ?? $product->public_price ?? $product->effective_price;
        $stock = $context['stock'] ?? $product->stock_qty;
        $noBackorder = (bool) ($context['no_backorder'] ?? $product->no_backorder ?? false);
        $b2cThemeCodes = ['ciak', 'intemposhop', 'tekniko', 'ready'];
        $isB2bStore = (bool) ($store->is_b2b ?? false)
            && (int) ($product->site_type ?? $store->erp_site_code ?? 0) === 1
            && !in_array(trim((string) ($store->theme ?? '')), $b2cThemeCodes, true);
        $offerAvailability = match (true) {
            (float) $stock > 0 => 'https://schema.org/InStock',
            !$noBackorder => $isB2bStore ? 'https://schema.org/BackOrder' : 'https://schema.org/InStock',
            default => 'https://schema.org/OutOfStock',
        };

        return $this->base($store, $locale, [
            'title' => $title,
            'description' => $description,
            'og_image' => $images->first(),
            'og_type' => 'product',
            'json_ld' => [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $translation?->name ?: $product->sku,
                'description' => $description,
                'sku' => (string) $product->sku,
                'image' => $images->all(),
                'offers' => $price !== null ? [
                    '@type' => 'Offer',
                    'priceCurrency' => 'EUR',
                    'price' => number_format((float) $price, 2, '.', ''),
                    'availability' => $offerAvailability,
                    'url' => $this->canonicalUrl(),
                ] : null,
            ],
        ]);
    }

    private function entry(Store $store, string $locale, string $type, string $key, array $fallback): array
    {
        $entry = StorefrontSeoEntry::query()
            ->where('store_id', $store->id)
            ->where('locale', $locale)
            ->where('entity_type', $type)
            ->where('entity_key', $key)
            ->where('is_active', true)
            ->first();

        return $this->base($store, $locale, [
            'title' => $entry?->meta_title ?: ($fallback['title'] ?? $store->name),
            'description' => $entry?->meta_description ?: ($fallback['description'] ?? null),
            'heading' => $entry?->heading ?: ($fallback['heading'] ?? null),
            'intro' => $entry?->intro,
            'canonical' => $entry?->canonical_url,
            'robots' => $entry?->robots ?: 'index,follow',
            'og_title' => $entry?->og_title,
            'og_description' => $entry?->og_description,
            'og_image' => media_url($entry?->og_image_path),
        ]);
    }

    private function base(Store $store, string $locale, array $data): array
    {
        $canonical = $data['canonical'] ?? $this->canonicalUrl();
        $data['canonical'] = $canonical;
        $alternates = collect($store->supported_locales ?: [$store->default_locale ?: $locale])
            ->mapWithKeys(fn ($supportedLocale) => [
                $supportedLocale => LaravelLocalization::getLocalizedURL($supportedLocale, $canonical),
            ]);
        $defaultLocale = $store->default_locale ?: $locale;
        $alternates->put('x-default', LaravelLocalization::getLocalizedURL($defaultLocale, $canonical));

        return array_merge([
            'title' => $store->name,
            'description' => null,
            'canonical' => $canonical,
            'robots' => 'index,follow',
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'og_type' => 'website',
            'alternates' => $alternates->all(),
            'json_ld' => null,
        ], $data);
    }

    private function canonicalUrl(): string
    {
        return url()->current();
    }
}
