<?php

namespace App\Services\Storefront\Promotion;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PromotionEngine
{
    public function __construct(
        private CouponService $couponService
    ) {
    }

    public function evaluate(Cart $cart, ?string $couponCode = null): array
    {
        $cart->loadMissing(['items', 'store', 'customer']);

        $store = $cart->store;

        if (!$store instanceof Store) {
            return $this->emptyResult();
        }

        $items = collect($cart->items ?? [])
            ->reject(fn ($item) => $this->isCouponSku((string) ($item->sku ?? '')))
            ->values();

        if ($items->isEmpty()) {
            return $this->emptyResult();
        }

        $promotions = $this->resolveAutomaticPromotions($store);
        $coupon = $this->resolveCoupon($store, $cart, $couponCode);

        $lineDiscounts = [];
        $appliedPromotions = [];
        $appliedCoupons = [];
        $couponRows = [];
        $cartDiscountTotal = 0.0;

        foreach ($promotions as $promotion) {
            $evaluation = $this->applyPromotionToCart($promotion, $cart, $items, $lineDiscounts);
            $discountAmount = (float) ($evaluation['discount_total'] ?? 0);

            if ($discountAmount <= 0) {
                continue;
            }

            $lineDiscounts = is_array($evaluation['line_discounts'] ?? null)
                ? $evaluation['line_discounts']
                : $lineDiscounts;

            $cartDiscountTotal += $discountAmount;
            $appliedPromotions[] = $this->mapAppliedPromotion($promotion, $discountAmount);
        }

        if ($coupon instanceof Coupon) {
            $evaluation = $this->applyCouponToCart($coupon, $cart, $items, $lineDiscounts);
            $discountAmount = (float) ($evaluation['discount_total'] ?? 0);

            if ($discountAmount > 0) {
                // Il coupon NON viene più spalmato sulle righe prodotto.
                // Diventa una riga/prodotto separata con SKU maiuscolo.
                $cartDiscountTotal += $discountAmount;

                $appliedCoupons[] = $this->mapAppliedCoupon($coupon, $discountAmount);
                $couponRows[] = $this->mapCouponRow($coupon, $discountAmount);
            }
        }

        return [
            'line_discounts' => $this->normalizeLineDiscounts($lineDiscounts),
            'cart_discount_total' => $this->asMoney($cartDiscountTotal),
            'applied_promotions' => $appliedPromotions,
            'applied_coupons' => $appliedCoupons,
            'coupon_rows' => $couponRows,
        ];
    }

    public function evaluateAutomaticPromotions(Cart $cart): array
    {
        return $this->evaluate($cart, null);
    }

    protected function resolveAutomaticPromotions(Store $store): Collection
    {
        return Promotion::query()
            ->where('is_active', true)
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where(function ($query) use ($store) {
                $query->whereNull('site_type')
                    ->orWhere('site_type', (int) $store->erp_site_code);
            })
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->filter(function (Promotion $promotion) use ($store) {
                if (filled($promotion->code)) {
                    return false;
                }

                return !$this->promotionRequiresCoupon($promotion, $store);
            })
            ->values();
    }

    protected function resolveCoupon(Store $store, Cart $cart, ?string $couponCode = null): ?Coupon
    {
        $normalizedCode = $this->normalizeCode((string) $couponCode);

        if ($normalizedCode !== '') {
            $validation = $this->couponService->validateForCart(
                $store,
                $cart,
                $normalizedCode,
                $cart->customer
            );

            return ($validation['valid'] ?? false) === true
                ? ($validation['coupon'] ?? null)
                : null;
        }

        return $this->couponService->resolveCouponForCart($cart, $store);
    }

