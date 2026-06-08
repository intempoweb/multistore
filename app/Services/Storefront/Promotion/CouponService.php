<?php

namespace App\Services\Storefront\Promotion;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\Store;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CouponService
{
    public function findActiveCouponForCode(Store $store, string $code): ?Coupon
    {
        $normalizedCode = $this->normalizeCode($code);

        if ($normalizedCode === '') {
            return null;
        }

        return Coupon::query()
            ->with('promotion')
            ->active()
            ->valid()
            ->whereRaw('UPPER(code) = ?', [$normalizedCode])
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where(function ($query) use ($store) {
                $query->whereNull('site_type')
                    ->orWhere('site_type', (int) $store->erp_site_code);
            })
            ->orderByDesc('site_type')
            ->orderByDesc('id')
            ->first();
    }

    public function validateForCart(
        Store $store,
        Cart $cart,
        string $code,
        ?Customer $customer = null
    ): array {
        $cart->loadMissing(['items', 'customer']);
        $customer ??= $cart->customer;

        $normalizedCode = $this->normalizeCode($code);

        if ($normalizedCode === '') {
            return [
                'valid' => false,
                'coupon' => null,
                'code' => '',
                'message' => 'Codice coupon non valido.',
            ];
        }

        $coupon = $this->findActiveCouponForCode($store, $normalizedCode);

        if (!$coupon instanceof Coupon) {
            return [
                'valid' => false,
                'coupon' => null,
                'code' => $normalizedCode,
                'message' => 'Codice coupon non valido o scaduto.',
            ];
        }

        $coupon->loadMissing('promotion');

        if (!$this->isCouponValidNow($coupon)) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'code' => $normalizedCode,
                'message' => 'Il periodo di validità di questo coupon è terminato.',
            ];
        }

        if (!$this->matchesStoreContext($coupon, $store)) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'code' => $normalizedCode,
                'message' => 'Coupon non utilizzabile su questo store.',
            ];
        }

        if (!$this->hasRemainingRedemptions($coupon)) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'code' => $normalizedCode,
                'message' => 'Questo coupon è andato esaurito.',
            ];
        }

        if (!$this->hasRemainingRedemptionsForCustomer($coupon, $customer)) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'code' => $normalizedCode,
                'message' => 'Hai già utilizzato questo codice in un ordine precedente.',
            ];
        }

        if (!$this->matchesCartContext($store, $coupon, $cart)) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'code' => $normalizedCode,
                'message' => 'Aggiungi altri prodotti per poter applicare questo coupon.',
            ];
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'code' => $normalizedCode,
            'message' => null,
        ];
    }

    public function applyToCart(
        Store $store,
        Cart $cart,
        string $code,
        ?Customer $customer = null,
        bool $save = true
    ): array {
        $result = $this->validateForCart($store, $cart, $code, $customer);

        if (($result['valid'] ?? false) !== true) {
            return $result;
        }

        /** @var Coupon $coupon */
        $coupon = $result['coupon'];

        $promotion = $this->resolvePromotionForCoupon($store, $coupon);

        $meta = $this->normalizeMeta($cart->meta);
        $meta['coupon'] = [
            'id' => (int) $coupon->id,
            'code' => $this->normalizeCode((string) $coupon->code),
            'promotion_id' => $coupon->promotion_id !== null
                ? (int) $coupon->promotion_id
                : ($promotion instanceof Promotion ? (int) $promotion->id : null),
            'applied_at' => now()->toDateTimeString(),
        ];

        $cart->meta = $meta;

        if ($save) {
            $cart->save();
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'code' => $this->normalizeCode((string) $coupon->code),
            'message' => 'Coupon applicato correttamente.',
        ];
    }

    public function removeFromCart(Cart $cart, bool $save = true): void
    {
        $meta = $this->normalizeMeta($cart->meta);

        unset($meta['coupon']);

        if (isset($meta['promotions']) && is_array($meta['promotions'])) {
            $meta['promotions']['applied_coupons'] = [];
        }

        $cart->meta = $meta;

        if ($save) {
            $cart->save();
        }
    }

    public function extractCouponCodeFromCart(Cart $cart): ?string
    {
        $meta = $this->normalizeMeta($cart->meta);
        $code = data_get($meta, 'coupon.code');

        if (!is_string($code) || trim($code) === '') {
            return null;
        }

        return $this->normalizeCode($code);
    }

    public function resolveCouponForCart(Cart $cart, Store $store): ?Coupon
    {
        $code = $this->extractCouponCodeFromCart($cart);

        if ($code === null) {
            return null;
        }

        return $this->findActiveCouponForCode($store, $code);
    }

    private function matchesStoreContext(Coupon $coupon, Store $store): bool
    {
        if ((int) $coupon->ditta_cg18 !== (int) $store->ditta_cg18) {
            return false;
        }

        if ($coupon->site_type !== null && (int) $coupon->site_type !== (int) $store->erp_site_code) {
            return false;
        }

        return true;
    }

    private function matchesCartContext(Store $store, Coupon $coupon, Cart $cart): bool
    {
        $promotion = $this->resolvePromotionForCoupon($store, $coupon);

        if (!$promotion instanceof Promotion) {
            return true;
        }

        if (!$this->promotionMatchesCouponCode($promotion, (string) $coupon->code)) {
            return false;
        }

        $minimumSubtotal = $this->resolvePromotionMinimumSubtotal($promotion);

        if ($minimumSubtotal <= 0) {
            return true;
        }

        $currentSubtotal = (float) $cart->items->sum(function ($item) {
            $price = $item->base_price ?? $item->price ?? 0;
            $quantity = (float) ($item->quantity ?? 0);

            return (float) $price * $quantity;
        });

        return $currentSubtotal >= $minimumSubtotal;
    }

    private function resolvePromotionForCoupon(Store $store, Coupon $coupon): ?Promotion
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

                return $this->promotionMatchesCouponCode($promotion, $couponCode);
            });
    }

    private function hasRemainingRedemptions(Coupon $coupon): bool
    {
        if ($coupon->usage_limit === null) {
            return true;
        }

        return (int) ($coupon->used_count ?? 0) < (int) $coupon->usage_limit;
    }

    private function hasRemainingRedemptionsForCustomer(Coupon $coupon, ?Customer $customer): bool
    {
        if (!$customer instanceof Customer) {
            return true;
        }

        $limitPerCustomer = $this->resolveUsageLimitPerCustomer($coupon);

        if ($limitPerCustomer === null) {
            return true;
        }

        $usedCount = Order::query()
            ->where('customer_id', $customer->id)
            ->where('ditta_cg18', (int) $coupon->ditta_cg18)
            ->whereRaw('UPPER(coupon_code) = ?', [$this->normalizeCode((string) $coupon->code)])
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->count();

        return $usedCount < $limitPerCustomer;
    }

    private function isCouponValidNow(Coupon $coupon, ?CarbonInterface $now = null): bool
    {
        $now ??= now();

        if (!(bool) ($coupon->is_active ?? false)) {
            return false;
        }

        if ($coupon->starts_at !== null && $coupon->starts_at->gt($now)) {
            return false;
        }

        if ($coupon->expires_at !== null && $coupon->expires_at->lt($now)) {
            return false;
        }

        return $this->hasRemainingRedemptions($coupon);
    }

    private function resolvePromotionMinimumSubtotal(Promotion $promotion): float
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

        return 0.0;
    }

    private function promotionMatchesCouponCode(Promotion $promotion, string $couponCode): bool
    {
        $normalizedCouponCode = $this->normalizeCode($couponCode);

        if ($this->normalizeCode((string) ($promotion->code ?? '')) === $normalizedCouponCode) {
            return true;
        }

        foreach ($this->getPromotionConditions($promotion) as $condition) {
            $type = Str::lower(trim((string) ($condition['type'] ?? $condition['condition_type'] ?? '')));

            if (!in_array($type, ['coupon', 'coupon_code', 'requires_coupon'], true)) {
                continue;
            }

            $codes = $this->extractCodesFromMixedValue($condition['value'] ?? $condition['condition_value'] ?? null);

            if (empty($codes)) {
                return true;
            }

            return in_array($normalizedCouponCode, $codes, true);
        }

        return true;
    }

    private function resolveUsageLimitPerCustomer(Coupon $coupon): ?int
    {
        if (isset($coupon->usage_limit_per_customer) && $coupon->usage_limit_per_customer !== null) {
            return max(1, (int) $coupon->usage_limit_per_customer);
        }

        $promotion = $coupon->promotion;

        if (!$promotion instanceof Promotion) {
            return 1;
        }

        foreach ($this->getPromotionConditions($promotion) as $condition) {
            $type = Str::lower(trim((string) ($condition['type'] ?? $condition['condition_type'] ?? '')));

            if (!in_array($type, ['usage_limit_per_customer', 'per_customer_limit', 'customer_limit', 'once_per_customer'], true)) {
                continue;
            }

            $value = $condition['value'] ?? $condition['condition_value'] ?? null;

            if ($type === 'once_per_customer') {
                if (is_array($value)) {
                    $flag = $value['value'] ?? $value['enabled'] ?? true;
                    return filter_var($flag, FILTER_VALIDATE_BOOLEAN) ? 1 : null;
                }

                if ($value === null || $value === '') {
                    return 1;
                }

                return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : null;
            }

            if (is_array($value)) {
                $value = $value['value'] ?? $value['limit'] ?? null;
            }

            if ($value !== null && $value !== '') {
                return max(1, (int) $value);
            }
        }

        return 1;
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

    private function normalizeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function normalizeCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }
}