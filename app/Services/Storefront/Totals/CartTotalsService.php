<?php

namespace App\Services\Storefront\Totals;

use App\Data\Shipping\ShippingContext;
use App\Models\Cart;
use App\Services\Shipping\ShippingResolver;
use App\Services\Storefront\Pricing\CartPricingService;

class CartTotalsService
{
    public function __construct(
        protected CartPricingService $pricingService,
        protected ShippingResolver $shippingResolver,
    ) {
    }

    public function calculate(Cart $cart): array
    {
        $cart->loadMissing(['items', 'store', 'customer', 'shippingAddress']);

        $pricing = $this->pricingService->calculate($cart);
        $promotions = $this->normalizePromotionsPayload($pricing['promotions'] ?? null);

        $subtotal = (float) ($pricing['subtotal'] ?? 0);
        $discountTotal = (float) (
            $pricing['discount_total']
            ?? $promotions['cart_discount_total']
            ?? 0
        );
        $subtotalAfterDiscount = (float) (
            $pricing['subtotal_after_discount']
            ?? max(0, $subtotal - $discountTotal)
        );

        // La spedizione deve essere calcolata sul subtotale originale,
        // prima di coupon/promozioni, così il buono non abbassa la soglia spedizione.
        $shippingQuote = $this->shippingResolver->quote(
            ShippingContext::fromCart($cart, $subtotal)
        );

        $shippingTotal = max(0, (float) ($shippingQuote->amount ?? 0));
        $taxTotal = 0.0;
        $grandTotal = max(0, $subtotalAfterDiscount + $shippingTotal + $taxTotal);

        return [
            'subtotal' => $this->roundAmount($subtotal),
            'discount_total' => $this->roundAmount($discountTotal),
            'subtotal_after_discount' => $this->roundAmount($subtotalAfterDiscount),
            'shipping_total' => $this->roundAmount($shippingTotal),
            'tax_total' => $this->roundAmount($taxTotal),
            'grand_total' => $this->roundAmount($grandTotal),
            'shipping' => $shippingQuote->toArray(),
            'promotions' => $promotions,
        ];
    }

    public function recalculate(Cart $cart): Cart
    {
        $cart->loadMissing(['items', 'customer', 'store', 'shippingAddress']);

        $totals = $this->calculate($cart);
        $promotions = $this->normalizePromotionsPayload(data_get($totals, 'promotions'));
        $lineDiscounts = $promotions['line_discounts'];

        foreach ($cart->items as $item) {
            if ($this->isCouponDiscountItem($item)) {
                $this->normalizeCouponDiscountItem($item);
                continue;
            }

            $quantity = (float) ($item->quantity ?? 0);
            $unitPrice = $item->base_price !== null
            ? (float) $item->base_price
            : ($item->price !== null
                ? (float) $item->price
                : ($item->price_net !== null
                    ? (float) $item->price_net
                    : 0.0));
            $sku = (string) ($item->sku ?? '');

            $rowSubtotal = $quantity * $unitPrice;
            $rowDiscount = (float) data_get($lineDiscounts, $sku . '.discount_total', 0);
            $rowDiscount = min($rowDiscount, max(0, $rowSubtotal));
            $rowTotal = max(0, $rowSubtotal - $rowDiscount);
            $finalUnitPrice = $quantity > 0 ? ($rowTotal / $quantity) : $unitPrice;

            $item->row_subtotal = $this->formatAmount($rowSubtotal);
            $item->row_discount_total = $this->formatAmount($rowDiscount);
            $item->row_tax_total = $this->formatAmount(0);
            $item->row_total = $this->formatAmount($rowTotal);

            if ($this->hasColumn($item, 'base_price')) {
                $item->base_price = $this->formatAmount($unitPrice);
            }

            if ($this->hasColumn($item, 'base_row_total')) {
                $item->base_row_total = $this->formatAmount($rowSubtotal);
            }

            if ($this->hasColumn($item, 'web_discount_total')) {
                $item->web_discount_total = $this->formatAmount($rowDiscount);
            }

            if ($this->hasColumn($item, 'final_price')) {
                $item->final_price = $this->formatAmount($finalUnitPrice);
            }

            if ($this->hasColumn($item, 'final_row_total')) {
                $item->final_row_total = $this->formatAmount($rowTotal);
            }

            $item->save();
        }

        $meta = is_array($cart->meta) ? $cart->meta : [];
        $meta['promotions'] = [
            'cart_discount_total' => $this->formatAmount((float) ($promotions['cart_discount_total'] ?? 0)),
            'applied_promotions' => is_array($promotions['applied_promotions'] ?? null)
                ? $promotions['applied_promotions']
                : [],
            'applied_coupons' => is_array($promotions['applied_coupons'] ?? null)
                ? $promotions['applied_coupons']
                : [],
            'coupon_rows' => is_array($promotions['coupon_rows'] ?? null)
                ? $promotions['coupon_rows']
                : [],
            'coupon_discount_product_total' => $this->formatAmount((float) ($promotions['coupon_discount_product_total'] ?? 0)),
        ];

        $cart->meta = $meta;
        $cart->subtotal = $this->formatAmount((float) ($totals['subtotal'] ?? 0));
        $cart->discount_total = $this->formatAmount((float) ($totals['discount_total'] ?? 0));
        $cart->shipping_total = $this->formatAmount((float) ($totals['shipping_total'] ?? 0));
        $cart->tax_total = $this->formatAmount((float) ($totals['tax_total'] ?? 0));
        $cart->grand_total = $this->formatAmount((float) ($totals['grand_total'] ?? 0));
        $cart->save();

        return $cart->fresh(['items', 'customer', 'store', 'shippingAddress']);
    }

