<?php

namespace App\Data\Shipping;

use App\Models\Cart;
use App\Models\Store;
use InvalidArgumentException;

final class ShippingContext
{
    public function __construct(
        public readonly Store $store,
        public readonly float $subtotal,
        public readonly float $weight,
        public readonly ?string $country,
        public readonly ?string $province,
        public readonly ?string $cap,
        public readonly bool $isB2b,
    ) {
    }

    public static function fromCart(Cart $cart, float $subtotal): self
    {
        $relations = ['items', 'store', 'customer'];

        if (!empty($cart->shipping_address_id)) {
            $relations[] = 'shippingAddress';
        }

        $cart->loadMissing($relations);

        if (!$cart->store instanceof Store) {
            throw new InvalidArgumentException('Store non associato al carrello.');
        }

        $weight = (float) collect($cart->items ?? [])->sum(
            fn ($item) => ((float) ($item->weight ?? 0)) * ((float) ($item->quantity ?? 0))
        );

        $isB2b = (bool) ($cart->store->is_b2b ?? false);

        $country = $isB2b
            ? self::normalizeNullableString(
                $cart->shipping_country
                    ?? $cart->shippingAddress?->statoest_cg07
                    ?? $cart->customer?->statoestero_cg16,
                true
            )
            : self::normalizeNullableString($cart->shipping_country, true);

        $province = $isB2b
            ? self::normalizeNullableString(
                $cart->shipping_province
                    ?? $cart->shippingAddress?->destprov_mg22
                    ?? $cart->customer?->prov_cg16,
                true
            )
            : self::normalizeNullableString($cart->shipping_province, true);

        $cap = $isB2b
            ? self::normalizeNullableString(
                $cart->shipping_zip
                    ?? $cart->shippingAddress?->destcap_mg22
                    ?? $cart->customer?->cap_cg16,
                true
            )
            : self::normalizeNullableString($cart->shipping_zip, true);

        logger()->info('ShippingContext resolved', [
            'is_b2b' => $isB2b,
            'shipping_address_id' => $cart->shipping_address_id,
            'cart_shipping_country' => $cart->shipping_country,
            'cart_shipping_province' => $cart->shipping_province,
            'cart_shipping_zip' => $cart->shipping_zip,
            'resolved_country' => $country,
            'resolved_province' => $province,
            'resolved_cap' => $cap,
            'weight' => round($weight, 3),
            'subtotal' => round($subtotal, 3),
        ]);

        return new self(
            store: $cart->store,
            subtotal: round($subtotal, 3),
            weight: round($weight, 3),
            country: $country,
            province: $province,
            cap: $cap,
            isB2b: $isB2b,
        );
    }

    public function toArray(): array
    {
        return [
            'store' => $this->store,
            'subtotal' => $this->subtotal,
            'weight' => $this->weight,
            'country' => $this->country,
            'province' => $this->province,
            'cap' => $this->cap,
            'is_b2b' => $this->isB2b,
        ];
    }

    private static function normalizeNullableString(mixed $value, bool $uppercase = false): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $uppercase ? strtoupper($value) : $value;
    }
}