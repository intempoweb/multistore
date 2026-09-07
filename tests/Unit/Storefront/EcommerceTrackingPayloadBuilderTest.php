<?php

namespace Tests\Unit\Storefront;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Services\Storefront\Analytics\EcommerceTrackingPayloadBuilder;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class EcommerceTrackingPayloadBuilderTest extends TestCase
{
    public function test_it_builds_add_to_cart_payload_for_simple_variant(): void
    {
        $store = new Store(['name' => 'B2C CIAK']);
        $product = new Product([
            'sku' => 'SKU-RED-A5',
            'parent_code' => 'SKU-PARENT',
            'marca_mg64' => 'Ciak',
            'fam_99' => 'Agenda',
            'sfam_99' => 'Settimanale',
        ]);
        $cart = new Cart(['currency' => 'EUR']);
        $item = new CartItem([
            'sku' => 'SKU-RED-A5',
            'product_name' => 'Agenda rossa A5',
            'quantity' => 2,
            'price_net' => 12.5,
            'row_subtotal' => 25,
            'row_discount_total' => 5,
        ]);

        $item->setRelation('cart', $cart);
        $item->setRelation('product', $product);

        $payload = (new EcommerceTrackingPayloadBuilder())->addToCart($store, $item);

        $this->assertSame('add_to_cart', $payload['event']);
        $this->assertSame('EUR', $payload['ecommerce']['currency']);
        $this->assertSame(25.0, $payload['ecommerce']['value']);
        $this->assertSame('SKU-RED-A5', $payload['ecommerce']['items'][0]['item_id']);
        $this->assertSame('SKU-PARENT', $payload['ecommerce']['items'][0]['item_group_id']);
        $this->assertSame('Ciak', $payload['ecommerce']['items'][0]['item_brand']);
        $this->assertSame(2.5, $payload['ecommerce']['items'][0]['discount']);
    }

    public function test_it_builds_begin_checkout_payload_from_cart(): void
    {
        $store = new Store(['name' => 'B2B INTEMPO']);
        $cart = new Cart([
            'currency' => 'EUR',
            'subtotal' => 78,
        ]);
        $item = new CartItem([
            'sku' => 'BAG-001',
            'product_name' => 'Borsa pelle',
            'quantity' => 1,
            'price_net' => 78,
            'row_subtotal' => 78,
        ]);

        $cart->setRelation('items', new Collection([$item]));

        $payload = (new EcommerceTrackingPayloadBuilder())->beginCheckout($store, $cart);

        $this->assertSame('begin_checkout', $payload['event']);
        $this->assertSame(78.0, $payload['ecommerce']['value']);
        $this->assertSame('BAG-001', $payload['ecommerce']['items'][0]['item_id']);
    }

    public function test_it_builds_purchase_payload_with_transaction_id(): void
    {
        $store = new Store(['name' => 'Ready']);
        $order = new Order([
            'order_number' => 'ORD-123',
            'currency' => 'EUR',
            'subtotal' => 100,
            'shipping_total' => 7.5,
            'tax_total' => 0,
            'coupon_code' => 'WELCOME',
        ]);
        $item = new OrderItem([
            'sku' => 'READY-001',
            'product_name' => 'Zaino Ready',
            'quantity' => 1,
            'price_net' => 100,
            'row_subtotal' => 100,
        ]);

        $order->setRelation('items', new Collection([$item]));

        $payload = (new EcommerceTrackingPayloadBuilder())->purchase($store, $order);

        $this->assertSame('purchase', $payload['event']);
        $this->assertSame('ORD-123', $payload['ecommerce']['transaction_id']);
        $this->assertSame('WELCOME', $payload['ecommerce']['coupon']);
        $this->assertSame(7.5, $payload['ecommerce']['shipping']);
        $this->assertSame('READY-001', $payload['ecommerce']['items'][0]['item_id']);
    }
}
