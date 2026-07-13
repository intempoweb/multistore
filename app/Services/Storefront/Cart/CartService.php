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
        $customer = $this->resolveCustomer($customer, $store);
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
        $customer = $this->resolveCustomer($customer, $store);

        return $this->createNewCart(
            store: $store,
            customer: $customer,
            sessionId: $sessionId,
        );
    }

    public function getOrCreate(Store $store, ?Customer $customer = null): Cart
    {
        $customer = $this->resolveCustomer($customer, $store);

        $cart = $this->getOrCreateWithoutRecalculate($store, $customer);

        return $this->recalculate($cart, $customer);
    }

    public function claimGuestCart(Store $store, Customer $customer, ?Cart $guestCart = null): Cart
    {
        $guestCart ??= $this->current($store, null);

        if (!$guestCart instanceof Cart || (int) $guestCart->customer_id === (int) $customer->id) {
            return $this->getOrCreate($store, $customer);
        }

        return DB::transaction(function () use ($store, $customer, $guestCart) {
            $lockedGuestCart = Cart::query()
                ->whereKey($guestCart->id)
                ->where('status', 'active')
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedGuestCart instanceof Cart) {
                return $this->getOrCreate($store, $customer);
            }

            Cart::query()
                ->where('store_id', $store->id)
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->where('id', '<>', $lockedGuestCart->id)
                ->update(['status' => 'abandoned']);

            $lockedGuestCart->forceFill([
                'customer_id' => $customer->id,
                'session_id' => $this->resolveSessionId(),
                'expires_at' => now()->addDays($store->cartLifetimeDays()),
            ]);

            $this->fillCustomerSnapshot($lockedGuestCart, $customer);
            $lockedGuestCart->save();

            return $this->recalculate(
                $lockedGuestCart->fresh(['items', 'customer', 'store', 'shippingAddress']),
                $customer
            );
        });
    }

    public function addProduct(Store $store, Product $product, float|int $quantity, ?Customer $customer = null): Cart
    {
        $customer = $this->resolveCustomer($customer, $store);

        return DB::transaction(function () use ($store, $product, $quantity, $customer) {
            $cart = $this->getOrCreateWithoutRecalculate($store, $customer);
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

    public function addProducts(Store $store, iterable $items, ?Customer $customer = null): Cart
    {
        $customer = $this->resolveCustomer($customer, $store);

        return DB::transaction(function () use ($store, $items, $customer) {
            $cart = $this->getOrCreateWithoutRecalculate($store, $customer);
            $this->assertCartStoreContext($cart, $store);

            $cart->loadMissing(['items', 'customer', 'store', 'shippingAddress']);

            foreach ($items as $row) {
                $product = $row['product'] ?? null;
                $quantity = (float) ($row['quantity'] ?? $row['qty'] ?? 0);

                if (!$product instanceof Product || $quantity <= 0) {
                    continue;
                }

                $this->cartItemService->addOrUpdateProduct(
                    cart: $cart,
                    store: $store,
                    product: $product,
                    quantity: $quantity,
                    customer: $customer
                );
            }

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
            throw new InvalidArgumentException(__('themes_b2c.cart.shipping_address_not_compatible'));
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
            throw new InvalidArgumentException(__('themes_b2c.cart.store_not_linked'));
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

        $result['cart'] = $this->recalculate(
            $cart->fresh(['items', 'customer', 'store', 'shippingAddress']),
            $customer,
            syncCoupon: false
        );

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

        $cart->expires_at = now()->addDays($cart->cartLifetimeDays());
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

    protected function getOrCreateWithoutRecalculate(Store $store, ?Customer $customer = null): Cart
    {
        $customer = $this->resolveCustomer($customer, $store);

        $existing = $this->current($store, $customer);

        if ($existing instanceof Cart) {
            return $existing->fresh(['items', 'customer', 'store', 'shippingAddress']);
        }

        return $this->createNewCart(
            store: $store,
            customer: $customer,
            sessionId: $this->resolveSessionId(),
        )->fresh(['items', 'customer', 'store', 'shippingAddress']);
    }

    protected function resolveCustomer(?Customer $customer = null, ?Store $store = null): ?Customer
    {
        if ($customer instanceof Customer) {
            return $customer;
        }

        $contextId = (string) request()->query('agent_context', '');

        if ($contextId !== '' && session()->get('agent_mode') === true) {
            $context = session()->get("agent_contexts.$contextId");

            if (is_array($context) && !empty($context['customer_id'])) {
                $query = Customer::query()
                    ->active()
                    ->webEnabled()
                    ->where('id', (int) $context['customer_id']);

                if ($store instanceof Store) {
                    $query->where('ditta_cg18', (int) $store->ditta_cg18);
                }

                $contextCustomer = $query->first();

                if ($contextCustomer instanceof Customer) {
                    return $contextCustomer;
                }
            }
        }

        $authCustomer = auth('customer')->user();

        return $authCustomer instanceof Customer ? $authCustomer : null;
    }

    protected function createNewCart(Store $store, ?Customer $customer = null, ?string $sessionId = null): Cart
    {
        $cart = Cart::query()->create([
            'store_id' => $store->id,
            'channel' => $store->channel(),
            'cart_token' => (string) Str::uuid(),
            'ditta_cg18' => (int) $store->ditta_cg18,
            'site_type' => (int) $store->erp_site_code,
            'store_code' => (string) ($store->site_code ?? $store->erp_site_code),
            'is_b2b' => $store->isB2B(),
            'customer_id' => $customer?->id,
            'session_id' => $sessionId,
            'expires_at' => now()->addDays($store->cartLifetimeDays()),
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

        if ($cart->isB2C()) {
            $shippingAddress = trim((string) ($customer->indircor_cg16 ?: $customer->indirizzo_cg16));
            $shippingZip = trim((string) ($customer->capcor_cg16 ?: $customer->cap_cg16));
            $shippingCity = trim((string) ($customer->cittacor_cg16 ?: $customer->citta_cg16));
            $shippingProvince = trim((string) ($customer->provcor_cg16 ?: $customer->prov_cg16));

            $cart->shipping_name = $cart->shipping_name ?: $cart->customer_name;
            $cart->shipping_address = $cart->shipping_address ?: ($shippingAddress !== '' ? $shippingAddress : null);
            $cart->shipping_zip = $cart->shipping_zip ?: ($shippingZip !== '' ? $shippingZip : null);
            $cart->shipping_city = $cart->shipping_city ?: ($shippingCity !== '' ? $shippingCity : null);
            $cart->shipping_province = $cart->shipping_province ?: ($shippingProvince !== '' ? $shippingProvince : null);

            $meta = is_array($cart->meta) ? $cart->meta : (json_decode((string) $cart->meta, true) ?: []);
            $meta['checkout'] = array_merge([
                'shipping_first_name' => $customer->nomeconnweb,
                'shipping_last_name' => $customer->cognomeconnweb,
                'shipping_email' => $customer->indemail_cg16,
                'shipping_phone' => $customer->cellnum_cg16 ?: $customer->tel1num_cg16,
                'billing_company' => $customer->ragsoanag_cg16,
                'billing_first_name' => $customer->nomeconnweb,
                'billing_last_name' => $customer->cognomeconnweb,
                'billing_email' => $customer->indemailperfatt_cg16 ?: $customer->indemail_cg16,
                'billing_address_line_1' => $customer->indirizzo_cg16,
                'billing_postcode' => $customer->cap_cg16,
                'billing_city' => $customer->citta_cg16,
                'billing_province' => $customer->prov_cg16,
                'billing_tax_code' => $customer->codfiscale_cg16,
                'billing_vat_number' => $customer->partiva_cg16,
                'billing_pec' => $customer->email_pec_cg16,
            ], $meta['checkout'] ?? []);
            $cart->meta = $meta;
        }
    }

    protected function refreshItemSnapshots(Cart $cart, ?Customer $customer = null): void
    {
        $cart->loadMissing(['items', 'store']);

        $store = $cart->store;

        if (!$store instanceof Store) {
            throw new InvalidArgumentException(__('themes_b2c.cart.store_not_linked'));
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
        $unavailableNeedles = [
            __('themes_b2c.cart.quantity_not_available'),
            'Quantità non disponibile',
            'Quantity not available',
        ];

        $isQuantityError = collect($unavailableNeedles)
            ->filter(fn ($needle) => trim((string) $needle) !== '')
            ->contains(fn ($needle) => str_contains($message, (string) $needle));

        if (!$isQuantityError) {
            return null;
        }

        if (!preg_match('/(?:Disponibili solo|Only)\s+([0-9]+(?:[,.][0-9]+)?)/i', $message, $matches)) {
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
            throw new InvalidArgumentException(__('themes_b2c.cart.company_not_compatible'));
        }

        if ($cart->site_type !== null && (int) $cart->site_type !== (int) $store->erp_site_code) {
            throw new InvalidArgumentException(__('themes_b2c.cart.site_type_not_compatible'));
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
