<?php

namespace App\Services\Storefront\Pricing;

use App\Models\Cart;
use App\Services\Storefront\Promotion\CouponService;
use App\Services\Storefront\Promotion\PromotionEngine;

class CartPricingService
{
    public function __construct(
        protected PromotionEngine $promotionEngine,
        protected CouponService $couponService,
    ) {
    }

    public function calculate(Cart $cart): array
    {
        $cart->loadMissing(['items', 'store']);

        $items = collect($cart->items ?? []);

        $regularItems = $items->reject(fn ($item) => $this->isCouponDiscountItem($item));
        $couponDiscountItems = $items->filter(fn ($item) => $this->isCouponDiscountItem($item));

        $regularSubtotal = (float) $regularItems->sum(function ($item) {
            $quantity = (float) ($item->quantity ?? 0);
            $unitPrice = $this->resolveUnitPrice($item);

            return $unitPrice * $quantity;
        });

        $couponRowsTotal = (float) $couponDiscountItems->sum(function ($item) {
            $quantity = (float) ($item->quantity ?? 0);
            $unitPrice = $this->resolveUnitPrice($item);

            return $unitPrice * $quantity;
        });

        // Il subtotale deve restare il totale pieno dei prodotti,
        // senza sottrarre la riga coupon negativa.
        $subtotal = $regularSubtotal;
        $couponDiscountTotal = abs(min(0, $couponRowsTotal));

        $couponCode = $cart->store
            ? $this->couponService->extractCouponCodeFromCart($cart)
            : null;

        $promotionResult = $this->promotionEngine->evaluate($cart, $couponCode);

        $lineDiscounts = is_array($promotionResult['line_discounts'] ?? null)
            ? $promotionResult['line_discounts']
            : [];

        $promotionDiscountTotal = $this->resolvePromotionDiscountTotal($lineDiscounts);

        // Gli sconti web devono includere sia le promozioni automatiche
        // sia il coupon rappresentato come riga/prodotto negativa MTBUONO*.
        $discountTotal = min(
            $promotionDiscountTotal + $couponDiscountTotal,
            max(0, $regularSubtotal)
        );

        $subtotalAfterDiscount = max(0, $subtotal - $discountTotal);

        return [
            'subtotal' => $this->roundAmount($subtotal),
            'discount_total' => $this->roundAmount($discountTotal),
            'subtotal_after_discount' => $this->roundAmount($subtotalAfterDiscount),
            'promotions' => [
                'line_discounts' => $lineDiscounts,
                'cart_discount_total' => $this->roundAmount($discountTotal),
                'applied_promotions' => is_array($promotionResult['applied_promotions'] ?? null)
                    ? $promotionResult['applied_promotions']
                    : [],
                'applied_coupons' => is_array($promotionResult['applied_coupons'] ?? null)
                    ? $promotionResult['applied_coupons']
                    : [],
                'coupon_rows' => is_array($promotionResult['coupon_rows'] ?? null)
                    ? $promotionResult['coupon_rows']
                    : [],
                'coupon_discount_product_total' => $this->roundAmount($couponDiscountTotal),
            ],
        ];
    }

    protected function resolvePromotionDiscountTotal(array $lineDiscounts): float
    {
        $total = 0.0;

        foreach ($lineDiscounts as $row) {
            $total += max(0, (float) ($row['discount_total'] ?? 0));
        }

        return max(0, $total);
    }

    protected function isCouponDiscountItem(object $item): bool
    {
        $sku = strtoupper(trim((string) ($item->sku ?? '')));
        $configuredSku = strtoupper(trim((string) config('cart.coupon_discount_sku', 'SCONTO')));

        if (str_starts_with($sku, 'MTBUONO')) {
            return true;
        }

        if ($configuredSku !== '' && $sku === $configuredSku) {
            return true;
        }

        return in_array($sku, [
            'SCONTO',
            'COUPON',
            'BUONO',
            'BUONO-SCONTO',
            'BUONO_SCONTO',
            'DISCOUNT',
        ], true);
    }

    protected function resolveUnitPrice(object $item): float
    {
        if (($item->base_price ?? null) !== null) {
            return (float) $item->base_price;
        }

        if (($item->price ?? null) !== null) {
            return (float) $item->price;
        }

        return (float) ($item->price_net ?? 0);
    }

    protected function roundAmount(float $value): float
    {
        return round($value, 3);
    }
}