    protected function applyPromotionToCart(
        Promotion $promotion,
        Cart $cart,
        Collection $items,
        array $existingLineDiscounts
    ): array {
        $minimumSubtotal = $this->resolvePromotionMinimumSubtotal($promotion);
        $discountDefinition = $this->resolvePromotionDiscountDefinition($promotion);

        if ($discountDefinition === null) {
            return [
                'line_discounts' => $existingLineDiscounts,
                'discount_total' => 0.0,
            ];
        }

        if (!$this->cartMatchesThreshold($cart, $items, $minimumSubtotal)) {
            return [
                'line_discounts' => $existingLineDiscounts,
                'discount_total' => 0.0,
            ];
        }

        return $this->applyDiscountDefinition(
            type: $discountDefinition['type'],
            value: $discountDefinition['value'],
            scope: $discountDefinition['scope'],
            items: $items,
            existingLineDiscounts: $existingLineDiscounts
        );
    }

    protected function applyCouponToCart(
        Coupon $coupon,
        Cart $cart,
        Collection $items,
        array $existingLineDiscounts
    ): array {
        $cart->loadMissing('store');

        $store = $cart->store;

        if (!$store instanceof Store) {
            return [
                'line_discounts' => $existingLineDiscounts,
                'discount_total' => 0.0,
            ];
        }

        $promotion = $this->resolvePromotionForCoupon($coupon, $store);

        if (!$promotion instanceof Promotion) {
            return [
                'line_discounts' => $existingLineDiscounts,
                'discount_total' => 0.0,
            ];
        }

        $minimumSubtotal = $this->resolvePromotionMinimumSubtotal($promotion);
        $discountDefinition = $this->resolvePromotionDiscountDefinition($promotion);

        if ($discountDefinition === null) {
            return [
                'line_discounts' => $existingLineDiscounts,
                'discount_total' => 0.0,
            ];
        }

        if (!$this->cartMatchesThreshold($cart, $items, $minimumSubtotal)) {
            return [
                'line_discounts' => $existingLineDiscounts,
                'discount_total' => 0.0,
            ];
        }

        return [
            'line_discounts' => $existingLineDiscounts,
            'discount_total' => $this->calculateDiscountOnly(
                type: $discountDefinition['type'],
                value: $discountDefinition['value'],
                scope: $discountDefinition['scope'],
                items: $items,
                existingLineDiscounts: $existingLineDiscounts
            ),
        ];
    }

    private function resolvePromotionForCoupon(Coupon $coupon, Store $store): ?Promotion
    {
        $coupon->loadMissing('promotion');

        if ($coupon->promotion instanceof Promotion) {
            return $coupon->promotion;
        }

        $couponCode = $this->normalizeCode((string) $coupon->code);

        if ($couponCode === '') {
            return null;
        }

        return Promotion::query()
            ->where('is_active', true)
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where(function ($query) use ($store) {
                $query->whereNull('site_type')
                    ->orWhere('site_type', (int) $store->erp_site_code);
            })
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->first(function (Promotion $promotion) use ($couponCode) {
                if ($this->normalizeCode((string) ($promotion->code ?? '')) === $couponCode) {
                    return true;
                }

                return $this->promotionContainsCouponCode($promotion, $couponCode);
            });
    }

    protected function applyDiscountDefinition(
        string $type,
        float $value,
        string $scope,
        Collection $items,
        array $existingLineDiscounts
    ): array {
        $type = $this->normalizeDiscountType($type);
        $scope = $this->normalizeDiscountScope($scope);

        if (!in_array($type, ['percent', 'fixed'], true) || $value <= 0) {
            return [
                'line_discounts' => $existingLineDiscounts,
                'discount_total' => 0.0,
            ];
        }

        return $scope === 'line'
            ? $this->applyLineDiscount($type, $value, $items, $existingLineDiscounts)
            : $this->applyCartDiscount($type, $value, $items, $existingLineDiscounts);
    }

