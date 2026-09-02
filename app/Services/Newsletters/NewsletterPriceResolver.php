<?php

namespace App\Services\Newsletters;

use App\Models\PriceTier;
use App\Models\Product;

class NewsletterPriceResolver
{
    public function resolve(Product $product, ?int $listinoId, int|float $qty = 1): array
    {
        $ditta = (int) $product->ditta_cg18;
        $sku = trim((string) $product->sku);

        if ($ditta > 0 && $listinoId !== null && $listinoId > 0 && $sku !== '') {
            $tier = PriceTier::query()
                ->forProduct($ditta, $listinoId, $sku)
                ->forQuantity(max(1, (float) $qty))
                ->first();

            if ($tier instanceof PriceTier && is_numeric($tier->price_net) && (float) $tier->price_net > 0) {
                return [
                    'price' => (float) $tier->price_net,
                    'source' => 'listino',
                    'listino_id' => $listinoId,
                    'qty_from' => (float) $tier->qty_from,
                ];
            }
        }

        if (filter_var(config('newsletters.fallback_to_public_price', true), FILTER_VALIDATE_BOOLEAN)) {
            $publicPrice = $this->publicPrice($product);

            if ($publicPrice !== null) {
                return [
                    'price' => $publicPrice,
                    'source' => 'public_price',
                    'listino_id' => $product->public_price_listino_id,
                    'qty_from' => 1.0,
                ];
            }
        }

        return [
            'price' => null,
            'source' => null,
            'listino_id' => $listinoId,
            'qty_from' => null,
        ];
    }

    private function publicPrice(Product $product): ?float
    {
        foreach ([$product->public_price, $product->effective_price] as $candidate) {
            if (is_numeric($candidate) && (float) $candidate > 0) {
                return (float) $candidate;
            }
        }

        return null;
    }
}
