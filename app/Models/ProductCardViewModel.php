<?php

namespace App\Models;

use Illuminate\Support\Collection;

class ProductCardViewModel
{
    public readonly Product $product;
    public readonly Collection $listingCard;
    public readonly Collection $variantOptions;
    public readonly ?array $selectedVariant;
    public readonly string $name;
    public readonly int $variants;
    public readonly string $targetSku;
    public readonly ?string $image;
    public readonly ?string $hoverImage;
    public readonly Collection $priceBreaks;
    public readonly mixed $price;
    public readonly bool $hasVariablePrice;
    public readonly mixed $selectedColorValue;
    public readonly mixed $selectedFormatValue;
    public readonly Collection $colorOptions;
    public readonly Collection $formatOptions;
    public readonly int $quantityMin;
    public readonly int $packMultiple;
    public readonly int $quantityStep;
    public readonly ?int $quantityMax;
    public readonly bool $showPackMultiple;
    public readonly string $productUrl;
    public readonly bool $isWishlisted;
    public readonly bool $isPurchasable;

    public function __construct(?Product $product, mixed $listingCard = [])
    {
        if (!$product instanceof Product) {
            abort(404);
        }

        $this->product = $product;
        $this->listingCard = collect($listingCard ?? []);
        $this->name = $product->display_name ?? $product->sku;
        $this->variants = (int) ($product->listing_variant_count ?? 1);
        $this->variantOptions = collect($product->listing_variant_options ?? []);

        $this->targetSku = $this->resolveTargetSku($product);

        $this->selectedVariant = $this->resolveSelectedVariant();
        $this->image = $this->resolveImage();
        $this->hoverImage = $this->resolveHoverImage();
        $this->priceBreaks = $this->resolvePriceBreaks();
        $this->price = $this->resolvePrice();
        $this->hasVariablePrice = $this->priceBreaks->count() > 1;

        $this->selectedColorValue = $this->listingCard->get('selected_color_value')
            ?? ($this->selectedVariant['color']['value'] ?? null)
            ?? $product->listing_selected_color_value;

        $this->selectedFormatValue = $this->listingCard->get('selected_format_value')
            ?? ($this->selectedVariant['format']['value'] ?? null)
            ?? $product->listing_selected_format_value;

        $this->colorOptions = $this->variantOptions
            ->filter(fn ($item) => !empty($item['color']['value']) && !empty($item['sku']))
            ->unique(fn ($item) => (string) $item['color']['value'])
            ->values();

        $this->formatOptions = $this->variantOptions
            ->filter(fn ($item) => !empty($item['format']['value']) && !empty($item['sku']))
            ->unique(fn ($item) => (string) $item['format']['value'])
            ->values();

        $this->packMultiple = $this->resolvePackMultiple();

        $this->quantityMin = $this->normalizeQuantityMin(
            $this->resolveQuantityMin(),
            $this->packMultiple
        );

        $this->quantityStep = $this->resolveQuantityStep();
        $this->quantityMax = $this->resolveQuantityMax();
        $this->showPackMultiple = $this->packMultiple > 1;
        $this->productUrl = route('storefront.product.show', $this->targetSku);
        $this->isPurchasable = $this->resolvePurchasableState();

        $this->isWishlisted = (bool) (
            $this->listingCard->get('is_wishlisted')
            ?? $product->is_wishlisted
            ?? false
        );
    }

    public static function make(?Product $product, mixed $listingCard = []): self
    {
        return new self($product, $listingCard);
    }

    public function quantityInputId(): string
    {
        return 'product-card-qty-' . md5($this->targetSku);
    }

    public function formattedPrice(): string
    {
        return $this->formatPrice($this->price);
    }

    public function formattedQuantityMin(): string
    {
        return number_format($this->quantityMin, 0, ',', '.');
    }

    public function formattedPackMultiple(): string
    {
        return number_format($this->packMultiple, 0, ',', '.');
    }

    public function formattedQuantityMax(): string
    {
        return $this->quantityMax !== null
            ? number_format($this->quantityMax, 0, ',', '.')
            : '';
    }