    protected function calculateDiscountOnly(
        string $type,
        float $value,
        string $scope,
        Collection $items,
        array $existingLineDiscounts
    ): float {
        $type = $this->normalizeDiscountType($type);
        $scope = $this->normalizeDiscountScope($scope);

        if (!in_array($type, ['percent', 'fixed'], true) || $value <= 0) {
            return 0.0;
        }

        $availableTotal = (float) $items->sum(function ($item) use ($existingLineDiscounts) {
            $sku = $this->normalizeSku((string) ($item->sku ?? ''));

            if ($sku === '' || $this->isCouponSku($sku)) {
                return 0.0;
            }

            $baseRowTotal = $this->resolveItemBaseRowTotal($item);
            $alreadyDiscounted = (float) data_get($existingLineDiscounts, $sku . '.discount_total', 0);

            return max(0, $baseRowTotal - $alreadyDiscounted);
        });

        if ($availableTotal <= 0) {
            return 0.0;
        }

        $discountTotal = $type === 'percent'
            ? ($availableTotal * ($value / 100))
            : $value;

        if ($scope === 'line' && $type === 'fixed') {
            $discountTotal = (float) $items->sum(function ($item) use ($value) {
                $sku = $this->normalizeSku((string) ($item->sku ?? ''));

                if ($sku === '' || $this->isCouponSku($sku)) {
                    return 0.0;
                }

                return $value * max(0, (float) ($item->quantity ?? 0));
            });
        }

        return round(min($discountTotal, $availableTotal), 3);
    }

    protected function applyCartDiscount(
        string $type,
        float $value,
        Collection $items,
        array $existingLineDiscounts
    ): array {
        $eligibleRows = $items->map(function ($item) use ($existingLineDiscounts) {
            $sku = $this->normalizeSku((string) ($item->sku ?? ''));

            if ($sku === '' || $this->isCouponSku($sku)) {
                return [
                    'sku' => '',
                    'available' => 0,
                ];
            }

            $baseRowTotal = $this->resolveItemBaseRowTotal($item);
            $alreadyDiscounted = (float) data_get($existingLineDiscounts, $sku . '.discount_total', 0);
            $available = max(0, $baseRowTotal - $alreadyDiscounted);

            return [
                'sku' => $sku,
                'available' => $available,
            ];
        })->filter(fn (array $row) => $row['sku'] !== '' && $row['available'] > 0)->values();

        if ($eligibleRows->isEmpty()) {
            return [
                'line_discounts' => $existingLineDiscounts,
                'discount_total' => 0.0,
            ];
        }

        $availableTotal = (float) $eligibleRows->sum('available');

        if ($availableTotal <= 0) {
            return [
                'line_discounts' => $existingLineDiscounts,
                'discount_total' => 0.0,
            ];
        }

        $discountTotal = $type === 'percent'
            ? ($availableTotal * ($value / 100))
            : $value;

        $discountTotal = round(min($discountTotal, $availableTotal), 3);

        if ($discountTotal <= 0) {
            return [
                'line_discounts' => $existingLineDiscounts,
                'discount_total' => 0.0,
            ];
        }

        $lineDiscounts = $existingLineDiscounts;
        $allocated = 0.0;
        $count = $eligibleRows->count();

        foreach ($eligibleRows as $index => $row) {
            $sku = (string) $row['sku'];
            $available = (float) $row['available'];

            if ($index === ($count - 1)) {
                $lineDiscount = max(0, round(min($discountTotal - $allocated, $available), 3));
            } else {
                $ratio = $availableTotal > 0 ? ($available / $availableTotal) : 0;
                $lineDiscount = round($discountTotal * $ratio, 3);
                $lineDiscount = min($lineDiscount, $available);
                $allocated += $lineDiscount;
            }

            if ($lineDiscount <= 0) {
                continue;
            }

            $lineDiscounts[$sku] = [
                'discount_total' => round(((float) data_get($lineDiscounts, $sku . '.discount_total', 0)) + $lineDiscount, 3),
            ];
        }

        return [
            'line_discounts' => $lineDiscounts,
            'discount_total' => $discountTotal,
        ];
    }

