<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CreateSendcloudShipmentForOrder;
use App\Mail\Storefront\Orders\OrderInternalNotificationMail;
use App\Mail\Storefront\Orders\OrderStatusMail;
use App\Models\Order;
use App\Services\Erp\OrderExportService;
use App\Services\Payments\PaymentService;
use App\Services\Shipping\Sendcloud\SendcloudService;
use App\Services\Storefront\Mail\StorefrontMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class AdminOrderController extends Controller
{
    public function __construct(
        private SendcloudService $sendcloudService,
        private PaymentService $paymentService,
        private OrderExportService $orderExportService,
    ) {
    }

    public function index(Request $request): View
    {
        $orders = Order::query()
            ->with(['store', 'customer'])
            ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->input('channel')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim((string) $request->input('q'));

                $query->where(function ($subQuery) use ($q) {
                    $subQuery
                        ->where('order_number', 'like', "%{$q}%")
                        ->orWhere('customer_name', 'like', "%{$q}%")
                        ->orWhere('customer_email', 'like', "%{$q}%")
                        ->orWhere('shipping_contact_name', 'like', "%{$q}%")
                        ->orWhere('shipping_company', 'like', "%{$q}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('fulfillment_status'), fn ($query) => $query->where('fulfillment_status', $request->input('fulfillment_status')))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->input('payment_status')))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'filters' => [
                'q' => $request->input('q', ''),
                'store_id' => $request->input('store_id', ''),
                'channel' => $request->input('channel', ''),
                'status' => $request->input('status', ''),
                'fulfillment_status' => $request->input('fulfillment_status', ''),
                'payment_status' => $request->input('payment_status', ''),
            ],
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['store', 'customer', 'items']);

        return view('admin.orders.show', [
            'order' => $order,
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,processing,complete,closed,canceled'],
        ]);

        return match ($validated['status']) {
            'processing' => $this->confirmStock($order),
            'complete' => $this->markCompleted($order),
            'closed' => $this->close($order),
            'canceled' => $this->cancel($order),
            default => $this->markPending($order),
        };
    }

    public function updatePaymentStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'payment_status' => ['required', 'string', 'in:not_required,pending,authorized,paid,failed,refunded,canceled,cancelled'],
        ]);

        $order->forceFill([
            'payment_status' => $validated['payment_status'],
            'paid_at' => $validated['payment_status'] === 'paid'
                ? ($order->paid_at ?: now())
                : $order->paid_at,
        ])->save();

        $this->syncSendcloudStatus($order->fresh());

        if ($validated['payment_status'] === 'refunded') {
            $freshOrder = $order->fresh(['store', 'customer', 'items']);

            $this->sendCustomerStatusMailOnce($freshOrder, 'refunded');
        }

        return back()->with('success', 'Stato pagamento aggiornato.');
    }

    public function markPending(Order $order): RedirectResponse
    {
        $order->forceFill([
            'status' => 'pending',
            'fulfillment_status' => 'pending',
        ])->save();

        $this->syncSendcloudStatus($order->fresh(), 'pending');

        $freshOrder = $order->fresh(['store', 'customer', 'items']);

        $this->sendOrderStatusMail($freshOrder, 'pending');

        return back()->with('success', 'Ordine riportato in pending.');
    }

    public function confirmStock(Order $order): RedirectResponse
    {
        try {
            if ($order->canCapturePayment()) {
                $payload = $this->paymentService->capturePayment(
                    (string) $order->payment_gateway,
                    (string) $order->payment_transaction_id,
                    $order
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
            }

            $order->forceFill([
                'status' => 'processing',
                'fulfillment_status' => 'processing',
                'shipping_gateway' => $order->isB2c() ? 'sendcloud' : $order->shipping_gateway,
            ])->save();

            if ($order->requiresSendcloudShipment()) {
                CreateSendcloudShipmentForOrder::dispatchSync($order->id);
            }

            $freshOrder = $order->fresh(['store', 'customer', 'items']);

            $this->syncSendcloudStatus($freshOrder, 'processing');

            $freshOrder = $freshOrder->fresh(['store', 'customer', 'items']);

            if (!$freshOrder->isB2c()) {
                $this->sendOrderStatusMail($freshOrder, 'accepted');
                $this->sendOrderInternalNotificationMail($freshOrder, 'accepted');
            }

            return back()->with('success', 'Giacenza confermata: ordine in processing, pagamento incassato e Sendcloud sincronizzato.');
        } catch (Throwable $exception) {
            report($exception);

            $this->storePaymentError($order, 'confirm_stock', $exception->getMessage());

            return back()->with('error', 'Errore conferma ordine: ' . $exception->getMessage());
        }
    }

    public function markProcessing(Order $order): RedirectResponse
    {
        return $this->confirmStock($order);
    }

    public function markCompleted(Order $order): RedirectResponse
    {
        $order->forceFill([
            'status' => 'complete',
            'fulfillment_status' => 'complete',
        ])->save();

        $this->syncSendcloudStatus($order->fresh(), 'complete');

        $freshOrder = $order->fresh(['store', 'customer', 'items']);

        if (!$freshOrder->isB2c()) {
            $this->sendOrderStatusMail($freshOrder, 'completed');
            $this->sendOrderInternalNotificationMail($freshOrder, 'completed');
        }

        return back()->with('success', 'Ordine completato.');
    }

    public function capturePayment(Order $order): RedirectResponse
    {
        if (!$order->canCapturePayment()) {
            return back()->with('error', 'Pagamento non acquisibile per questo ordine.');
        }

        try {
            $payload = $this->paymentService->capturePayment(
                (string) $order->payment_gateway,
                (string) $order->payment_transaction_id,
                $order
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

            $this->syncSendcloudStatus($order->fresh());

            return back()->with('success', 'Pagamento acquisito correttamente.');
        } catch (Throwable $exception) {
            report($exception);

            $this->storePaymentError($order, 'capture', $exception->getMessage());

            return back()->with('error', 'Errore acquisizione pagamento: ' . $exception->getMessage());
        }
    }

    public function exportToErp(Order $order): RedirectResponse
    {
        if (!$order->canExportToErp()) {
            return back()->with('error', 'Ordine non esportabile verso ERP: già esportato, annullato/chiuso oppure non richiesto.');
        }

        try {
            $this->orderExportService->export($order);

            $order->refresh();

            return back()->with('success', 'Ordine esportato verso ERP. NUMREG WEB: ' . ($order->erp_web_numreg ?: '-'));
        } catch (Throwable $exception) {
            report($exception);

            $order->forceFill([
                'erp_export_status' => 'failed',
                'erp_export_error' => mb_substr($exception->getMessage(), 0, 65535),
            ])->save();

            return back()->with('error', 'Errore export ERP: ' . $exception->getMessage());
        }
    }

    public function refundPayment(Order $order): RedirectResponse
    {
        return $this->refundOrder(
            $order,
            'closed',
            'closed',
            'closed',
            'Pagamento rimborsato e ordine chiuso.'
        );
    }

    public function close(Order $order): RedirectResponse
    {
        if ($order->isPaid()) {
            return $this->refundPayment($order);
        }

        $order->forceFill([
            'status' => 'closed',
            'fulfillment_status' => 'closed',
        ])->save();

        $this->syncSendcloudStatus($order->fresh(), 'closed');

        $freshOrder = $order->fresh(['store', 'customer', 'items']);

        $this->sendCustomerStatusMailOnce($freshOrder, 'closed');

        if (!$freshOrder->isB2c()) {
            $this->sendOrderInternalNotificationMail($freshOrder, 'closed');
        }

        return back()->with('success', 'Ordine chiuso.');
    }

    public function cancel(Order $order): RedirectResponse
    {
        if ($order->isPaid()) {
            return $this->refundOrder(
                $order,
                'canceled',
                'canceled',
                'canceled',
                'Pagamento rimborsato e ordine annullato.'
            );
        }

        try {
            if ($order->canCapturePayment()) {
                $payload = $this->paymentService->cancelPayment(
                    (string) $order->payment_gateway,
                    (string) $order->payment_transaction_id,
                    'requested_by_customer'
                );

                $meta = $this->orderMeta($order);

                $meta['payment'] = array_merge($meta['payment'] ?? [], [
                    'cancel_payload' => $payload,
                    'canceled_at' => now()->toISOString(),
                    'cancel_error' => null,
                    'cancel_failed_at' => null,
                ]);

                $order->forceFill(['meta' => $meta])->save();
            }
        } catch (Throwable $exception) {
            report($exception);

            $this->storePaymentError($order, 'cancel_payment', $exception->getMessage());

            return back()->with('error', 'Errore annullamento autorizzazione pagamento: ' . $exception->getMessage());
        }

        $order->forceFill([
            'status' => 'canceled',
            'payment_status' => in_array((string) $order->payment_status, ['not_required'], true)
                ? $order->payment_status
                : 'canceled',
            'fulfillment_status' => 'canceled',
        ])->save();

        $this->syncSendcloudStatus($order->fresh(), 'canceled');

        $freshOrder = $order->fresh(['store', 'customer', 'items']);

        $this->sendCustomerStatusMailOnce($freshOrder, 'canceled');

        if (!$freshOrder->isB2c()) {
            $this->sendOrderInternalNotificationMail($freshOrder, 'canceled');
        }

        return back()->with('success', 'Ordine annullato.');
    }

    private function refundOrder(
        Order $order,
        string $finalStatus,
        string $finalFulfillmentStatus,
        string $sendcloudStatus,
        string $successMessage
    ): RedirectResponse {
        if (!$order->canRefundPayment()) {
            return back()->with('error', 'Pagamento non rimborsabile per questo ordine.');
        }

        try {
            $sendcloudPayload = null;

            if ($this->shouldSyncSendcloud($order)) {
                $sendcloudPayload = $this->cancelSendcloudSafely($order);
            }

            $payload = $this->paymentService->refundPayment(
                (string) $order->payment_gateway,
                (string) $order->payment_transaction_id,
                (float) $order->grand_total,
                'sendcloud_label_cancelled'
            );

            $meta = $this->orderMeta($order);

            $meta['payment'] = array_merge($meta['payment'] ?? [], [
                'refund_payload' => $payload,
                'refunded_at' => now()->toISOString(),
                'refund_error' => null,
                'refund_failed_at' => null,
            ]);

            $meta['sendcloud'] = array_merge($meta['sendcloud'] ?? [], [
                'cancel_payload' => $sendcloudPayload,
                'cancelled_at' => now()->toISOString(),
                'cancel_status' => 'cancelled',
                'status_sync_error' => null,
                'status_sync_failed_at' => null,
            ]);

            $order->forceFill([
                'status' => $finalStatus,
                'payment_status' => 'refunded',
                'fulfillment_status' => $finalFulfillmentStatus,
                'meta' => $meta,
            ])->save();

            $freshOrder = $order->fresh(['store', 'customer', 'items']);

            $this->sendCustomerStatusMailOnce($freshOrder, 'refunded');

            if (!$freshOrder->isB2c()) {
                $this->sendOrderInternalNotificationMail($freshOrder, 'refunded');
            }

            return back()->with('success', $successMessage);
        } catch (Throwable $exception) {
            report($exception);

            $this->storePaymentError($order, 'refund', $exception->getMessage());

            return back()->with('error', 'Errore rimborso pagamento: ' . $exception->getMessage());
        }
    }

    private function syncSendcloudStatus(Order $order, ?string $status = null): void
    {
        if (!$this->shouldSyncSendcloud($order)) {
            return;
        }

        try {
            $payload = in_array($status, ['canceled', 'cancelled', 'closed'], true)
                ? $this->cancelSendcloudSafely($order)
                : $this->sendcloudService->updateIncomingOrderStatus($order, $status);

            $meta = $this->orderMeta($order);

            $meta['sendcloud'] = array_merge($meta['sendcloud'] ?? [], [
                'status_sync_payload' => $payload,
                'status_synced_at' => now()->toISOString(),
                'status_sync_error' => null,
                'status_sync_failed_at' => null,
            ]);

            $order->forceFill(['meta' => $meta])->save();
        } catch (Throwable $exception) {
            report($exception);

            if ($this->isSendcloudAlreadyCancellingMessage($exception->getMessage())) {
                $meta = $this->orderMeta($order);

                $meta['sendcloud'] = array_merge($meta['sendcloud'] ?? [], [
                    'status_sync_payload' => [
                        'status' => 'accepted',
                        'message' => $exception->getMessage(),
                    ],
                    'status_synced_at' => now()->toISOString(),
                    'status_sync_error' => null,
                    'status_sync_failed_at' => null,
                    'cancel_status' => 'already_cancelling',
                ]);

                $order->forceFill(['meta' => $meta])->save();

                return;
            }

            $meta = $this->orderMeta($order);

            $meta['sendcloud'] = array_merge($meta['sendcloud'] ?? [], [
                'status_sync_error' => $exception->getMessage(),
                'status_sync_failed_at' => now()->toISOString(),
            ]);

            $order->forceFill(['meta' => $meta])->save();
        }
    }

    private function cancelSendcloudSafely(Order $order): array
    {
        try {
            $payload = $this->sendcloudService->cancelIncomingOrder($order);

            if ($this->sendcloudPayloadIsAlreadyCancelling($payload)) {
                return [
                    'status' => 'accepted',
                    'message' => data_get($payload, 'message', 'This shipment is already being cancelled.'),
                    'original_payload' => $payload,
                ];
            }

            return is_array($payload) ? $payload : [];
        } catch (Throwable $exception) {
            if ($this->isSendcloudAlreadyCancellingMessage($exception->getMessage())) {
                return [
                    'status' => 'accepted',
                    'message' => $exception->getMessage(),
                    'already_cancelling' => true,
                ];
            }

            throw $exception;
        }
    }

    private function sendcloudPayloadIsAlreadyCancelling(array $payload): bool
    {
        $status = strtolower(trim((string) data_get($payload, 'status')));
        $message = strtolower(trim((string) data_get($payload, 'message')));

        return $status === 'failed' && $this->isSendcloudAlreadyCancellingMessage($message);
    }

    private function isSendcloudAlreadyCancellingMessage(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'already being cancelled')
            || str_contains($message, 'already being canceled')
            || str_contains($message, 'already cancelled')
            || str_contains($message, 'already canceled')
            || str_contains($message, 'being cancelled')
            || str_contains($message, 'being canceled');
    }

    private function shouldSyncSendcloud(Order $order): bool
    {
        return $order->isB2c()
            && (string) $order->shipping_gateway === 'sendcloud'
            && filled(data_get($this->orderMeta($order), 'sendcloud.incoming_order_id'));
    }


    private function storePaymentError(Order $order, string $operation, string $message): void
    {
        $meta = $this->orderMeta($order);

        $meta['payment'] = array_merge($meta['payment'] ?? [], [
            "{$operation}_error" => $message,
            "{$operation}_failed_at" => now()->toISOString(),
        ]);

        $order->forceFill(['meta' => $meta])->save();
    }

    private function sendCustomerStatusMailOnce(?Order $order, string $event): void
    {
        if (!$order instanceof Order) {
            return;
        }

        $order->loadMissing(['store', 'customer', 'items']);

        $meta = $this->orderMeta($order);
        $mailKey = $event . '_customer';

        if (
            filled(data_get($meta, 'mail.' . $mailKey . '_sent_at'))
            || filled(data_get($meta, 'mail.' . $mailKey . '_sending_at'))
        ) {
            return;
        }

        $to = trim((string) ($order->customer_email ?: $order->shipping_email ?: $order->billing_email));

        if ($to === '') {
            return;
        }

        $meta['mail'] = array_merge($meta['mail'] ?? [], [
            $mailKey . '_sending_at' => now()->toISOString(),
            $mailKey . '_error' => null,
            $mailKey . '_failed_at' => null,
        ]);

        $order->forceFill(['meta' => $meta])->save();

        try {
            $order = $order->fresh(['store', 'customer', 'items']) ?: $order;

            Mail::to($to)->send(new OrderStatusMail($order, $event));

            $meta = $this->orderMeta($order);
            $meta['mail'] = array_merge($meta['mail'] ?? [], [
                $mailKey . '_sent_at' => now()->toISOString(),
                $mailKey . '_sending_at' => null,
                $mailKey . '_error' => null,
                $mailKey . '_failed_at' => null,
            ]);

            $order->forceFill(['meta' => $meta])->save();
        } catch (Throwable $exception) {
            report($exception);

            $meta = $this->orderMeta($order);
            $meta['mail'] = array_merge($meta['mail'] ?? [], [
                $mailKey . '_sending_at' => null,
                $mailKey . '_error' => $exception->getMessage(),
                $mailKey . '_failed_at' => now()->toISOString(),
            ]);

            $order->forceFill(['meta' => $meta])->save();
        }
    }

    private function sendOrderStatusMail(?Order $order, string $event): void
    {
        $this->sendCustomerStatusMailOnce($order, $event);
    }

    private function sendOrderInternalNotificationMail(?Order $order, string $event): void
    {
        if (!$order instanceof Order) {
            return;
        }

        $order->loadMissing(['store', 'customer', 'items']);

        $store = $order->store;

        if (!$store) {
            return;
        }

        $to = app(StorefrontMailService::class)->internalRecipientForStore($store);

        if ($to === null) {
            return;
        }

        try {
            Mail::to($to)->send(new OrderInternalNotificationMail($order, $event));
        } catch (Throwable $exception) {
            report($exception);

            $this->storeMailError($order, $event, 'internal', $exception->getMessage());
        }
    }

    private function storeMailError(Order $order, string $event, string $recipientType, string $message): void
    {
        $meta = $this->orderMeta($order);

        $meta['mail'] = array_merge($meta['mail'] ?? [], [
            $event . '_' . $recipientType . '_error' => $message,
            $event . '_' . $recipientType . '_failed_at' => now()->toISOString(),
        ]);

        $order->forceFill(['meta' => $meta])->save();
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