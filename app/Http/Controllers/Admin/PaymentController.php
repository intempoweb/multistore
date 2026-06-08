<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
    ) {
    }

    public function index(Request $request): View|JsonResponse
    {
        $orders = Order::query()
            ->with(['store', 'customer'])
            ->whereNotNull('payment_method_code')
            ->latest('placed_at')
            ->paginate(30);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $orders,
            ]);
        }

        return view('admin.payments.index', [
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, Order $order): View|JsonResponse
    {
        $order->load(['items', 'store', 'customer']);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $order,
            ]);
        }

        return view('admin.payments.show', [
            'order' => $order,
        ]);
    }

    public function capture(Order $order): RedirectResponse
    {
        if (!$order->canCapturePayment()) {
            return back()->with('error', 'Pagamento non acquisibile per questo ordine.');
        }

        if (blank($order->payment_gateway) || blank($order->payment_transaction_id)) {
            return back()->with('error', 'Gateway o ID transazione pagamento mancante.');
        }

        try {
            $payload = $this->paymentService->capturePayment(
                (string) $order->payment_gateway,
                (string) $order->payment_transaction_id
            );

            $meta = $this->orderMeta($order);
            $meta['payment'] = array_merge($meta['payment'] ?? [], [
                'capture_payload' => $payload,
                'captured_at' => now()->toISOString(),
                'capture_error' => null,
                'capture_failed_at' => null,
            ]);

            $order->forceFill([
                'payment_status' => 'paid',
                'paid_at' => $order->paid_at ?: now(),
                'meta' => $meta,
            ])->save();
        } catch (Throwable $exception) {
            report($exception);

            $meta = $this->orderMeta($order);
            $meta['payment'] = array_merge($meta['payment'] ?? [], [
                'capture_error' => $exception->getMessage(),
                'capture_failed_at' => now()->toISOString(),
            ]);

            $order->forceFill([
                'meta' => $meta,
            ])->save();

            return back()->with('error', 'Errore acquisizione pagamento: ' . $exception->getMessage());
        }

        return back()->with('success', 'Pagamento acquisito correttamente.');
    }

    public function refund(Order $order): RedirectResponse
    {
        if (!$order->canRefundPayment()) {
            return back()->with('error', 'Pagamento non rimborsabile per questo ordine.');
        }

        if (blank($order->payment_gateway) || blank($order->payment_transaction_id)) {
            return back()->with('error', 'Gateway o ID transazione pagamento mancante.');
        }

        try {
            $payload = $this->paymentService->refundPayment(
                (string) $order->payment_gateway,
                (string) $order->payment_transaction_id,
                (float) $order->grand_total
            );

            $meta = $this->orderMeta($order);
            $meta['payment'] = array_merge($meta['payment'] ?? [], [
                'refund_payload' => $payload,
                'refunded_at' => now()->toISOString(),
                'refund_error' => null,
                'refund_failed_at' => null,
            ]);

            $order->forceFill([
                'status' => 'canceled',
                'payment_status' => 'refunded',
                'fulfillment_status' => 'canceled',
                'meta' => $meta,
            ])->save();
        } catch (Throwable $exception) {
            report($exception);

            $meta = $this->orderMeta($order);
            $meta['payment'] = array_merge($meta['payment'] ?? [], [
                'refund_error' => $exception->getMessage(),
                'refund_failed_at' => now()->toISOString(),
            ]);

            $order->forceFill([
                'meta' => $meta,
            ])->save();

            return back()->with('error', 'Errore rimborso pagamento: ' . $exception->getMessage());
        }

        return back()->with('success', 'Pagamento rimborsato e ordine annullato correttamente.');
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