    public function colorOptionPayload(array $option): array
    {
        $optionSku = (string) ($option['sku'] ?? '');
        $optionColorValue = $option['color']['value'] ?? null;
        $optionPackMultiple = $this->resolveOptionPackMultiple($option);
        $optionQuantityMin = $this->normalizeQuantityMin(
            $this->resolveOptionQuantityMin($option),
            $optionPackMultiple
        );
        $price = $this->resolveOptionPrice($option);

        return [
            'sku' => $optionSku,
            'value' => $optionColorValue,
            'image' => $option['image'] ?? '',
            'hover_image' => $option['hover_image'] ?? '',
            'price' => $this->formatPrice($price, fallback: ''),
            'price_raw' => $price !== null ? (float) $price : '',
            'quantity_min' => $optionQuantityMin,
            'quantity_step' => $this->resolveOptionQuantityStep($option, $optionPackMultiple, $optionQuantityMin),
            'quantity_max' => $this->resolveOptionQuantityMax($option, $optionQuantityMin),
            'pack_multiple' => $optionPackMultiple,
            'is_purchasable' => $this->isOptionPurchasable($option, $optionQuantityMin),
            'url' => route('storefront.product.show', $optionSku),
            'is_selected' => $this->selectedColorValue !== null
                && $optionColorValue !== null
                && (string) $optionColorValue === (string) $this->selectedColorValue,
            'swatch_url' => $option['color']['swatch_url'] ?? null,
        ];
    }

    public function formatOptionPayload(array $option): array
    {
        $optionSku = (string) ($option['sku'] ?? '');
        $optionFormatValue = $option['format']['value'] ?? null;
        $optionPackMultiple = $this->resolveOptionPackMultiple($option);
        $optionQuantityMin = $this->normalizeQuantityMin(
            $this->resolveOptionQuantityMin($option),
            $optionPackMultiple
        );
        $price = $this->resolveOptionPrice($option);

        return [
            'sku' => $optionSku,
            'value' => $optionFormatValue,
            'image' => $option['image'] ?? '',
            'hover_image' => $option['hover_image'] ?? '',
            'price' => $this->formatPrice($price, fallback: ''),
            'price_raw' => $price !== null ? (float) $price : '',
            'quantity_min' => $optionQuantityMin,
            'quantity_step' => $this->resolveOptionQuantityStep($option, $optionPackMultiple, $optionQuantityMin),
            'quantity_max' => $this->resolveOptionQuantityMax($option, $optionQuantityMin),
            'pack_multiple' => $optionPackMultiple,
            'is_purchasable' => $this->isOptionPurchasable($option, $optionQuantityMin),
            'url' => route('storefront.product.show', $optionSku),
            'is_selected' => $this->selectedFormatValue !== null
                && $optionFormatValue !== null
                && (string) $optionFormatValue === (string) $this->selectedFormatValue,
        ];
    }

    protected function formatPrice(mixed $price, string $fallback = '—'): string
    {
        if ($price === null || (is_string($price) && trim($price) === '')) {
            return $fallback;
        }

        $decimals = $this->priceDecimals();

        return '€ ' . number_format((float) $price, $decimals, ',', '.');
    }

    protected function priceDecimals(): int
    {
        $store = current_store();

        return $store?->priceDecimals() ?? 2;
    }

    protected function resolveOptionPrice(array $option): mixed
    {
        return $this->firstFilled([
            $option['price'] ?? null,
            $option['effective_price'] ?? null,
        ], allowZero: true);
    }

    protected function resolveTargetSku(Product $product): string
    {
        $preferredSku = trim((string) (
            $this->listingCard->get('target_sku')
            ?? $product->listing_target_sku
            ?? ''
        ));

        if ($preferredSku !== '') {
            return $preferredSku;
        }

        $availableVariants = $this->variantOptions
            ->filter(fn ($item) => is_array($item) && !empty($item['sku']))
            ->values();

        if ($availableVariants->isNotEmpty()) {
            $purchasableVariants = $availableVariants
                ->filter(fn (array $item) => $this->isOptionPurchasable($item))
                ->values();

            return (string) (($purchasableVariants->isNotEmpty() ? $purchasableVariants : $availableVariants)->random()['sku'] ?? $product->sku);
        }

        return (string) $product->sku;
    }

