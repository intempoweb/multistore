<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_order_when_stripe_webhook_signature_is_valid(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);

        $order = Order::query()->create([
            'channel' => 'b2c',
            'ditta_cg18' => 1,
            'site_type' => 7,
            'order_number' => '1726000000000001',
            'status' => 'processing',
            'payment_status' => 'authorized',
            'payment_gateway' => 'stripe',
            'payment_transaction_id' => 'pi_test_123',
            'currency' => 'EUR',
            'grand_total' => 19.80,
            'subtotal' => 13.90,
            'shipping_total' => 5.90,
            'discount_total' => 0,
            'tax_total' => 0,
            'placed_at' => now(),
        ]);

        $payload = json_encode([
            'id' => 'evt_test_123',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123',
                    'object' => 'payment_intent',
                    'status' => 'succeeded',
                    'metadata' => [
                        'order_id' => (string) $order->id,
                        'order_number' => $order->order_number,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $this->stripeSignature($payload, 'whsec_test_secret'),
            ],
            $payload
        );

        $response->assertOk();

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('payment_intent.succeeded', data_get($order->meta, 'stripe.last_webhook_event'));
    }

    public function test_it_rejects_stripe_webhook_when_signature_is_invalid(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);

        $order = Order::query()->create([
            'channel' => 'b2c',
            'ditta_cg18' => 1,
            'site_type' => 7,
            'order_number' => '1726000000000002',
            'status' => 'processing',
            'payment_status' => 'authorized',
            'payment_gateway' => 'stripe',
            'payment_transaction_id' => 'pi_test_456',
            'currency' => 'EUR',
            'grand_total' => 19.80,
            'subtotal' => 13.90,
            'shipping_total' => 5.90,
            'discount_total' => 0,
            'tax_total' => 0,
            'placed_at' => now(),
        ]);

        $payload = json_encode([
            'id' => 'evt_test_456',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_456',
                    'object' => 'payment_intent',
                    'status' => 'succeeded',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/api/webhooks/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=' . time() . ',v1=invalid',
            ],
            $payload
        );

        $response->assertStatus(400);

        $this->assertSame('authorized', $order->fresh()->payment_status);
    }

    private function stripeSignature(string $payload, string $secret): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        return 't=' . $timestamp . ',v1=' . $signature;
    }
}