    protected function isCouponDiscountItem(object $item): bool
    {
        $sku = strtoupper(trim((string) ($item->sku ?? '')));

        return str_starts_with($sku, 'MTBUONO');
    }

    protected function normalizeCouponDiscountItem(object $item): void
    {
        $quantity = 1.0;
        $unitPrice = $item->base_price !== null
            ? (float) $item->base_price
            : ($item->price !== null
                ? (float) $item->price
                : ($item->price_net !== null
                    ? (float) $item->price_net
                    : (float) ($item->row_total ?? 0)));

        $amount = -1 * abs($unitPrice);

        $item->quantity = $this->formatAmount($quantity);
        $item->price = $this->formatAmount($amount);
        $item->price_net = $this->formatAmount($amount);
        $item->price_gross = $this->formatAmount($amount);
        $item->row_subtotal = $this->formatAmount($amount);
        $item->row_discount_total = $this->formatAmount(0);
        $item->row_tax_total = $this->formatAmount(0);
        $item->row_total = $this->formatAmount($amount);

        if ($this->hasColumn($item, 'base_price')) {
            $item->base_price = $this->formatAmount($amount);
        }

        if ($this->hasColumn($item, 'base_row_total')) {
            $item->base_row_total = $this->formatAmount($amount);
        }

        if ($this->hasColumn($item, 'web_discount_total')) {
            $item->web_discount_total = $this->formatAmount(0);
        }

        if ($this->hasColumn($item, 'final_price')) {
            $item->final_price = $this->formatAmount($amount);
        }

        if ($this->hasColumn($item, 'final_row_total')) {
            $item->final_row_total = $this->formatAmount($amount);
        }

        $item->save();
    }

    protected function hasColumn(object $model, string $attribute): bool
    {
        if (
            !method_exists($model, 'getAttributes')
            || !method_exists($model, 'getCasts')
            || !method_exists($model, 'isFillable')
        ) {
            return false;
        }

        return array_key_exists($attribute, $model->getAttributes())
            || array_key_exists($attribute, $model->getCasts())
            || $model->isFillable($attribute);
    }

    protected function roundAmount(float $value): float
    {
        return round($value, 3);
    }

    protected function formatAmount(float|int $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }

    protected function normalizePromotionsPayload(mixed $promotions): array
    {
        $payload = is_array($promotions) ? $promotions : [];

        return [
            'line_discounts' => is_array($payload['line_discounts'] ?? null)
                ? $payload['line_discounts']
                : [],
            'cart_discount_total' => $this->roundAmount((float) ($payload['cart_discount_total'] ?? 0)),
            'applied_promotions' => is_array($payload['applied_promotions'] ?? null)
                ? $payload['applied_promotions']
                : [],
            'applied_coupons' => is_array($payload['applied_coupons'] ?? null)
                ? $payload['applied_coupons']
                : [],
            'coupon_rows' => is_array($payload['coupon_rows'] ?? null)
                ? $payload['coupon_rows']
                : [],
            'coupon_discount_product_total' => $this->roundAmount((float) ($payload['coupon_discount_product_total'] ?? 0)),
        ];
    }
}