    protected function resolveSelectedVariant(): ?array
    {
        $selectedVariant = $this->variantOptions->first(
            fn ($item) => is_array($item) && (string) ($item['sku'] ?? '') === $this->targetSku
        );

        return is_array($selectedVariant) ? $selectedVariant : null;
    }

    protected function resolveImage(): ?string
    {
        return $this->firstFilled([
            $this->listingCard->get('image'),
            $this->selectedVariant['image'] ?? null,
            $this->product->main_image_url ?? null,
            $this->mediaAssetUrl(MediaAsset::ROLE_MAIN),
            $this->mediaAssetUrl(MediaAsset::ROLE_GALLERY),
            $this->mediaAssetUrl(),
        ]);
    }

    protected function resolveHoverImage(): ?string
    {
        $mainImage = $this->image ?? $this->listingCard->get('image') ?? ($this->selectedVariant['image'] ?? null);

        return $this->firstFilled([
            $this->listingCard->get('hover_image'),
            $this->selectedVariant['hover_image'] ?? null,
            $this->product->listing_hover_image_url ?? null,
            $this->mediaAssetUrl(MediaAsset::ROLE_GALLERY, $mainImage),
            $this->mediaAssetUrl(MediaAsset::ROLE_MAIN, $mainImage),
            $this->mediaAssetUrl(null, $mainImage),
        ]);
    }

    protected function resolvePriceBreaks(): Collection
    {
        $pricePayload = collect($this->listingCard->get('price_payload') ?? []);

        return collect(
            $pricePayload->get('price_breaks')
            ?? $this->listingCard->get('price_breaks')
            ?? $this->selectedVariant['price_breaks']
            ?? []
        )->filter(fn ($row) => is_array($row))->values();
    }

    protected function resolvePrice(): mixed
    {
        $pricePayload = collect($this->listingCard->get('price_payload') ?? []);

        return $this->firstFilled([
            $this->listingCard->get('price'),
            $pricePayload->get('price'),
            $pricePayload->get('price_net'),
            $this->selectedVariant['price'] ?? null,
            $this->selectedVariant['effective_price'] ?? null,
            $this->product->effective_price ?? null,
            $this->product->public_price ?? null,
        ], allowZero: true);
    }

    protected function resolveQuantityMin(): int
    {
        return max(1, (int) ceil((float) (
            $this->selectedVariant['quantity_min']
            ?? $this->selectedVariant['min_order_qty']
            ?? $this->product->min_order_qty
            ?? 1
        )));
    }

    protected function resolvePackMultiple(): int
    {
        return max(1, (int) ceil((float) (
            $this->selectedVariant['pack_multiple']
            ?? $this->selectedVariant['pzconf_mg68']
            ?? $this->product->pzconf_mg68
            ?? 1
        )));
    }

    protected function resolveQuantityStep(): int
    {
        return max(1, (int) (
            $this->selectedVariant['quantity_step']
            ?? ($this->packMultiple > 1 ? $this->packMultiple : $this->quantityMin)
        ));
    }

    protected function resolveQuantityMax(): ?int
    {
        if (is_array($this->selectedVariant)) {
            return $this->resolveOptionQuantityMax($this->selectedVariant, $this->quantityMin);
        }

        return $this->resolveStockQuantityMax(
            $this->product->stock_qty !== null ? (float) $this->product->stock_qty : null,
            (bool) ($this->product->no_backorder ?? false),
            $this->quantityMin
        );
    }

    protected function resolvePurchasableState(): bool
    {
        if (is_array($this->selectedVariant)) {
            return $this->isOptionPurchasable($this->selectedVariant, $this->quantityMin);
        }

        return $this->isStockPurchasable(
            $this->product->stock_qty !== null ? (float) $this->product->stock_qty : null,
            (bool) ($this->product->no_backorder ?? false),
            $this->quantityMin
        );
    }

