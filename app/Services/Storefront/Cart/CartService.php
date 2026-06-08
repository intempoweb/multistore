<?php

namespace App\Services\Storefront\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\CustomerShippingAddress;
use App\Models\Product;
use App\Models\Store;
use App\Services\Storefront\Promotion\CouponService;
use App\Services\Storefront\Promotion\PromotionEngine;
use App\Services\Storefront\Totals\CartTotalsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CartService
{
    public function __construct(
        protected CartItemService $cartItemService,
        protected CartTotalsService $cartTotalsService,
        protected CouponService $couponService,
        protected PromotionEngine $promotionEngine,
    ) {
    }

    public function current(Store $store, ?Customer $customer = null): ?Cart
    {
        $customer ??= auth('customer')->user();
        $sessionId = $this->resolveSessionId();

        $query = Cart::query()
            ->with(['items'])
            ->where('status', 'active')
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where(function ($sub) use ($store) {
                $sub->where('store_id', $store->id)
                    ->orWhere(function ($fallback) use ($store) {
                        $fallback->whereNull('store_id')
                            ->where('site_type', (int) $store->erp_site_code);
                    });
            });

        if ($customer instanceof Customer) {
            $query->where('customer_id', $customer->id);
        } elseif (!empty($sessionId)) {
            $query->where('session_id', $sessionId);
        } else {
            return null;
        }

        return $query->latest('id')->first();
    }

    public function createCart(Store $store, ?Customer $customer = null, ?string $sessionId = null): Cart
    {
        $customer ??= auth('customer')->user();

        return $this->createNewCart(
            store: $store,
            customer: $customer,
            sessionId: $sessionId,
        );
    }

    public function getOrCreate(Store $store, ?Customer $customer = null): Cart
    {
        $customer ??= auth('customer')->user();

        $existing = $this->current($store, $customer);

        if ($existing instanceof Cart) {
            return $this->recalculate($existing, $customer);
        }

        $cart = $this->createNewCart(
            store: $store,
            customer: $customer,
            sessionId: $this->resolveSessionId(),
        );

        return $this->recalculate($cart, $customer);
    }

    public function addProduct(Store $store, Product $product, float|int $quantity, ?Customer $customer = null): Cart
    {
        $customer ??= auth('customer')->user();

        return DB::transaction(function () use ($store, $product, $quantity, $customer) {
            $cart = $this->getOrCreate($store, $customer);
            $this->assertCartStoreContext($cart, $store);

            $this->cartItemService->addOrUpdateProduct(
                cart: $cart,
                store: $store,
                product: $product,
                quantity: (float) $quantity,
                customer: $customer
            );

            return $this->recalculate($cart->fresh(['items', 'customer', 'store', 'shippingAddress']), $customer);
        });
    }

    public function updateItemQuantity(CartItem $item, float|int $quantity, ?Customer $customer = null): Cart
    {
        $cart = $item->cart()->with(['items', 'store', 'customer'])->firstOrFail();
        $customer ??= $cart->customer;

        return DB::transaction(function () use ($item, $cart, $quantity, $customer) {
            if ((float) $quantity <= 0) {
                if ($this->cartItemService->isCouponDiscountItem($item)) {
                    return $this->recalculate($cart->fresh(['items', 'customer', 'store', 'shippingAddress']), $customer);
                }

                $item->delete();

                return $this->recalculate($cart->fresh(['items', 'customer', 'store', 'shippingAddress']), $customer);
            }

            $this->cartItemService->updateQuantity(
                item: $item,
                quantity: (float) $quantity,
                customer: $customer
            );

            return $this->recalculate($cart->fresh(['items', 'customer', 'store', 'shippingAddress']), $customer);
        });
    }

    public function removeItem(CartItem $item): Cart
    {
        $cart = $item->cart()->with(['items', 'customer', 'store', 'shippingAddress'])->firstOrFail();

        if (!$this->cartItemService->isCouponDiscountItem($item)) {
            $item->delete();
        }

        return $this->recalculate($cart->fresh(['items', 'customer', 'store', 'shippingAddress']), $cart->customer);
    }

    public function clear(Cart $cart): Cart
    {
        $cart->items()->delete();

        return $this->recalculate($cart->fresh(['items', 'customer', 'store', 'shippingAddress']), $cart->customer);
    }

    public function assignShippingAddress(Cart $cart, ?CustomerShippingAddress $address): Cart
    {
        if ($address === null) {
            $cart->fill([
                'shipping_address_id' => null,
                'shipping_name' => null,
                'shipping_address' => null,
                'shipping_zip' => null,
                'shipping_city' => null,
                'shipping_province' => null,
                'shipping_country' => null,
            ]);

            $cart->save();

            return $this->recalculate($cart->fresh(['items', 'customer', 'store', 'shippingAddress']), $cart->customer);
        }

        if ((int) $address->ditta_cg18 !== (int) $cart->ditta_cg18) {
            throw new InvalidArgumentException('Indirizzo spedizione non compatibile con il carrello.');
        }

        $cart->fill([
            'shipping_address_id' => $address->id,
            'shipping_name' => $address->destragsoc_mg22,
            'shipping_address' => $address->destind_mg22,
            'shipping_zip' => $address->destcap_mg22,
            'shipping_city' => $address->destcitta_mg22,
            'shipping_province' => $address->destprov_mg22,
            'shipping_country' => $address->statoest_cg07 ? (string) $address->statoest_cg07 : 'IT',
        ]);

        $cart->save();

        return $this->recalculate($cart->fresh(['items', 'customer', 'store', 'shippingAddress']), $cart->customer);
    }

    public function setNotes(Cart $cart, ?string $notes): Cart
    {
        $cart->notes = $this->nullableString($notes);
        $cart->save();

        return $cart->fresh(['items', 'customer', 'store', 'shippingAddress']);
    }

    public function applyCoupon(Cart $cart, string $code, ?Customer $customer = null): array
    {
        $customer ??= $cart->customer;

        $cart->loadMissing(['items', 'customer', 'store', 'shippingAddress']);

        $store = $cart->store;

        if (!$store instanceof Store) {
            throw new InvalidArgumentException('Store non associato al carrello.');
        }

        $result = $this->couponService->applyToCart(
            store: $store,
            cart: $cart,
            code: $code,
            customer: $customer,
            save: true,
        );

        if (($result['valid'] ?? false) !== true) {
            return $result;
        }

        $updatedCart = $this->recalculate(
            $cart->fresh(['items', 'customer', 'store', 'shippingAddress']),
            $customer,
            syncCoupon: false
        );

        $result['cart'] = $updatedCart;

        return $result;
    }

    public function removeCoupon(Cart $cart, ?Customer $customer = null): Cart
    {
        $customer ??= $cart->customer;

        $this->couponService->removeFromCart($cart, true);
        $this->cartItemService->removeCouponDiscountRows($cart);

        return $this->recalculate(
            $cart->fresh(['items', 'customer', 'store', 'shippingAddress']),
            $customer,
            syncCoupon: false
        );
    }

    public function recalculate(Cart $cart, ?Customer $customer = null, bool $syncCoupon = true): Cart
    {
        $customer ??= $cart->customer;

        $cart->loadMissing(['items', 'customer', 'store', 'shippingAddress']);

        $this->fillCustomerSnapshot($cart, $customer);

        $cart->expires_at = now()->addDays(($cart->is_b2b ?? false) ? 30 : 7);
        $cart->save();

        $this->refreshItemSnapshots(
            $cart->fresh(['items', 'customer', 'store', 'shippingAddress']),
            $customer
        );

        $cart = $cart->fresh(['items', 'customer', 'store', 'shippingAddress']);

        if ($syncCoupon) {
            $this->syncCouponState($cart, $customer);
            $cart = $cart->fresh(['items', 'customer', 'store', 'shippingAddress']);
        }

        $this->syncCouponDiscountRows($cart, $customer);
        $cart = $cart->fresh(['items', 'customer', 'store', 'shippingAddress']);

        return $this->cartTotalsService->recalculate($cart);
    }

    public function decorateItems(iterable $items, Store $store): Collection
    {
        return $this->cartItemService->decorateItems($items, $store);
    }

    public function resolveQuantityConstraintsForProduct(Product $product): array
    {
        return $this->cartItemService->resolveQuantityConstraintsForProduct($product);
    }

    protected function createNewCart(Store $store, ?Customer $customer = null, ?string $sessionId = null): Cart
    {
        $cart = Cart::query()->create([
            'store_id' => $store->id,
            'channel' => $store->is_b2b ? 'b2b' : 'b2c',
            'cart_token' => (string) Str::uuid(),
            'ditta_cg18' => (int) $store->ditta_cg18,
            'site_type' => (int) $store->erp_site_code,
            'store_code' => (string) ($store->site_code ?? $store->erp_site_code),
            'is_b2b' => (bool) $store->is_b2b,
            'customer_id' => $customer?->id,
            'session_id' => $sessionId,
            'expires_at' => now()->addDays($store->is_b2b ? 30 : 7),
            'status' => 'active',
            'currency' => 'EUR',
        ]);

        $this->fillCustomerSnapshot($cart, $customer);
        $cart->save();

        return $cart->fresh(['items']);
    }

    protected function fillCustomerSnapshot(Cart $cart, ?Customer $customer): void
    {
        if (!$customer instanceof Customer) {
            return;
        }

        $customerName = trim(implode(' ', array_filter([
            $customer->nomeconnweb,
            $customer->cognomeconnweb,
        ])));

        $cart->customer_id = $customer->id;
        $cart->customer_name = $customerName !== ''
            ? $customerName
            : ($customer->ragsoanag_cg16 ?? null);
        $cart->customer_email = $customer->indemail_cg16;
        $cart->customer_clifor_cg44 = $customer->clifor_cg44;
    }

    protected function refreshItemSnapshots(Cart $cart, ?Customer $customer = null): void
    {
        $cart->loadMissing(['items', 'store']);

        $store = $cart->store;

        if (!$store instanceof Store) {
            throw new InvalidArgumentException('Store non associato al carrello.');
        }

        foreach ($cart->items as $item) {
            if ($this->cartItemService->isCouponDiscountItem($item)) {
                continue;
            }

            try {
                $this->cartItemService->updateQuantity(
                    item: $item,
                    quantity: (float) ($item->quantity ?? 0),
                    customer: $customer
                );
            } catch (InvalidArgumentException $exception) {
                $availableQuantity = $this->extractAvailableQuantityFromException($exception);

                if ($availableQuantity === null) {
                    throw $exception;
                }

                if ($availableQuantity <= 0) {
                    $item->delete();
                    continue;
                }

                $this->cartItemService->updateQuantity(
                    item: $item,
                    quantity: $availableQuantity,
                    customer: $customer
                );
            }
        }
    }

    protected function extractAvailableQuantityFromException(InvalidArgumentException $exception): ?float
    {
        $message = $exception->getMessage();

        if (!str_contains($message, 'Quantità non disponibile')) {
            return null;
        }

        if (!preg_match('/Disponibili solo\s+([0-9]+(?:[,.][0-9]+)?)/i', $message, $matches)) {
            return null;
        }

        return (float) str_replace(',', '.', $matches[1]);
    }

    protected function syncCouponState(Cart $cart, ?Customer $customer = null): void
    {
        $cart->loadMissing(['items', 'customer', 'store']);
        $customer ??= $cart->customer;

        $store = $cart->store;

        if (!$store instanceof Store) {
            $this->cartItemService->removeCouponDiscountRows($cart);
            return;
        }

        $couponCode = $this->couponService->extractCouponCodeFromCart($cart);

        if ($couponCode === null) {
            $this->cartItemService->removeCouponDiscountRows($cart);
            return;
        }

        $validation = $this->couponService->validateForCart(
            store: $store,
            cart: $cart,
            code: $couponCode,
            customer: $customer,
        );

        if (($validation['valid'] ?? false) === true) {
            return;
        }

        $this->couponService->removeFromCart($cart, true);
        $this->cartItemService->removeCouponDiscountRows($cart);
        $this->cartTotalsService->recalculate(
            $cart->fresh(['items', 'customer', 'store', 'shippingAddress'])
        );
    }

    protected function syncCouponDiscountRows(Cart $cart, ?Customer $customer = null): void
    {
        $cart->loadMissing(['items', 'customer', 'store']);
        $customer ??= $cart->customer;

        $store = $cart->store;

        if (!$store instanceof Store) {
            $this->cartItemService->removeCouponDiscountRows($cart);
            return;
        }

        $couponCode = $this->couponService->extractCouponCodeFromCart($cart);

        if ($couponCode === null) {
            $this->cartItemService->removeCouponDiscountRows($cart);
            return;
        }

        $validation = $this->couponService->validateForCart(
            store: $store,
            cart: $cart,
            code: $couponCode,
            customer: $customer,
        );

        if (($validation['valid'] ?? false) !== true) {
            $this->cartItemService->removeCouponDiscountRows($cart);
            return;
        }

        $promotionResult = $this->promotionEngine->evaluate($cart, $couponCode);

        $couponRows = is_array($promotionResult['coupon_rows'] ?? null)
            ? $promotionResult['coupon_rows']
            : [];

        if (empty($couponRows)) {
            $this->cartItemService->removeCouponDiscountRows($cart);
            return;
        }

        $this->cartItemService->syncCouponDiscountRows($cart, $couponRows);
    }

    protected function assertCartStoreContext(Cart $cart, Store $store): void
    {
        if ((int) $cart->ditta_cg18 !== (int) $store->ditta_cg18) {
            throw new InvalidArgumentException('Carrello non compatibile con la ditta dello store.');
        }

        if ($cart->site_type !== null && (int) $cart->site_type !== (int) $store->erp_site_code) {
            throw new InvalidArgumentException('Carrello non compatibile con il site type dello store.');
        }
    }

    protected function resolveSessionId(): ?string
    {
        $sessionId = session()->getId();

        return $sessionId !== '' ? $sessionId : null;
    }

    protected function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}