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
    public readonly bool $showPackMultiple;
    public readonly string $productUrl;
    public readonly bool $isWishlisted;

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

        $this->targetSku = (string) (
            $this->listingCard->get('target_sku')
            ?? $product->listing_target_sku
            ?? $product->sku
        );

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
        $this->showPackMultiple = $this->packMultiple > 1;
        $this->productUrl = route('storefront.product.show', $this->targetSku);

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
        if ($this->price === null) {
            return '—';
        }

        return '€ ' . number_format((float) $this->price, 3, ',', '.');
    }

    public function formattedQuantityMin(): string
    {
        return number_format($this->quantityMin, 0, ',', '.');
    }

    public function formattedPackMultiple(): string
    {
        return number_format($this->packMultiple, 0, ',', '.');
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

        return [
            'sku' => $optionSku,
            'value' => $optionColorValue,
            'image' => $option['image'] ?? '',
            'hover_image' => $option['hover_image'] ?? '',
            'price' => $option['price'] ?? $option['effective_price'] ?? '',
            'quantity_min' => $optionQuantityMin,
            'quantity_step' => $this->resolveOptionQuantityStep($option, $optionPackMultiple, $optionQuantityMin),
            'pack_multiple' => $optionPackMultiple,
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

        return [
            'sku' => $optionSku,
            'value' => $optionFormatValue,
            'image' => $option['image'] ?? '',
            'hover_image' => $option['hover_image'] ?? '',
            'price' => $option['price'] ?? $option['effective_price'] ?? '',
            'quantity_min' => $optionQuantityMin,
            'quantity_step' => $this->resolveOptionQuantityStep($option, $optionPackMultiple, $optionQuantityMin),
            'pack_multiple' => $optionPackMultiple,
            'url' => route('storefront.product.show', $optionSku),
            'is_selected' => $this->selectedFormatValue !== null
                && $optionFormatValue !== null
                && (string) $optionFormatValue === (string) $this->selectedFormatValue,
        ];
    }

    protected function resolveSelectedVariant(): ?array
    {
        $selectedVariant = $this->variantOptions->first(
            fn (array $item) => (string) ($item['sku'] ?? '') === $this->targetSku
        );

        if (!$selectedVariant && $this->variantOptions->isNotEmpty()) {
            $selectedVariant = $this->variantOptions->first();
        }

        return is_array($selectedVariant) ? $selectedVariant : null;
    }

    protected function resolveImage(): ?string
    {
        return $this->listingCard->get('image')
            ?? ($this->selectedVariant['image'] ?? null)
            ?? $this->product->mainImage()?->url
            ?? $this->product->mediaAssets
                ->firstWhere('role', MediaAsset::ROLE_MAIN)?->url
            ?? $this->product->mediaAssets->first()?->url;
    }

    protected function resolveHoverImage(): ?string
    {
        return $this->listingCard->get('hover_image')
            ?? ($this->selectedVariant['hover_image'] ?? null)
            ?? $this->product->mediaAssets
                ->where('role', MediaAsset::ROLE_GALLERY)
                ->values()
                ->get(0)?->url
            ?? $this->product->mediaAssets
                ->where('role', MediaAsset::ROLE_MAIN)
                ->values()
                ->get(1)?->url
            ?? null;
    }

    protected function resolvePriceBreaks(): Collection
    {
        $pricePayload = collect($this->listingCard->get('price_payload') ?? []);

        return collect(
            $pricePayload->get('price_breaks')
            ?? $this->listingCard->get('price_breaks')
            ?? []
        );
    }

    protected function resolvePrice(): mixed
    {
        $pricePayload = collect($this->listingCard->get('price_payload') ?? []);

        $price = $this->listingCard->get('price');

        if ($price === null) {
            $price = $pricePayload->get('price');
        }

        return $price
            ?? ($this->selectedVariant['price'] ?? null)
            ?? ($this->selectedVariant['effective_price'] ?? null)
            ?? $this->product->effective_price
            ?? $this->product->public_price;
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

    protected function normalizeQuantityMin(int $quantityMin, int $packMultiple): int
    {
        if ($packMultiple > 1 && $quantityMin % $packMultiple !== 0) {
            return (int) (ceil($quantityMin / $packMultiple) * $packMultiple);
        }

        return $quantityMin;
    }
}