    protected function isOptionPurchasable(array $option, ?int $quantityMin = null): bool
    {
        return $this->isStockPurchasable(
            array_key_exists('stock_qty', $option) && $option['stock_qty'] !== null ? (float) $option['stock_qty'] : null,
            (bool) ($option['no_backorder'] ?? false),
            $quantityMin ?? (int) ($option['quantity_min'] ?? 1)
        );
    }

    protected function isStockPurchasable(?float $stockQty, bool $noBackorder, int $quantityMin): bool
    {
        if ($this->isB2cStorefront()) {
            if ($stockQty === null) {
                return true;
            }

            return (int) floor($stockQty) >= max(1, $quantityMin);
        }

        if (!$noBackorder || $stockQty === null) {
            return true;
        }

        return (int) floor($stockQty) >= max(1, $quantityMin);
    }

    protected function resolveStockQuantityMax(?float $stockQty, bool $noBackorder, int $quantityMin): ?int
    {
        if ($this->isB2cStorefront()) {
            if ($stockQty === null) {
                return null;
            }

            $maxQuantity = (int) floor($stockQty);

            return $maxQuantity >= max(1, $quantityMin) ? $maxQuantity : 0;
        }

        if (!$noBackorder || $stockQty === null) {
            return null;
        }

        $maxQuantity = (int) floor($stockQty);

        return $maxQuantity >= max(1, $quantityMin) ? $maxQuantity : 0;
    }

    protected function isB2cStorefront(): bool
    {
        $store = function_exists('current_store') ? current_store() : null;

        return is_object($store) && method_exists($store, 'isB2C') && $store->isB2C();
    }

    protected function resolveOptionQuantityMin(array $option): int
    {
        return max(1, (int) ceil((float) (
            $option['quantity_min']
            ?? $option['min_order_qty']
            ?? $this->quantityMin
        )));
    }

    protected function resolveOptionPackMultiple(array $option): int
    {
        return max(1, (int) ceil((float) (
            $option['pack_multiple']
            ?? $option['pzconf_mg68']
            ?? $this->packMultiple
        )));
    }

    protected function resolveOptionQuantityStep(array $option, int $packMultiple, int $quantityMin): int
    {
        return max(1, (int) (
            $option['quantity_step']
            ?? ($packMultiple > 1 ? $packMultiple : $quantityMin)
        ));
    }

    protected function resolveOptionQuantityMax(array $option, int $quantityMin): ?int
    {
        return $this->resolveStockQuantityMax(
            array_key_exists('stock_qty', $option) && $option['stock_qty'] !== null ? (float) $option['stock_qty'] : null,
            (bool) ($option['no_backorder'] ?? false),
            $quantityMin
        );
    }

    protected function mediaAssetUrl(?string $role = null, ?string $exceptUrl = null): ?string
    {
        if (!$this->product->relationLoaded('mediaAssets')) {
            return null;
        }

        return collect($this->product->getRelation('mediaAssets'))
            ->filter(function ($asset) use ($role) {
                if (!$asset instanceof MediaAsset) {
                    return false;
                }

                return $role === null || $asset->role === $role;
            })
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn (MediaAsset $asset) => $asset->url ?? null)
            ->filter(fn ($url) => $url !== null && $url !== '' && $url !== $exceptUrl)
            ->first();
    }

    protected function firstFilled(array $values, bool $allowZero = false): mixed
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            if (!$allowZero && is_numeric($value) && (float) $value === 0.0) {
                continue;
            }

            return $value;
        }

        return null;
    }

    protected function normalizeQuantityMin(int $quantityMin, int $packMultiple): int
    {
        if ($packMultiple > 1 && $quantityMin % $packMultiple !== 0) {
            return (int) (ceil($quantityMin / $packMultiple) * $packMultiple);
        }

        return $quantityMin;
    }
}
