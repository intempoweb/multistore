<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
    ) {
    }

    public function stripeSuccess(Request $request): RedirectResponse
    {
        $orderNumber = trim((string) $request->query('order', ''));
        $paymentIntentId = trim((string) $request->query('payment_intent', ''));

        if ($paymentIntentId === '') {
            return redirect()
                ->route('storefront.checkout.show')
                ->with('error', 'PaymentIntent Stripe mancante.');
        }

        $order = $this->findOrder($orderNumber);

        if (!$order) {
            return redirect()
                ->route('storefront.checkout.show')
                ->with('error', 'Ordine non trovato.');
        }

        try {
            $payment = $this->paymentService->retrievePayment('stripe', $paymentIntentId);

            $status = strtolower((string) ($payment['status'] ?? ''));
            $isPaid = $status === 'succeeded';

            $meta = $this->orderMeta($order);
            $meta['stripe'] = array_merge($meta['stripe'] ?? [], [
                'payment_intent_id' => $paymentIntentId,
                'success_payload' => $payment,
                'success_at' => now()->toISOString(),
            ]);

            $order->forceFill([
                'payment_gateway' => 'stripe',
                'payment_status' => $isPaid ? 'paid' : 'pending',
                'payment_transaction_id' => $paymentIntentId,
                'paid_at' => $isPaid ? ($order->paid_at ?: now()) : null,
                'meta' => $meta,
            ])->save();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('storefront.checkout.show')
                ->with('error', 'Pagamento Stripe non verificato.');
        }

        return redirect()
            ->route('storefront.cart.index')
            ->with('success', 'Pagamento completato. Ordine: ' . $order->order_number);
    }

    public function paypalCapture(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paypal_order_id' => ['required', 'string', 'max:255'],
        ]);

        try {
            $payment = $this->paymentService->capturePayPal($validated['paypal_order_id']);

            $status = strtoupper((string) ($payment['status'] ?? ''));

            if ($status !== 'COMPLETED') {
                return response()->json([
                    'message' => 'Pagamento PayPal non completato.',
                    'data' => [
                        'paypal_order_id' => $validated['paypal_order_id'],
                        'payment' => $payment,
                    ],
                ], 422);
            }
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Pagamento PayPal non verificato.',
            ], 422);
        }

        return response()->json([
            'message' => 'Pagamento PayPal catturato.',
            'data' => [
                'paypal_order_id' => $validated['paypal_order_id'],
                'payment' => $payment,
            ],
        ]);
    }

    public function paypalSuccess(Request $request): RedirectResponse
    {
        $orderNumber = trim((string) $request->query('order', ''));
        $paypalOrderId = trim((string) $request->query('token', ''));

        if ($paypalOrderId === '') {
            return redirect()
                ->route('storefront.checkout.show')
                ->with('error', 'Token PayPal mancante.');
        }

        $order = $this->findOrder($orderNumber);

        if (!$order) {
            return redirect()
                ->route('storefront.checkout.show')
                ->with('error', 'Ordine non trovato.');
        }

        try {
            $payment = $this->paymentService->authorizePayPal($paypalOrderId);

            $authorizationId = $this->extractAuthorizationId($payment);
            $captureId = $this->extractCaptureId($payment);
            $status = strtoupper((string) ($payment['status'] ?? ''));

            $meta = $this->orderMeta($order);
            $meta['paypal'] = array_merge($meta['paypal'] ?? [], [
                'order_id' => $paypalOrderId,
                'authorization_id' => $authorizationId ?: data_get($meta, 'paypal.authorization_id'),
                'capture_id' => $captureId ?: data_get($meta, 'paypal.capture_id'),
                'success_payload' => $payment,
                'success_status' => $status,
                'success_at' => now()->toISOString(),
            ]);

            $isPaid = $status === 'COMPLETED' || $captureId !== null;
            $isAuthorized = $authorizationId !== null;

            $order->forceFill([
                'payment_gateway' => 'paypal',
                'payment_status' => $isPaid ? 'paid' : ($isAuthorized ? 'authorized' : 'pending'),
                'payment_transaction_id' => $paypalOrderId,
                'paid_at' => $isPaid ? ($order->paid_at ?: now()) : null,
                'meta' => $meta,
            ])->save();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('storefront.checkout.show')
                ->with('error', 'Pagamento PayPal non verificato.');
        }

        return redirect()
            ->route('storefront.cart.index')
            ->with('success', 'Pagamento PayPal autorizzato. Ordine: ' . $order->order_number);
    }

    public function cancel(Request $request): RedirectResponse
    {
        return redirect()
            ->route('storefront.checkout.show')
            ->with('error', 'Pagamento annullato.');
    }

    private function findOrder(string $orderNumber): ?Order
    {
        $orderNumber = trim($orderNumber);

        if ($orderNumber === '') {
            return null;
        }

        return Order::query()
            ->where('order_number', $orderNumber)
            ->first();
    }

    private function extractAuthorizationId(array $payment): ?string
    {
        $authorizationId = data_get($payment, 'purchase_units.0.payments.authorizations.0.id')
            ?? data_get($payment, 'payments.authorizations.0.id');

        $authorizationId = trim((string) $authorizationId);

        return $authorizationId !== '' ? $authorizationId : null;
    }

    private function extractCaptureId(array $payment): ?string
    {
        $captureId = data_get($payment, 'purchase_units.0.payments.captures.0.id')
            ?? data_get($payment, 'payments.captures.0.id');

        $captureId = trim((string) $captureId);

        return $captureId !== '' ? $captureId : null;
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