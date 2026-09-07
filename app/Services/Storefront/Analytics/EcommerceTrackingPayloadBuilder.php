<?php

namespace App\Services\Storefront\Analytics;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

class EcommerceTrackingPayloadBuilder
{
    public function addToCart(Store $store, CartItem $item): array
    {
        return [
            'event' => 'add_to_cart',
            'ecommerce' => [
                'currency' => $this->currency($item->cart?->currency ?? null),
                'value' => $this->money($this->rowValue($item)),
                'items' => [
                    $this->itemPayload($store, $item, 0),
                ],
            ],
        ];
    }

    public function beginCheckout(Store $store, Cart $cart): array
    {
        $items = $this->relatedItems($cart);

        return [
            'event' => 'begin_checkout',
            'ecommerce' => [
                'currency' => $this->currency($cart->currency ?? null),
                'value' => $this->money($cart->subtotal ?? $this->itemsValue($items)),
                'coupon' => $this->nullableString(data_get($cart->meta, 'coupon.code')),
                'items' => $this->itemsPayload($store, $items),
            ],
        ];
    }

    public function purchase(Store $store, Order $order): array
    {
        $items = $this->relatedItems($order);

        return [
            'event' => 'purchase',
            'ecommerce' => [
                'transaction_id' => (string) $order->order_number,
                'affiliation' => $store->name,
                'currency' => $this->currency($order->currency ?? null),
                'value' => $this->money($order->subtotal ?? $this->itemsValue($items)),
                'tax' => $this->money($order->tax_total ?? 0),
                'shipping' => $this->money($order->shipping_total ?? 0),
                'coupon' => $this->nullableString($order->coupon_code),
                'items' => $this->itemsPayload($store, $items),
            ],
        ];
    }

    public function itemsPayload(Store $store, Collection $items): array
    {
        return $items
            ->values()
            ->map(fn ($item, int $index) => $this->itemPayload($store, $item, $index))
            ->all();
    }

    private function itemPayload(Store $store, CartItem|OrderItem $item, int $index): array
    {
        $sku = (string) ($item->sku ?? '');
        $product = $this->resolveProduct($item);
        $variantAttributes = $this->variantAttributes($item);
        $parentCode = $this->nullableString($product?->parent_code);
        $categoryPath = $this->categoryPath($product);

        $payload = [
            'item_id' => $sku,
            'item_name' => (string) ($item->product_name ?: $sku),
            'affiliation' => $store->name,
            'item_brand' => $this->nullableString($product?->marca_mg64),
            'item_group_id' => $parentCode,
            'item_variant' => $this->variantLabel($variantAttributes),
            'price' => $this->money($this->unitPrice($item)),
            'quantity' => $this->quantity($item->quantity ?? 1),
            'discount' => $this->money($this->unitDiscount($item)),
            'index' => $index,
        ];

        foreach ($categoryPath as $position => $category) {
            $payload[$position === 0 ? 'item_category' : 'item_category' . ($position + 1)] = $category;
        }

        return array_filter($payload, fn ($value) => $value !== null && $value !== '');
    }

    private function relatedItems(Cart|Order $model): Collection
    {
        if (!$model->relationLoaded('items')) {
            $model->loadMissing(['items']);
        }

        return collect($model->getRelationValue('items'))->values();
    }

    private function resolveProduct(CartItem|OrderItem $item): ?Product
    {
        $product = $item->relationLoaded('product') ? $item->product : null;

        if ($product instanceof Product) {
            return $product;
        }

        if (!empty($item->product_id)) {
            if (Model::getConnectionResolver() === null) {
                return null;
            }

            return Product::query()->find((int) $item->product_id);
        }

        $sku = trim((string) ($item->sku ?? ''));

        if ($sku === '') {
            return null;
        }

        if (Model::getConnectionResolver() === null) {
            return null;
        }

        try {
            return Product::query()
                ->where('sku', $sku)
                ->where('ditta_cg18', (int) ($item->ditta_cg18 ?? 0))
                ->when($item->site_type !== null, fn ($query) => $query->where('site_type', (int) $item->site_type))
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    private function categoryPath(?Product $product): array
    {
        if (!$product instanceof Product) {
            return [];
        }

        return array_values(array_filter([
            $this->nullableString($product->fam_99),
            $this->nullableString($product->sfam_99),
            $this->nullableString($product->gruppo_99),
            $this->nullableString($product->sgruppo_99),
        ]));
    }

    private function variantAttributes(CartItem|OrderItem $item): array
    {
        $attributes = $item->variant_attributes ?? null;

        if (is_string($attributes)) {
            $attributes = json_decode($attributes, true);
        }

        return is_array($attributes) ? $attributes : [];
    }

    private function variantLabel(array $attributes): ?string
    {
        $parts = collect(['color', 'colore', 'format', 'formato', 'size'])
            ->map(fn (string $key) => $this->nullableString($attributes[$key] ?? null))
            ->filter()
            ->unique()
            ->values();

        if ($parts->isEmpty()) {
            return null;
        }

        return $parts->implode(' / ');
    }

    private function itemsValue(Collection $items): float
    {
        return $items->sum(fn ($item) => $this->rowValue($item));
    }

    private function rowValue(CartItem|OrderItem $item): float
    {
        $rowTotal = $item->row_subtotal ?? $item->base_row_total ?? $item->row_total ?? null;

        if ($rowTotal !== null) {
            return (float) $rowTotal;
        }

        return $this->unitPrice($item) * $this->quantity($item->quantity ?? 1);
    }

    private function unitPrice(CartItem|OrderItem $item): float
    {
        return (float) ($item->price_net ?? $item->final_price ?? $item->price ?? 0);
    }

    private function unitDiscount(CartItem|OrderItem $item): float
    {
        $discount = (float) ($item->row_discount_total ?? $item->web_discount_total ?? 0);
        $quantity = $this->quantity($item->quantity ?? 1);

        return $quantity > 0 ? $discount / $quantity : 0.0;
    }

    private function quantity(mixed $value): float
    {
        return max(1.0, (float) ($value ?? 1));
    }

    private function money(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    private function currency(?string $value): string
    {
        $currency = strtoupper(trim((string) ($value ?: 'EUR')));

        return $currency !== '' ? $currency : 'EUR';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
