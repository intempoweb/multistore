<?php

namespace Tests\Unit;

use App\Models\Order;
use PHPUnit\Framework\TestCase;

class OrderSendcloudTrackingTest extends TestCase
{
    public function test_it_reads_tracking_url_from_sendcloud_webhook_payload(): void
    {
        $order = new Order([
            'meta' => [
                'sendcloud' => [
                    'webhook_payload' => [
                        'parcel' => [
                            'tracking_url' => 'https://tracking.example.test/ABC123',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame(
            'https://tracking.example.test/ABC123',
            $order->sendcloudTrackingUrl()
        );
    }

    public function test_direct_tracking_url_has_priority_over_nested_payloads(): void
    {
        $order = new Order([
            'meta' => [
                'sendcloud' => [
                    'tracking_url' => 'https://tracking.example.test/direct',
                    'parcel_payload' => [
                        'parcel' => [
                            'tracking_url' => 'https://tracking.example.test/nested',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame(
            'https://tracking.example.test/direct',
            $order->sendcloudTrackingUrl()
        );
    }
}
