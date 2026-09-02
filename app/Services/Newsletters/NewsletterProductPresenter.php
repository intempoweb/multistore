<?php

namespace App\Services\Newsletters;

use App\Models\Newsletter;
use App\Models\Product;

class NewsletterProductPresenter
{
    public function __construct(
        private NewsletterPriceResolver $priceResolver,
        private ProductUrlResolver $urlResolver,
    ) {
    }

    public function present(Newsletter $newsletter, Product $product): array
    {
        $product->loadMissing(['translations', 'mediaAssets', 'parent.mediaAssets']);

        $translation = $product->translationOrFallback($newsletter->locale);
        $price = $this->priceResolver->resolve($product, $newsletter->listino_id);

        return [
            'sku' => (string) $product->sku,
            'name' => $translation?->name ?: (string) $product->sku,
            'description' => $this->shortDescription($translation?->description),
            'type' => (string) $product->type,
            'parent_code' => $product->parent_code,
            'image_url' => $product->mainImage()?->url ?: $product->parent?->mainImage()?->url,
            'url' => $this->urlResolver->resolve($product, $newsletter->store, $newsletter->locale),
            'price' => $price['price'],
            'price_label' => $price['price'] !== null
                ? number_format((float) $price['price'], $newsletter->store?->priceDecimals() ?? 3, ',', '.') . ' EUR'
                : null,
            'price_source' => $price['source'],
            'badges' => $this->badges($product),
        ];
    }

    private function shortDescription(?string $description): ?string
    {
        $text = trim(strip_tags((string) $description));

        return $text !== '' ? mb_substr($text, 0, 180) : null;
    }

    private function badges(Product $product): array
    {
        $badges = [];

        if ($product->flgnovita_webt01) {
            $badges[] = 'Novita';
        }

        if ($product->flgpromo_webt01) {
            $badges[] = 'Promozione';
        }

        if ($product->flgofferta_webt01) {
            $badges[] = 'Offerta';
        }

        if ($product->flgcampagna_webt01) {
            $badges[] = 'Campagna';
        }

        return $badges;
    }
}
