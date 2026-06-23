<?php

namespace App\Services\Storefront\Catalog;

use Illuminate\Support\Collection;

final class ProductListingCardDataFactory
{
    public function forProducts(iterable $products): Collection
    {
        return collect($products)->mapWithKeys(fn ($product) => [
            (string) $product->sku => $this->forProduct($product),
        ]);
    }

    public function forProduct(mixed $product): array
    {
        $variantOptions = collect($product->listing_variant_options ?? []);
        $targetSku = $this->targetSku($product, $variantOptions);

        $selectedVariant = $variantOptions->first(
            fn ($variant) => is_array($variant) && (string) ($variant['sku'] ?? '') === $targetSku
        ) ?? $variantOptions->first(fn ($variant) => is_array($variant));

        $selectedVariant = is_array($selectedVariant) ? $selectedVariant : [];

        return [
            'target_sku' => $targetSku,
            'image' => $selectedVariant['image'] ?? $product->main_image_url ?? null,
            'hover_image' => $selectedVariant['hover_image'] ?? $product->listing_hover_image_url ?? null,
            'price' => $selectedVariant['price']
                ?? $selectedVariant['effective_price']
                ?? $product->effective_price
                ?? $product->public_price
                ?? null,
            'selected_color_value' => $selectedVariant['color']['value']
                ?? $product->listing_selected_color_value
                ?? null,
            'selected_format_value' => $selectedVariant['format']['value']
                ?? $product->listing_selected_format_value
                ?? null,
            'price_payload' => null,
            'price_breaks' => collect(),
        ];
    }

    private function targetSku(mixed $product, Collection $variantOptions): string
    {
        $targetSku = (string) ($product->listing_target_sku ?? $product->sku);
        $targetExists = $variantOptions->contains(
            fn ($variant) => is_array($variant) && (string) ($variant['sku'] ?? '') === $targetSku
        );

        if (! $targetExists && $variantOptions->isNotEmpty()) {
            $firstVariant = $variantOptions->first(
                fn ($variant) => is_array($variant) && ! empty($variant['sku'])
            );

            $targetSku = (string) ($firstVariant['sku'] ?? $targetSku);
        }

        return $targetSku;
    }
}
