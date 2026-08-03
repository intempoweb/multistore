<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $secret = trim((string) config('services.stripe.webhook_secret'));

        if ($secret === '') {
            Log::error('STRIPE WEBHOOK SECRET MISSING');

            return response()->json([
                'message' => 'Stripe webhook secret non configurato.',
            ], 500);
        }

        if (!$this->hasValidSignature($payload, (string) $request->header('Stripe-Signature'), $secret)) {
            Log::warning('STRIPE WEBHOOK INVALID SIGNATURE');

            return response()->json([
                'message' => 'Firma Stripe non valida.',
            ], 400);
        }

        $event = json_decode($payload, true);

        if (!is_array($event)) {
            return response()->json([
                'message' => 'Payload Stripe non valido.',
            ], 400);
        }

        $eventType = strtolower(trim((string) data_get($event, 'type')));
        $object = data_get($event, 'data.object', []);

        if (!is_array($object)) {
            return response()->json([
                'message' => 'Oggetto evento Stripe mancante.',
            ], 400);
        }

        $paymentIntentId = $this->extractPaymentIntentId($object);
        $order = $this->resolveOrder($paymentIntentId, $object);

        Log::info('STRIPE WEBHOOK RECEIVED', [
            'event_type' => $eventType,
            'order_found' => $order instanceof Order,
            'payment_intent_id' => $paymentIntentId,
        ]);

        if (!$order instanceof Order) {
            return response()->json([
                'message' => 'Ordine non trovato.',
                'event_type' => $eventType,
            ]);
        }

        $updates = $this->updatesForEvent($order, $eventType, $object, $paymentIntentId);

        if ($updates !== []) {
            $order->forceFill($updates)->save();
        }

        return response()->json([
            'message' => 'Webhook Stripe ricevuto.',
            'event_type' => $eventType,
            'order_number' => $order->order_number,
            'payment_status' => $order->fresh()?->payment_status,
        ]);
    }

    private function hasValidSignature(string $payload, string $signatureHeader, string $secret): bool
    {
        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, '');

                return [trim($key) => trim($value)];
            });

        $timestamp = (int) $parts->get('t');
        $signature = (string) $parts->get('v1');

        if ($timestamp <= 0 || $signature === '') {
            return false;
        }

        if (abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        return hash_equals($expected, $signature);
    }

    private function updatesForEvent(Order $order, string $eventType, array $object, ?string $paymentIntentId): array
    {
        $stripeStatus = strtolower(trim((string) data_get($object, 'status')));
        $meta = $this->orderMeta($order);

        $meta['stripe'] = array_merge($meta['stripe'] ?? [], [
            'last_webhook_event' => $eventType,
            'last_webhook_object' => $object,
            'last_webhook_received_at' => now()->toISOString(),
            'payment_intent_id' => $paymentIntentId ?: data_get($meta, 'stripe.payment_intent_id'),
            'gateway_status' => $stripeStatus ?: data_get($meta, 'stripe.gateway_status'),
        ]);

        $meta['payment'] = array_merge($meta['payment'] ?? [], [
            'gateway' => 'stripe',
            'transaction_id' => $paymentIntentId ?: data_get($meta, 'payment.transaction_id'),
            'gateway_status' => $stripeStatus ?: data_get($meta, 'payment.gateway_status'),
            'last_webhook_event' => $eventType,
            'last_webhook_received_at' => now()->toISOString(),
        ]);

        $updates = [
            'payment_gateway' => 'stripe',
            'meta' => $meta,
        ];

        if ($paymentIntentId) {
            $updates['payment_transaction_id'] = $paymentIntentId;
        }

        if ($eventType === 'payment_intent.amount_capturable_updated' || $stripeStatus === 'requires_capture') {
            if (!in_array((string) $order->payment_status, ['paid', 'refunded'], true)) {
                $updates['payment_status'] = 'authorized';
            }
        }

        if ($eventType === 'payment_intent.succeeded' || $stripeStatus === 'succeeded') {
            $updates['payment_status'] = 'paid';
            $updates['paid_at'] = $order->paid_at ?: now();
        }

        if (in_array($eventType, ['payment_intent.payment_failed', 'payment_intent.canceled'], true)) {
            if (!in_array((string) $order->payment_status, ['paid', 'refunded'], true)) {
                $updates['payment_status'] = $eventType === 'payment_intent.canceled' ? 'canceled' : 'failed';
            }
        }

        if ($eventType === 'charge.refunded' || $eventType === 'charge.refund.updated') {
            $updates['payment_status'] = 'refunded';
        }

        return $updates;
    }

    private function resolveOrder(?string $paymentIntentId, array $object): ?Order
    {
        $orderId = data_get($object, 'metadata.order_id');
        $orderNumber = data_get($object, 'metadata.order_number');

        if ($orderId) {
            $order = Order::query()->find((int) $orderId);

            if ($order instanceof Order) {
                return $order;
            }
        }

        if ($orderNumber) {
            $order = Order::query()->where('order_number', (string) $orderNumber)->first();

            if ($order instanceof Order) {
                return $order;
            }
        }

        if ($paymentIntentId) {
            return Order::query()
                ->where('payment_gateway', 'stripe')
                ->where('payment_transaction_id', $paymentIntentId)
                ->latest('id')
                ->first();
        }

        return null;
    }

    private function extractPaymentIntentId(array $object): ?string
    {
        $objectType = strtolower(trim((string) data_get($object, 'object')));

        $paymentIntentId = $objectType === 'payment_intent'
            ? data_get($object, 'id')
            : data_get($object, 'payment_intent');

        $paymentIntentId = trim((string) $paymentIntentId);

        return $paymentIntentId !== '' ? $paymentIntentId : null;
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