    protected function applyLineDiscount(
        string $type,
        float $value,
        Collection $items,
        array $existingLineDiscounts
    ): array {
        $lineDiscounts = $existingLineDiscounts;
        $discountTotal = 0.0;

        foreach ($items as $item) {
            $sku = $this->normalizeSku((string) ($item->sku ?? ''));

            if ($sku === '' || $this->isCouponSku($sku)) {
                continue;
            }

            $quantity = max(0, (float) ($item->quantity ?? 0));
            $baseRowTotal = $this->resolveItemBaseRowTotal($item);
            $alreadyDiscounted = (float) data_get($lineDiscounts, $sku . '.discount_total', 0);
            $available = max(0, $baseRowTotal - $alreadyDiscounted);

            if ($quantity <= 0 || $available <= 0) {
                continue;
            }

            $lineDiscount = $type === 'percent'
                ? ($available * ($value / 100))
                : ($value * $quantity);

            $lineDiscount = round(min($lineDiscount, $available), 3);

            if ($lineDiscount <= 0) {
                continue;
            }

            $lineDiscounts[$sku] = [
                'discount_total' => round($alreadyDiscounted + $lineDiscount, 3),
            ];

            $discountTotal += $lineDiscount;
        }

        return [
            'line_discounts' => $lineDiscounts,
            'discount_total' => round($discountTotal, 3),
        ];
    }

    protected function cartMatchesThreshold(Cart $cart, Collection $items, mixed $minimumSubtotal): bool
    {
        if ($minimumSubtotal === null || $minimumSubtotal === '') {
            return true;
        }

        return $this->resolveCartSubtotal($cart, $items) >= (float) $minimumSubtotal;
    }

    protected function normalizeLineDiscounts(array $lineDiscounts): array
    {
        $normalized = [];

        foreach ($lineDiscounts as $sku => $row) {
            $sku = $this->normalizeSku((string) $sku);

            if ($sku === '') {
                continue;
            }

            $normalized[$sku] = [
                'discount_total' => $this->asMoney((float) ($row['discount_total'] ?? 0)),
            ];
        }

        return $normalized;
    }

    protected function mapAppliedPromotion(Promotion $promotion, float $discountTotal): array
    {
        return [
            'id' => $promotion->id,
            'code' => $promotion->code,
            'name' => $promotion->name,
            'discount_total' => $this->asMoney($discountTotal),
        ];
    }

    protected function mapAppliedCoupon(Coupon $coupon, float $discountTotal): array
    {
        return [
            'id' => $coupon->id,
            'code' => $this->normalizeCode((string) $coupon->code),
            'name' => $coupon->promotion?->name ?? $coupon->code,
            'sku' => $this->couponProductSku($coupon),
            'discount_total' => $this->asMoney($discountTotal),
        ];
    }

    protected function mapCouponRow(Coupon $coupon, float $discountTotal): array
    {
        $sku = $this->couponProductSku($coupon);
        $amount = -1 * abs($discountTotal);

        return [
            'sku' => $sku,
            'code' => $this->normalizeCode((string) $coupon->code),
            'product_name' => $coupon->promotion?->name ?: 'BUONO',
            'product_description' => 'Buono sconto ' . $sku,
            'quantity' => $this->asMoney(1),
            'price' => $this->asMoney($amount),
            'price_net' => $this->asMoney($amount),
            'price_gross' => $this->asMoney($amount),
            'row_subtotal' => $this->asMoney($amount),
            'row_discount_total' => $this->asMoney(0),
            'row_tax_total' => $this->asMoney(0),
            'row_total' => $this->asMoney($amount),
        ];
    }

    protected function emptyResult(): array
    {
        return [
            'line_discounts' => [],
            'cart_discount_total' => $this->asMoney(0),
            'applied_promotions' => [],
            'applied_coupons' => [],
            'coupon_rows' => [],
        ];
    }

    private function resolveCartSubtotal(Cart $cart, Collection $items): float
    {
        unset($cart);

        return round((float) $items->sum(fn ($item) => $this->resolveItemBaseRowTotal($item)), 3);
    }

    private function resolveItemBaseRowTotal(mixed $item): float
    {
        $baseRowTotal = $item->base_row_total ?? null;

        if ($baseRowTotal !== null && $baseRowTotal !== '') {
            return round((float) $baseRowTotal, 3);
        }

        $price = $item->base_price ?? $item->price ?? 0;
        $quantity = (float) ($item->quantity ?? 0);

        return round(((float) $price) * $quantity, 3);
    }

