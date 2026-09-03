<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_paypal_webhook_updates_order_only_after_signature_verification(): void
    {
        config([
            'services.paypal.mode' => 'sandbox',
            'services.paypal.client_id' => 'paypal-client',
            'services.paypal.client_secret' => 'paypal-secret',
            'services.paypal.webhook_id' => 'WH-test',
        ]);

        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'access-token',
            ], 200),
            'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ], 200),
        ]);

        $order = $this->makeOrder([
            'payment_gateway' => 'paypal',
            'payment_status' => 'authorized',
            'payment_transaction_id' => 'PAYPAL-ORDER-123',
        ]);

        $response = $this->withHeaders($this->paypalHeaders())->postJson('/api/webhooks/paypal', [
            'id' => 'WH-EVENT-123',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'custom_id' => (string) $order->id,
                'id' => 'CAPTURE-123',
                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' => 'PAYPAL-ORDER-123',
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
    }

    public function test_paypal_webhook_rejects_missing_or_invalid_signature_headers(): void
    {
        config(['services.paypal.webhook_id' => 'WH-test']);

        $order = $this->makeOrder([
            'payment_gateway' => 'paypal',
            'payment_status' => 'authorized',
            'payment_transaction_id' => 'PAYPAL-ORDER-456',
        ]);

        $response = $this->postJson('/api/webhooks/paypal', [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'custom_id' => (string) $order->id,
                'id' => 'CAPTURE-456',
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame('authorized', $order->fresh()->payment_status);
    }

    public function test_sendcloud_webhook_rejects_invalid_secret(): void
    {
        config(['services.sendcloud.webhook_secret' => 'sendcloud-secret']);

        $order = $this->makeOrder([
            'shipping_gateway' => 'sendcloud',
            'shipping_tracking_number' => null,
        ]);

        $response = $this->withHeaders([
            'X-Sendcloud-Webhook-Secret' => 'wrong-secret',
        ])->postJson('/api/webhooks/sendcloud', [
            'parcel' => [
                'order_number' => $order->order_number,
                'tracking_number' => 'TRACK-123',
            ],
        ]);

        $response->assertForbidden();
        $this->assertNull($order->fresh()->shipping_tracking_number);
    }

    private function makeOrder(array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'channel' => 'b2c',
            'ditta_cg18' => 1,
            'site_type' => 7,
            'order_number' => '1726000000000999',
            'status' => 'processing',
            'payment_status' => 'authorized',
            'payment_gateway' => 'stripe',
            'currency' => 'EUR',
            'grand_total' => 19.80,
            'subtotal' => 13.90,
            'shipping_total' => 5.90,
            'discount_total' => 0,
            'tax_total' => 0,
            'placed_at' => now(),
        ], $overrides));
    }

    private function paypalHeaders(): array
    {
        return [
            'PayPal-Auth-Algo' => 'SHA256withRSA',
            'PayPal-Cert-Url' => 'https://api-m.sandbox.paypal.com/certs/test',
            'PayPal-Transmission-Id' => 'transmission-id',
            'PayPal-Transmission-Sig' => 'transmission-signature',
            'PayPal-Transmission-Time' => now()->toIso8601String(),
        ];
    }
}
