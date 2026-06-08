<?php

namespace App\Services\Storefront\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Store;
use App\Services\Storefront\Pricing\ProductPriceService;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class CartItemService
{
    public function __construct(
        protected ProductPriceService $productPriceService,
    ) {
    }

    public function addOrUpdateProduct(
        Cart $cart,
        Store $store,
        Product $product,
        float $quantity,
        ?Customer $customer = null
    ): CartItem {
        /** @var CartItem|null $item */
        $item = $cart->items()
            ->where('sku', (string) $product->sku)
            ->first();

        $requestedQuantity = (float) $quantity;

        if ($item instanceof CartItem) {
            $requestedQuantity = (float) $item->quantity + (float) $quantity;
        }

        $normalizedQuantity = $this->normalizeQuantityForProduct($product, $requestedQuantity);
        $this->assertAvailableStock($product, $normalizedQuantity);

        $snapshot = $this->buildProductSnapshot(
            store: $store,
            product: $product,
            quantity: $normalizedQuantity,
            customer: $customer
        );

        if ($item instanceof CartItem) {
            $item->fill(array_merge($snapshot, [
                'quantity' => $normalizedQuantity,
            ]));
            $item->save();

            return $item->fresh();
        }

        /** @var CartItem $created */
        $created = $cart->items()->create(array_merge($snapshot, [
            'ditta_cg18' => (int) $cart->ditta_cg18,
            'site_type' => $cart->site_type,
            'product_id' => $product->id,
            'quantity' => $normalizedQuantity,
        ]));

        return $created->fresh();
    }

    public function updateQuantity(
        CartItem $item,
        float $quantity,
        ?Customer $customer = null
    ): CartItem {
        if ($this->isCouponDiscountItem($item)) {
            $item->quantity = $this->asQuantity(1);
            $item->save();

            return $item->fresh();
        }

        /** @var Cart $cart */
        $cart = $item->cart()->with(['store', 'customer'])->firstOrFail();

        /** @var Store|null $store */
        $store = $cart->store;
        if (!$store instanceof Store) {
            throw new InvalidArgumentException('Store non associato al carrello.');
        }

        /** @var Product|null $product */
        $product = Product::query()
            ->where('ditta_cg18', (int) $cart->ditta_cg18)
            ->where('site_type', (int) $cart->site_type)
            ->where('sku', (string) $item->sku)
            ->first();

        if (!$product instanceof Product) {
            throw new InvalidArgumentException('Prodotto non trovato per aggiornare la riga carrello.');
        }

        $normalizedQuantity = $this->normalizeQuantityForProduct($product, $quantity);
        $this->assertAvailableStock($product, $normalizedQuantity);

        $snapshot = $this->buildProductSnapshot(
            store: $store,
            product: $product,
            quantity: $normalizedQuantity,
            customer: $customer ?? $cart->customer
        );

        $item->fill(array_merge($snapshot, [
            'quantity' => $normalizedQuantity,
            'product_id' => $product->id,
        ]));
        $item->save();

        return $item->fresh();
    }

    public function decorateItems(iterable $items, Store $store): Collection
    {
        $items = collect($items)->values();

        if ($items->isEmpty()) {
            return collect();
        }

        $skus = $items
            ->reject(fn ($item) => $this->isCouponDiscountItem($item))
            ->pluck('sku')
            ->filter()
            ->unique()
            ->values();

        /** @var Collection<string, Product> $productsBySku */
        $productsBySku = Product::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('site_type', (int) $store->erp_site_code)
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy('sku');

        return $items->map(function ($item) use ($productsBySku) {
            if ($this->isCouponDiscountItem($item)) {
                $item->quantity = $this->asQuantity(1);
                $item->quantity_min = 1;
                $item->quantity_step = 1;
                $item->pack_multiple = 1;
                $item->show_pack_multiple = false;

                return $item;
            }

            /** @var Product|null $product */
            $product = $productsBySku->get($item->sku);

            $constraints = $product instanceof Product
                ? $this->resolveQuantityConstraintsForProduct($product)
                : [
                    'min_order_qty' => 1,
                    'pack_multiple' => 1,
                    'quantity_min' => 1,
                    'quantity_step' => 1,
                    'show_pack_multiple' => false,
                ];

            $quantityMin = max(1, (int) ($constraints['quantity_min'] ?? 1));
            $quantityStep = max(1, (int) ($constraints['quantity_step'] ?? 1));
            $packMultiple = max(1, (int) ($constraints['pack_multiple'] ?? 1));
            $showPackMultiple = (bool) ($constraints['show_pack_multiple'] ?? false);

            $currentQuantity = (float) ($item->quantity ?? 0);

            if ($currentQuantity <= 0) {
                $currentQuantity = (float) $quantityMin;
            }

            if ($currentQuantity < $quantityMin) {
                $currentQuantity = (float) $quantityMin;
            }

            if ($quantityStep > 1) {
                $offset = $currentQuantity - $quantityMin;
                if ($offset > 0) {
                    $currentQuantity = $quantityMin + (ceil($offset / $quantityStep) * $quantityStep);
                }
            }

            $item->quantity = $currentQuantity;
            $item->quantity_min = $quantityMin;
            $item->quantity_step = $quantityStep;
            $item->pack_multiple = $packMultiple;
            $item->show_pack_multiple = $showPackMultiple;

            return $item;
        })->values();
    }

    public function resolveQuantityConstraintsForProduct(Product $product): array
    {
        $rawMinOrderQty = (int) ceil((float) ($product->min_order_qty ?? 1));
        $rawPackMultiple = (int) ceil((float) ($product->pzconf_mg68 ?? 0));

        $minOrderQty = max(1, $rawMinOrderQty);
        $packMultiple = max(1, $rawPackMultiple);

        $quantityMin = max($minOrderQty, $packMultiple);

        if ($packMultiple > 1 && $quantityMin % $packMultiple !== 0) {
            $quantityMin = (int) (ceil($quantityMin / $packMultiple) * $packMultiple);
        }

        $quantityStep = $packMultiple > 1
            ? $packMultiple
            : $quantityMin;

        return [
            'min_order_qty' => $minOrderQty,
            'pack_multiple' => $packMultiple,
            'quantity_min' => $quantityMin,
            'quantity_step' => max(1, $quantityStep),
            'show_pack_multiple' => $packMultiple > 1,
        ];
    }

    public function isCouponDiscountItem(object $item): bool
    {
        return $this->isCouponSku((string) ($item->sku ?? ''));
    }

    public function removeCouponDiscountRows(Cart $cart): void
    {
        $cart->items()
            ->whereRaw('UPPER(sku) LIKE ?', ['MTBUONO%'])
            ->delete();
    }

    public function syncCouponDiscountRows(Cart $cart, array $couponRows): void
    {
        $normalizedRows = collect($couponRows)
            ->mapWithKeys(function (array $row) {
                $sku = $this->normalizeSku((string) ($row['sku'] ?? ''));

                return $sku !== '' ? [$sku => $row] : [];
            });

        if ($normalizedRows->isEmpty()) {
            $this->removeCouponDiscountRows($cart);
            return;
        }

        $cart->items()
            ->whereRaw('UPPER(sku) LIKE ?', ['MTBUONO%'])
            ->whereNotIn('sku', $normalizedRows->keys()->all())
            ->delete();

        foreach ($normalizedRows as $sku => $row) {
            $amount = -1 * abs((float) ($row['row_total'] ?? $row['price'] ?? 0));

            if ($amount >= 0) {
                continue;
            }

            $payload = [
                'ditta_cg18' => (int) $cart->ditta_cg18,
                'site_type' => $cart->site_type,
                'product_id' => null,
                'sku' => $sku,
                'product_name' => (string) ($row['product_name'] ?? $sku),
                'product_description' => $row['product_description'] ?? null,
                'product_thumbnail_url' => null,
                'quantity' => $this->asQuantity(1),
                'stock_qty' => null,
                'no_backorder' => false,
                'price' => $this->asMoney($amount),
                'price_net' => $this->asMoney($amount),
                'price_gross' => $this->asMoney($amount),
                'row_total' => $this->asMoney($amount),
                'row_subtotal' => $this->asMoney($amount),
                'row_discount_total' => $this->asMoney(0),
                'row_tax_total' => $this->asMoney(0),
                'base_price' => $this->asMoney($amount),
                'base_row_total' => $this->asMoney($amount),
                'web_discount_total' => $this->asMoney(0),
                'final_price' => $this->asMoney($amount),
                'final_row_total' => $this->asMoney($amount),
                'listino_id' => null,
                'qty_from' => null,
                'qty_to' => null,
                'sc1' => null,
                'sc2' => null,
                'sc3' => null,
                'sc4' => null,
                'sc5' => null,
                'sc6' => null,
            ];

            /** @var CartItem|null $item */
            $item = $cart->items()
                ->whereRaw('UPPER(sku) = ?', [$sku])
                ->first();

            if ($item instanceof CartItem) {
                $item->fill($payload);
                $item->save();
                continue;
            }

            $cart->items()->create($payload);
        }
    }

    protected function buildProductSnapshot(
        Store $store,
        Product $product,
        float $quantity,
        ?Customer $customer = null
    ): array {
        $pricing = $this->productPriceService->resolveForListing(
            store: $store,
            product: $product,
            qty: $quantity,
            customer: $customer
        );

        $translation = $product->translationOrFallback((string) config('app.store_locale', app()->getLocale()));
        $image = $product->mainImage()?->url;

        $pricePayload = is_array($pricing['price_payload'] ?? null)
            ? $pricing['price_payload']
            : [];

        $price = $pricing['price']
            ?? $pricePayload['price']
            ?? $pricePayload['price_net']
            ?? $product->public_price
            ?? $product->effective_price;

        $priceNet = $pricePayload['price_net']
            ?? $pricing['price']
            ?? $pricePayload['price']
            ?? $product->public_price
            ?? $product->effective_price;

        $priceGross = $pricePayload['price_gross'] ?? null;

        $resolvedPrice = $price !== null ? (float) $price : null;
        $resolvedPriceNet = $priceNet !== null ? (float) $priceNet : $resolvedPrice;
        $resolvedPriceGross = $priceGross !== null ? (float) $priceGross : null;

        $baseRowTotal = $resolvedPrice !== null ? ($resolvedPrice * $quantity) : null;
        $baseUnitPrice = $resolvedPrice !== null ? $resolvedPrice : 0.0;

        return [
            'sku' => (string) $product->sku,
            'product_name' => $translation?->name ?? (string) $product->sku,
            'product_description' => $translation?->short_description
                ?? $translation?->description
                ?? null,
            'product_thumbnail_url' => $image,
            'stock_qty' => $product->stock_qty !== null ? $this->asQuantity((float) $product->stock_qty) : null,
            'no_backorder' => (bool) ($product->no_backorder ?? false),
            'price' => $resolvedPrice !== null ? $this->asMoney($resolvedPrice) : null,
            'price_net' => $resolvedPriceNet !== null ? $this->asMoney($resolvedPriceNet) : null,
            'price_gross' => $resolvedPriceGross !== null ? $this->asMoney($resolvedPriceGross) : null,
            'row_total' => $baseRowTotal !== null ? $this->asMoney($baseRowTotal) : null,
            'row_subtotal' => $baseRowTotal !== null ? $this->asMoney($baseRowTotal) : null,
            'row_discount_total' => $this->asMoney(0),
            'row_tax_total' => $this->asMoney(0),
            'base_price' => $resolvedPrice !== null ? $this->asMoney($baseUnitPrice) : null,
            'base_row_total' => $baseRowTotal !== null ? $this->asMoney($baseRowTotal) : null,
            'web_discount_total' => $this->asMoney(0),
            'final_price' => $resolvedPrice !== null ? $this->asMoney($baseUnitPrice) : null,
            'final_row_total' => $baseRowTotal !== null ? $this->asMoney($baseRowTotal) : null,
            'listino_id' => isset($pricePayload['listino_id']) ? (int) $pricePayload['listino_id'] : null,
            'qty_from' => isset($pricePayload['qty_from']) && $pricePayload['qty_from'] !== null
                ? $this->asQuantity((float) $pricePayload['qty_from'])
                : null,
            'qty_to' => isset($pricePayload['qty_to']) && $pricePayload['qty_to'] !== null
                ? $this->asQuantity((float) $pricePayload['qty_to'])
                : null,
            'sc1' => isset($pricePayload['sc1']) && $pricePayload['sc1'] !== null
                ? $this->asQuantity((float) $pricePayload['sc1'])
                : null,
            'sc2' => isset($pricePayload['sc2']) && $pricePayload['sc2'] !== null
                ? $this->asQuantity((float) $pricePayload['sc2'])
                : null,
            'sc3' => isset($pricePayload['sc3']) && $pricePayload['sc3'] !== null
                ? $this->asQuantity((float) $pricePayload['sc3'])
                : null,
            'sc4' => isset($pricePayload['sc4']) && $pricePayload['sc4'] !== null
                ? $this->asQuantity((float) $pricePayload['sc4'])
                : null,
            'sc5' => isset($pricePayload['sc5']) && $pricePayload['sc5'] !== null
                ? $this->asQuantity((float) $pricePayload['sc5'])
                : null,
            'sc6' => isset($pricePayload['sc6']) && $pricePayload['sc6'] !== null
                ? $this->asQuantity((float) $pricePayload['sc6'])
                : null,
        ];
    }

    protected function assertAvailableStock(Product $product, float $quantity): void
    {
        $noBackorder = (bool) ($product->no_backorder ?? false);

        if (!$noBackorder) {
            return;
        }

        $stockQty = $product->stock_qty !== null
            ? (float) $product->stock_qty
            : null;

        if ($stockQty === null) {
            return;
        }

        if ($quantity > $stockQty) {
            throw new InvalidArgumentException(sprintf(
                'Quantità non disponibile. Disponibili solo %s pezzi per questo prodotto.',
                number_format($stockQty, 0, ',', '.')
            ));
        }
    }

    protected function normalizeQuantityForProduct(Product $product, float|int $quantity): float
    {
        $quantity = max(0, (float) $quantity);
        $constraints = $this->resolveQuantityConstraintsForProduct($product);

        $quantityMin = (float) ($constraints['quantity_min'] ?? 1);
        $quantityStep = (float) ($constraints['quantity_step'] ?? 1);

        if ($quantity <= 0) {
            return $quantityMin;
        }

        $normalized = max($quantity, $quantityMin);

        if ($quantityStep > 0) {
            $offset = $normalized - $quantityMin;
            if ($offset > 0) {
                $normalized = $quantityMin + (ceil($offset / $quantityStep) * $quantityStep);
            }
        }

        return (float) $normalized;
    }

    protected function isCouponSku(string $sku): bool
    {
        return str_starts_with($this->normalizeSku($sku), 'MTBUONO');
    }

    protected function normalizeSku(string $sku): string
    {
        return mb_strtoupper(trim($sku));
    }

    protected function asMoney(float $value): string
    {
        return number_format($value, 3, '.', '');
    }

    protected function asQuantity(float $value): string
    {
        return number_format($value, 3, '.', '');
    }
}