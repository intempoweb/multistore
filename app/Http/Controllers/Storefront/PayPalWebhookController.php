<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $eventType = strtoupper((string) data_get($payload, 'event_type'));

        $paypalOrderId = $this->extractPayPalOrderId($payload);
        $captureId = $this->extractCaptureId($payload);
        $authorizationId = $this->extractAuthorizationId($payload);

        $order = $this->resolveOrder($paypalOrderId, $captureId, $authorizationId, $payload);

        Log::info('PAYPAL WEBHOOK RECEIVED', [
            'event_type' => $eventType,
            'order_found' => $order instanceof Order,
            'paypal_order_id' => $paypalOrderId,
            'capture_id' => $captureId,
            'authorization_id' => $authorizationId,
            'payload' => $payload,
        ]);

        if (!$order instanceof Order) {
            return response()->json([
                'message' => 'Ordine non trovato.',
                'event_type' => $eventType,
            ], 200);
        }

        $meta = $this->orderMeta($order);
        $meta['paypal'] = array_merge($meta['paypal'] ?? [], [
            'last_webhook_event' => $eventType,
            'last_webhook_payload' => $payload,
            'last_webhook_received_at' => now()->toISOString(),
        ]);

        $updates = [
            'payment_gateway' => 'paypal',
            'meta' => $meta,
        ];

        if ($paypalOrderId) {
            $updates['payment_transaction_id'] = $paypalOrderId;
            $meta['paypal']['order_id'] = $paypalOrderId;
        }

        if ($authorizationId) {
            $meta['paypal']['authorization_id'] = $authorizationId;
        }

        if ($captureId) {
            $meta['paypal']['capture_id'] = $captureId;
        }

        if (in_array($eventType, [
            'CHECKOUT.ORDER.APPROVED',
            'PAYMENT.AUTHORIZATION.CREATED',
        ], true)) {
            $updates['payment_status'] = 'authorized';
        }

        if (in_array($eventType, [
            'PAYMENT.CAPTURE.COMPLETED',
            'CHECKOUT.ORDER.COMPLETED',
        ], true)) {
            $updates['payment_status'] = 'paid';
            $updates['paid_at'] = $order->paid_at ?: now();
        }

        if (in_array($eventType, [
            'PAYMENT.CAPTURE.REFUNDED',
        ], true)) {
            $updates['payment_status'] = 'refunded';
        }

        if (in_array($eventType, [
            'PAYMENT.CAPTURE.DENIED',
            'PAYMENT.CAPTURE.DECLINED',
            'CHECKOUT.ORDER.VOIDED',
        ], true)) {
            $updates['payment_status'] = 'failed';
        }

        $updates['meta'] = $meta;

        $order->forceFill($updates)->save();

        return response()->json([
            'message' => 'Webhook PayPal ricevuto.',
            'event_type' => $eventType,
            'order_number' => $order->order_number,
            'payment_status' => $order->fresh()?->payment_status,
        ]);
    }

    private function resolveOrder(?string $paypalOrderId, ?string $captureId, ?string $authorizationId, array $payload): ?Order
    {
        $customId = data_get($payload, 'resource.custom_id')
            ?? data_get($payload, 'resource.purchase_units.0.custom_id');

        if ($customId) {
            $order = Order::query()->find((int) $customId);

            if ($order instanceof Order) {
                return $order;
            }
        }

        if ($paypalOrderId) {
            $order = Order::query()
                ->where('payment_transaction_id', $paypalOrderId)
                ->orWhere('meta->paypal->order_id', $paypalOrderId)
                ->first();

            if ($order instanceof Order) {
                return $order;
            }
        }

        if ($authorizationId) {
            $order = Order::query()
                ->where('meta->paypal->authorization_id', $authorizationId)
                ->first();

            if ($order instanceof Order) {
                return $order;
            }
        }

        if ($captureId) {
            $order = Order::query()
                ->where('meta->paypal->capture_id', $captureId)
                ->first();

            if ($order instanceof Order) {
                return $order;
            }
        }

        return null;
    }

    private function extractPayPalOrderId(array $payload): ?string
    {
        $id = data_get($payload, 'resource.supplementary_data.related_ids.order_id')
            ?? data_get($payload, 'resource.purchase_units.0.payments.captures.0.supplementary_data.related_ids.order_id')
            ?? data_get($payload, 'resource.id');

        return $this->clean($id);
    }

    private function extractCaptureId(array $payload): ?string
    {
        $id = data_get($payload, 'resource.id')
            ?? data_get($payload, 'resource.purchase_units.0.payments.captures.0.id');

        return $this->clean($id);
    }

    private function extractAuthorizationId(array $payload): ?string
    {
        $id = data_get($payload, 'resource.supplementary_data.related_ids.authorization_id')
            ?? data_get($payload, 'resource.purchase_units.0.payments.authorizations.0.id')
            ?? data_get($payload, 'resource.id');

        return $this->clean($id);
    }

    private function clean(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

    private function orderMeta(Order $order): array
    {
        $meta = $order->meta ?? [];

        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        return is_array($meta) ? $meta : [];
    }
}