    private function resolvePromotionMinimumSubtotal(Promotion $promotion): ?float
    {
        if (isset($promotion->minimum_subtotal) && $promotion->minimum_subtotal !== null && $promotion->minimum_subtotal !== '') {
            return (float) $promotion->minimum_subtotal;
        }

        if (isset($promotion->min_subtotal) && $promotion->min_subtotal !== null && $promotion->min_subtotal !== '') {
            return (float) $promotion->min_subtotal;
        }

        foreach ($this->getPromotionConditions($promotion) as $condition) {
            $type = Str::lower(trim((string) ($condition['type'] ?? $condition['condition_type'] ?? '')));

            if (!in_array($type, ['minimum_subtotal', 'min_subtotal', 'cart_subtotal_min', 'subtotal_min'], true)) {
                continue;
            }

            $value = $condition['value'] ?? $condition['condition_value'] ?? null;

            if (is_array($value)) {
                $value = $value['value'] ?? $value['amount'] ?? null;
            }

            if ($value !== null && $value !== '') {
                return (float) $value;
            }
        }

        return null;
    }

    private function resolvePromotionDiscountDefinition(Promotion $promotion): ?array
    {
        if (
            isset($promotion->discount_type, $promotion->discount_value)
            && $promotion->discount_type !== null
            && $promotion->discount_value !== null
        ) {
            return [
                'type' => $this->normalizeDiscountType((string) $promotion->discount_type),
                'value' => (float) $promotion->discount_value,
                'scope' => $this->normalizeDiscountScope((string) ($promotion->scope ?? 'cart')),
            ];
        }

        $promotionType = Str::lower(trim((string) ($promotion->type ?? '')));

        if (in_array($promotionType, ['cart_percent', 'cart_percentage'], true)) {
            $value = $this->extractDiscountValueFromActions($promotion);

            if ($value !== null) {
                return ['type' => 'percent', 'value' => $value, 'scope' => 'cart'];
            }
        }

        if (in_array($promotionType, ['cart_fixed', 'cart_amount'], true)) {
            $value = $this->extractDiscountValueFromActions($promotion);

            if ($value !== null) {
                return ['type' => 'fixed', 'value' => $value, 'scope' => 'cart'];
            }
        }

        if (in_array($promotionType, ['product_percent', 'line_percent', 'item_percent'], true)) {
            $value = $this->extractDiscountValueFromActions($promotion);

            if ($value !== null) {
                return ['type' => 'percent', 'value' => $value, 'scope' => 'line'];
            }
        }

        if (in_array($promotionType, ['product_fixed', 'line_fixed', 'item_fixed'], true)) {
            $value = $this->extractDiscountValueFromActions($promotion);

            if ($value !== null) {
                return ['type' => 'fixed', 'value' => $value, 'scope' => 'line'];
            }
        }

        foreach ($this->getPromotionActions($promotion) as $action) {
            $type = Str::lower(trim((string) ($action['type'] ?? $action['action_type'] ?? '')));

            if (!in_array($type, ['discount_percent', 'percentage_discount', 'discount_fixed', 'fixed_discount'], true)) {
                continue;
            }

            $value = $action['value'] ?? $action['amount'] ?? $action['discount_value'] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            return [
                'type' => in_array($type, ['discount_percent', 'percentage_discount'], true) ? 'percent' : 'fixed',
                'value' => (float) $value,
                'scope' => $this->normalizeDiscountScope((string) ($action['scope'] ?? $promotionType ?: 'cart')),
            ];
        }

        return null;
    }

    private function extractDiscountValueFromActions(Promotion $promotion): ?float
    {
        foreach ($this->getPromotionActions($promotion) as $action) {
            $value = $action['value'] ?? $action['amount'] ?? $action['discount_value'] ?? null;

            if ($value !== null && $value !== '') {
                return (float) $value;
            }
        }

        return null;
    }

