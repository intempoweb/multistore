<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
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
            ->when($this->shouldRestrictToB2c(), function ($query) {
                $query
                    ->where('channel', 'b2c')
                    ->whereIn('store_id', $this->allowedStoreIds());
            })
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

    public function show(Request $request, Order $order): View|JsonResponse|RedirectResponse
    {
        if ($redirect = $this->redirectIfCannotAccessOrder($request, $order)) {
            return $redirect;
        }

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
        if ($redirect = $this->redirectIfCannotAccessOrder(request(), $order)) {
            return $redirect;
        }

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
        if ($redirect = $this->redirectIfCannotAccessOrder(request(), $order)) {
            return $redirect;
        }

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

    private function redirectIfCannotAccessOrder(Request $request, Order $order): JsonResponse|RedirectResponse|null
    {
        if ($this->canAccessOrder($order)) {
            return null;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Non hai i permessi per accedere a questo ordine.',
            ], 403);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('warning', 'Non hai i permessi per accedere a questo ordine.');
    }

    private function canAccessOrder(Order $order): bool
    {
        $user = request()->user();

        if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        if (!$this->shouldRestrictToB2c()) {
            return true;
        }

        return $order->isB2c()
            && in_array((int) $order->store_id, $this->allowedStoreIds(), true);
    }

    private function shouldRestrictToB2c(): bool
    {
        $user = request()->user();

        return $user
            && method_exists($user, 'isB2cManager')
            && $user->isB2cManager();
    }

    private function allowedStoreIds(): array
    {
        $user = request()->user();

        return Store::query()
            ->where('is_active', true)
            ->get(['id', 'is_b2b'])
            ->filter(fn (Store $store) => $user && method_exists($user, 'canAccessAdminStore') && $user->canAccessAdminStore($store))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