    private function promotionRequiresCoupon(Promotion $promotion, Store $store): bool
    {
        if ($promotion->id !== null) {
            $hasLinkedCoupons = Coupon::query()
                ->where('promotion_id', $promotion->id)
                ->exists();

            if ($hasLinkedCoupons) {
                return true;
            }
        }

        $promotionCode = $this->normalizeCode((string) ($promotion->code ?? ''));

        if ($promotionCode !== '') {
            $hasCouponWithSameCode = Coupon::query()
                ->where('ditta_cg18', (int) $store->ditta_cg18)
                ->whereRaw('UPPER(code) = ?', [$promotionCode])
                ->where(function ($query) use ($store) {
                    $query->whereNull('site_type')
                        ->orWhere('site_type', (int) $store->erp_site_code);
                })
                ->exists();

            if ($hasCouponWithSameCode) {
                return true;
            }
        }

        foreach ($this->getPromotionConditions($promotion) as $condition) {
            $type = Str::lower(trim((string) ($condition['type'] ?? $condition['condition_type'] ?? '')));

            if (in_array($type, ['coupon', 'coupon_code', 'requires_coupon'], true)) {
                return true;
            }

            $codes = $this->extractCodesFromMixedValue($condition['value'] ?? $condition['condition_value'] ?? null);

            if (empty($codes)) {
                continue;
            }

            $hasMatchingCoupon = Coupon::query()
                ->where('ditta_cg18', (int) $store->ditta_cg18)
                ->whereIn('code', $codes)
                ->where(function ($query) use ($store) {
                    $query->whereNull('site_type')
                        ->orWhere('site_type', (int) $store->erp_site_code);
                })
                ->exists();

            if ($hasMatchingCoupon) {
                return true;
            }
        }

        return false;
    }

    private function promotionContainsCouponCode(Promotion $promotion, string $couponCode): bool
    {
        foreach ($this->getPromotionConditions($promotion) as $condition) {
            $codes = $this->extractCodesFromMixedValue($condition['value'] ?? $condition['condition_value'] ?? null);

            if (in_array($couponCode, $codes, true)) {
                return true;
            }
        }

        return false;
    }

    private function extractCodesFromMixedValue(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            $codes = [];

            foreach ($value as $item) {
                $codes = array_merge($codes, $this->extractCodesFromMixedValue($item));
            }

            return collect($codes)
                ->map(fn ($code) => $this->normalizeCode((string) $code))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return collect(preg_split('/[,;\s]+/', (string) $value) ?: [])
            ->map(fn ($code) => $this->normalizeCode((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function getPromotionConditions(Promotion $promotion): Collection
    {
        $conditions = method_exists($promotion, 'getConditions')
            ? $promotion->getConditions()
            : (is_array($promotion->conditions ?? null) ? $promotion->conditions : []);

        return collect($conditions)->filter(fn ($condition) => is_array($condition))->values();
    }

    private function getPromotionActions(Promotion $promotion): Collection
    {
        $actions = method_exists($promotion, 'getActions')
            ? $promotion->getActions()
            : (is_array($promotion->actions ?? null) ? $promotion->actions : []);

        return collect($actions)->filter(fn ($action) => is_array($action))->values();
    }

    private function couponProductSku(Coupon $coupon): string
    {
        return $this->normalizeSku((string) $coupon->code);
    }

    private function isCouponSku(string $sku): bool
    {
        return str_starts_with($this->normalizeSku($sku), 'MTBUONO');
    }

    private function normalizeDiscountType(string $type): string
    {
        $type = Str::lower(trim($type));

        return in_array($type, ['percent', 'percentage', 'discount_percent', 'percentage_discount'], true)
            ? 'percent'
            : 'fixed';
    }

    private function normalizeDiscountScope(string $scope): string
    {
        $scope = Str::lower(trim($scope));

        return in_array($scope, ['line', 'item', 'product', 'row'], true) ? 'line' : 'cart';
    }

    private function normalizeCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    private function normalizeSku(string $sku): string
    {
        return mb_strtoupper(trim($sku));
    }

    protected function asMoney(float $value): string
    {
        return number_format($value, 3, '.', '');
    }
}