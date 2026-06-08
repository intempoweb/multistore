<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\Storefront\Orders\OrderStatusMail;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use App\Services\Shipping\Sendcloud\SendcloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendcloudShipmentController extends Controller
{
    public function __construct(
        private SendcloudService $sendcloudService,
        private PaymentService $paymentService,
    ) {
    }

    public function create(Order $order): RedirectResponse
    {
        return back()->with('error', 'L’etichetta va creata da Sendcloud, non dal BO.');
    }

    public function webhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();

            $parcel = $this->extractParcelPayload($payload);
            $parcelId = $this->extractParcelId($parcel) ?: $this->extractParcelId($payload);
            $parcelPayload = null;

            if ($parcelId !== null) {
                try {
                    $parcelPayload = $this->sendcloudService->getParcel($parcelId);
                    $parcel = array_replace_recursive($parcel, $this->extractParcelPayload($parcelPayload));
                } catch (Throwable $exception) {
                    Log::warning('SENDCLOUD GET PARCEL FAILED', [
                        'parcel_id' => $parcelId,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            $parcelPayloadParcel = $parcelPayload ? $this->extractParcelPayload($parcelPayload) : [];

            $orderNumber = $this->extractOrderNumber($parcel, $payload)
                ?: ($parcelPayload ? $this->extractOrderNumber($parcelPayloadParcel, $parcelPayload) : null);

            $incomingOrderId = $this->extractIncomingOrderId($payload)
                ?: ($parcelPayload ? $this->extractIncomingOrderId($parcelPayload) : null);

            $externalOrderId = $this->extractExternalOrderId($parcel, $payload)
                ?: ($parcelPayload ? $this->extractExternalOrderId($parcelPayloadParcel, $parcelPayload) : null);

            $trackingNumber = $this->extractTrackingNumber($parcel)
                ?: $this->extractTrackingNumber($payload)
                ?: ($parcelPayload ? $this->extractTrackingNumber($parcelPayload) : null);

            $labelUrl = $this->extractLabelUrl($parcel)
                ?: $this->extractLabelUrl($payload)
                ?: ($parcelPayload ? $this->extractLabelUrl($parcelPayload) : null);

            $sendcloudStatus = $this->extractSendcloudStatus($parcel, $payload)
                ?: ($parcelPayload ? $this->extractSendcloudStatus($parcelPayloadParcel, $parcelPayload) : null);

            $sendcloudPaymentStatus = $this->sendcloudService->extractSendcloudPaymentStatus($payload)
                ?: ($parcelPayload ? $this->sendcloudService->extractSendcloudPaymentStatus($parcelPayload) : null);

            $statusMessage = $this->extractStatusMessage($parcel, $payload)
                ?: ($parcelPayload ? $this->extractStatusMessage($parcelPayloadParcel, $parcelPayload) : null);

            $order = $this->resolveOrderFromWebhook(
                $orderNumber,
                $incomingOrderId,
                $externalOrderId,
                $parcelId,
                $trackingNumber
            );

            Log::info('SENDCLOUD WEBHOOK RECEIVED', [
                'order_found' => $order instanceof Order,
                'order_number' => $orderNumber,
                'incoming_order_id' => $incomingOrderId,
                'external_order_id' => $externalOrderId,
                'parcel_id' => $parcelId,
                'tracking_number' => $trackingNumber,
                'label_url' => $labelUrl,
                'sendcloud_status' => $sendcloudStatus,
                'sendcloud_payment_status' => $sendcloudPaymentStatus,
            ]);

            if (!$order instanceof Order) {
                return response()->json([
                    'message' => 'Ordine non trovato.',
                    'order_number' => $orderNumber,
                    'incoming_order_id' => $incomingOrderId,
                    'external_order_id' => $externalOrderId,
                    'parcel_id' => $parcelId,
                    'tracking_number' => $trackingNumber,
                ], 200);
            }

            $meta = $this->orderMeta($order);
            $meta['sendcloud'] = array_merge($meta['sendcloud'] ?? [], [
                'incoming_order_id' => $incomingOrderId ?: data_get($meta, 'sendcloud.incoming_order_id'),
                'parcel_id' => $parcelId ?: data_get($meta, 'sendcloud.parcel_id'),
                'tracking_number' => $trackingNumber ?: data_get($meta, 'sendcloud.tracking_number'),
                'label_url' => $labelUrl ?: data_get($meta, 'sendcloud.label_url'),
                'status' => $sendcloudStatus ?: data_get($meta, 'sendcloud.status'),
                'payment_status' => $sendcloudPaymentStatus ?: data_get($meta, 'sendcloud.payment_status'),
                'status_message' => $statusMessage ?: data_get($meta, 'sendcloud.status_message'),
                'webhook_payload' => $payload,
                'parcel_payload' => $parcelPayload ?: data_get($meta, 'sendcloud.parcel_payload'),
                'webhook_received_at' => now()->toISOString(),
                'pending_webhook' => false,
                'error' => null,
                'failed_at' => null,
            ]);

            $updates = [
                'shipping_gateway' => 'sendcloud',
                'meta' => $meta,
            ];

            if ($this->isCanceledStatus($sendcloudStatus)) {
                $refunded = $this->refundPaymentAfterSendcloudCancellation(
                    order: $order,
                    meta: $meta,
                    payload: $payload,
                    sendcloudStatus: $sendcloudStatus,
                    source: 'sendcloud_webhook'
                );

                $updates['status'] = $refunded ? 'closed' : 'canceled';
                $updates['fulfillment_status'] = 'canceled';
                $updates['payment_status'] = $refunded ? 'refunded' : (string) $order->payment_status;
                $updates['shipping_tracking_number'] = null;
                $updates['shipping_label_url'] = null;
                $updates['shipping_label_created_at'] = null;
                $updates['meta'] = $meta;
            } else {
                if ($trackingNumber !== null) {
                    $updates['shipping_tracking_number'] = $trackingNumber;
                }

                if ($labelUrl !== null) {
                    $updates['shipping_label_url'] = $labelUrl;
                    $updates['shipping_label_created_at'] = $order->shipping_label_created_at ?: now();
                }

                if ($this->hasSendcloudLabelData($parcelId, $trackingNumber, $labelUrl, $sendcloudStatus)) {
                    $updates['status'] = 'complete';
                    $updates['fulfillment_status'] = 'complete';

                    $meta['sendcloud']['status'] = $sendcloudStatus ?: 'label.created';
                    $meta['sendcloud']['status_message'] = $statusMessage ?: 'Etichetta creata su Sendcloud';
                    $meta['sendcloud']['pending_webhook'] = false;
                    $updates['meta'] = $meta;
                }
            }

            if ($this->isRefundedPaymentStatus($sendcloudPaymentStatus)) {
                $updates['payment_status'] = 'refunded';
                $updates['status'] = 'closed';
            } elseif ($this->isCanceledPaymentStatus($sendcloudPaymentStatus) && ($updates['payment_status'] ?? null) !== 'refunded') {
                $updates['payment_status'] = (string) $order->payment_status === 'paid'
                    ? 'paid'
                    : $this->canceledPaymentStatusForGateway($order);
            } elseif ($this->isPaidPaymentStatus($sendcloudPaymentStatus) && (string) $order->payment_status !== 'paid') {
                $updates['payment_status'] = 'paid';
                $updates['paid_at'] = $order->paid_at ?: now();
            }

            $order->forceFill($updates)->save();
            $freshOrder = $order->fresh(['store', 'customer', 'items']);

            if ($freshOrder instanceof Order) {
                if ((string) $freshOrder->payment_status === 'refunded') {
                    $this->sendCustomerStatusMailOnce($freshOrder, 'refunded');
                } elseif (in_array((string) $freshOrder->status, ['canceled', 'closed'], true) && (string) $freshOrder->fulfillment_status === 'canceled') {
                    $this->sendCustomerStatusMailOnce($freshOrder, 'canceled');
                } elseif ($this->shouldSendAcceptedCustomerMail($freshOrder)) {
                    $this->sendCustomerStatusMailOnce($freshOrder, 'accepted');
                }

                $freshOrder = $freshOrder->fresh(['store', 'customer', 'items']);
            }

            return response()->json([
                'message' => 'Webhook Sendcloud ricevuto.',
                'order_number' => $freshOrder?->order_number,
                'status' => $freshOrder?->status,
                'fulfillment_status' => $freshOrder?->fulfillment_status,
                'payment_status' => $freshOrder?->payment_status,
                'shipping_tracking_number' => $freshOrder?->shipping_tracking_number,
                'shipping_label_url' => $freshOrder?->shipping_label_url,
                'parcel_id' => data_get($freshOrder?->meta, 'sendcloud.parcel_id'),
                'tracking_number' => $trackingNumber,
                'label_url' => $labelUrl,
                'sendcloud_status' => $sendcloudStatus,
                'sendcloud_payment_status' => $sendcloudPaymentStatus,
                'accepted_customer_sent_at' => data_get($this->orderMeta($freshOrder), 'mail.accepted_customer_sent_at'),
                'accepted_customer_error' => data_get($this->orderMeta($freshOrder), 'mail.accepted_customer_error'),
                'canceled_customer_sent_at' => data_get($this->orderMeta($freshOrder), 'mail.canceled_customer_sent_at'),
                'canceled_customer_error' => data_get($this->orderMeta($freshOrder), 'mail.canceled_customer_error'),
                'refunded_customer_sent_at' => data_get($this->orderMeta($freshOrder), 'mail.refunded_customer_sent_at'),
                'refunded_customer_error' => data_get($this->orderMeta($freshOrder), 'mail.refunded_customer_error'),
            ]);
        } catch (Throwable $exception) {
            Log::error('SENDCLOUD WEBHOOK FAILED', [
                'error' => $exception->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Webhook Sendcloud ricevuto ma non processato.',
                'error' => $exception->getMessage(),
            ], 200);
        }
    }

    public function cancel(Order $order): RedirectResponse
    {
        if ((string) $order->shipping_gateway !== 'sendcloud') {
            return back()->with('error', 'Nessuna spedizione/ordine Sendcloud da annullare.');
        }

        $parcelId = $this->resolveParcelId($order);
        $incomingOrderId = $this->resolveIncomingOrderId($order);

        if ($parcelId === null && $incomingOrderId === null) {
            return back()->with('error', 'ID Sendcloud mancante: impossibile annullare su Sendcloud.');
        }

        $meta = $this->orderMeta($order);
        $cancelPayload = null;

        try {
            try {
                $cancelPayload = $parcelId !== null
                    ? $this->sendcloudService->cancelParcel($parcelId)
                    : $this->sendcloudService->cancelIncomingOrder($order);
            } catch (Throwable $exception) {
                if (!$this->isSendcloudAlreadyCancellingError($exception->getMessage())) {
                    throw $exception;
                }

                $cancelPayload = [
                    'status' => 'accepted',
                    'already_cancelling' => true,
                    'message' => $exception->getMessage(),
                ];
            }

            $refunded = $this->refundPaymentAfterSendcloudCancellation(
                order: $order,
                meta: $meta,
                payload: ['cancel_payload' => $cancelPayload],
                sendcloudStatus: 'canceled',
                source: 'admin_sendcloud_cancel'
            );

            $meta['sendcloud'] = array_merge($meta['sendcloud'] ?? [], [
                'parcel_id' => $parcelId ?: data_get($meta, 'sendcloud.parcel_id'),
                'incoming_order_id' => $incomingOrderId ?: data_get($meta, 'sendcloud.incoming_order_id'),
                'cancel_payload' => $cancelPayload,
                'cancelled_at' => now()->toISOString(),
                'cancel_status' => data_get($cancelPayload, 'already_cancelling') ? 'already_cancelling' : 'cancelled',
                'status' => 'canceled',
                'status_message' => data_get($cancelPayload, 'already_cancelling')
                    ? 'Cancellation already requested on Sendcloud'
                    : 'Canceled from BO',
                'pending_webhook' => false,
                'error' => null,
                'failed_at' => null,
                'cancel_error' => null,
                'cancel_failed_at' => null,
                'status_sync_error' => null,
                'status_sync_failed_at' => null,
            ]);

            $order->forceFill([
                'status' => $refunded ? 'closed' : 'canceled',
                'fulfillment_status' => 'canceled',
                'payment_status' => $refunded ? 'refunded' : (string) $order->payment_status,
                'shipping_tracking_number' => null,
                'shipping_label_url' => null,
                'shipping_label_created_at' => null,
                'meta' => $meta,
            ])->save();

            $freshOrder = $order->fresh(['store', 'customer', 'items']);

            if ($freshOrder instanceof Order) {
                $this->sendCustomerStatusMailOnce($freshOrder, $refunded ? 'refunded' : 'canceled');
            }

            return back()->with(
                'success',
                $refunded
                    ? 'Spedizione Sendcloud annullata, ordine chiuso e pagamento rimborsato.'
                    : 'Spedizione Sendcloud annullata e ordine annullato. Rimborso non eseguito perché il pagamento non risultava rimborsabile.'
            );
        } catch (Throwable $exception) {
            report($exception);

            $meta['sendcloud'] = array_merge($meta['sendcloud'] ?? [], [
                'cancel_error' => $exception->getMessage(),
                'cancel_failed_at' => now()->toISOString(),
            ]);

            $order->forceFill([
                'meta' => $meta,
            ])->save();

            return back()->with('error', 'Errore annullamento Sendcloud: ' . $exception->getMessage());
        }
    }

    public function label(Order $order): RedirectResponse
    {
        if (empty($order->shipping_label_url)) {
            return back()->with('error', 'Etichetta Sendcloud non disponibile.');
        }

        return redirect()->away($order->shipping_label_url);
    }

    private function refundPaymentAfterSendcloudCancellation(
        Order $order,
        array &$meta,
        array $payload,
        ?string $sendcloudStatus,
        string $source
    ): bool {
        if (!$order->isB2c()) {
            return false;
        }

        if (!in_array((string) $order->payment_gateway, ['stripe', 'paypal'], true)) {
            return false;
        }

        if ((string) $order->payment_status === 'refunded') {
            return true;
        }

        if ((string) $order->payment_status !== 'paid') {
            return false;
        }

        if (
            filled(data_get($meta, 'payment.refund.id'))
            || filled(data_get($meta, 'payment.refund.refund_id'))
            || filled(data_get($meta, 'stripe.refund.id'))
            || filled(data_get($meta, 'paypal.refund.id'))
        ) {
            return true;
        }

        if (blank($order->payment_transaction_id)) {
            $meta['payment']['refund_error'] = 'Payment transaction ID mancante.';
            $meta['payment']['refund_failed_at'] = now()->toISOString();

            return false;
        }

        try {
            $gateway = (string) $order->payment_gateway;
            $reason = $gateway === 'stripe'
                ? 'requested_by_customer'
                : 'sendcloud_label_cancelled';

            $refund = $this->paymentService->refundPayment(
                $gateway,
                (string) $order->payment_transaction_id,
                null,
                $reason
            );

            $meta['payment']['refund'] = array_merge($refund, [
                'gateway' => $gateway,
                'reason' => 'sendcloud_label_cancelled',
                'source' => $source,
                'sendcloud_status' => $sendcloudStatus,
                'refunded_at' => now()->toISOString(),
            ]);

            $meta['payment']['refund_error'] = null;
            $meta['payment']['refund_failed_at'] = null;
            $meta['payment']['refunded_at'] = now()->toISOString();

            $meta[$gateway]['refund'] = $refund;
            $meta[$gateway]['refunded_at'] = now()->toISOString();

            Log::info('SENDCLOUD AUTO PAYMENT REFUND COMPLETED', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'gateway' => $gateway,
                'payment_transaction_id' => $order->payment_transaction_id,
                'source' => $source,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::error('SENDCLOUD AUTO PAYMENT REFUND FAILED', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'gateway' => $order->payment_gateway,
                'payment_transaction_id' => $order->payment_transaction_id,
                'error' => $exception->getMessage(),
                'payload' => $payload,
            ]);

            $meta['payment']['refund_error'] = $exception->getMessage();
            $meta['payment']['refund_failed_at'] = now()->toISOString();

            return false;
        }
    }

    private function resolveOrderFromWebhook(?string $orderNumber, ?string $incomingOrderId, ?string $externalOrderId = null, ?string $parcelId = null, ?string $trackingNumber = null): ?Order
    {
        $orderNumber = $this->normalizeOrderNumber($orderNumber);

        if ($orderNumber !== null) {
            $order = Order::query()
                ->where('order_number', $orderNumber)
                ->orWhere('order_number', ltrim($orderNumber, '#'))
                ->first();

            if ($order instanceof Order) {
                return $order;
            }
        }

        if ($externalOrderId !== null) {
            $order = Order::query()
                ->where('id', (int) $externalOrderId)
                ->orWhere('order_number', $externalOrderId)
                ->orWhere('meta->sendcloud->incoming_order_id', $externalOrderId)
                ->orWhere('meta->sendcloud->incoming_order_payload->data->0->id', $externalOrderId)
                ->orWhere('meta->sendcloud->incoming_order_payload->data->0->id', (int) $externalOrderId)
                ->first();

            if ($order instanceof Order) {
                return $order;
            }
        }

        if ($incomingOrderId !== null) {
            $order = Order::query()
                ->where('meta->sendcloud->incoming_order_id', $incomingOrderId)
                ->orWhere('meta->sendcloud->incoming_order_payload->data->0->id', $incomingOrderId)
                ->orWhere('meta->sendcloud->incoming_order_payload->data->0->id', (int) $incomingOrderId)
                ->first();

            if ($order instanceof Order) {
                return $order;
            }
        }

        if ($parcelId !== null) {
            $order = Order::query()
                ->where('meta->sendcloud->parcel_id', $parcelId)
                ->orWhere('meta->sendcloud->parcel_id', (int) $parcelId)
                ->first();

            if ($order instanceof Order) {
                return $order;
            }
        }

        if ($trackingNumber !== null) {
            $order = Order::query()
                ->where('shipping_tracking_number', $trackingNumber)
                ->orWhere('meta->sendcloud->tracking_number', $trackingNumber)
                ->first();

            if ($order instanceof Order) {
                return $order;
            }
        }

        return null;
    }

    private function normalizeOrderNumber(?string $orderNumber): ?string
    {
        $orderNumber = trim((string) $orderNumber);

        if ($orderNumber === '') {
            return null;
        }

        $orderNumber = preg_replace('/^ordine\s*/i', '', $orderNumber) ?: $orderNumber;
        $orderNumber = trim($orderNumber);

        return $orderNumber !== '' ? $orderNumber : null;
    }

    private function extractParcelPayload(array $payload): array
    {
        $parcel = $payload['parcel']
            ?? data_get($payload, 'data.parcel')
            ?? data_get($payload, 'data.object.parcel')
            ?? data_get($payload, 'data.object')
            ?? data_get($payload, 'data.0.parcel')
            ?? data_get($payload, 'data.0.object.parcel')
            ?? data_get($payload, 'data.0.object')
            ?? data_get($payload, 'object.parcel')
            ?? data_get($payload, 'object')
            ?? data_get($payload, 'shipment')
            ?? data_get($payload, 'data.shipment')
            ?? data_get($payload, 'data.0.shipment')
            ?? data_get($payload, 'data.0')
            ?? data_get($payload, 'data')
            ?? $payload;

        return is_array($parcel) ? $parcel : [];
    }

    private function extractOrderNumber(array $parcel, array $payload): ?string
    {
        $orderNumber = $this->firstScalar([
            $parcel['order_number'] ?? null,
            $parcel['reference'] ?? null,
            $parcel['external_reference'] ?? null,
            data_get($parcel, 'order.order_number'),
            data_get($parcel, 'order.number'),
            data_get($parcel, 'order.reference'),
            data_get($parcel, 'order.external_reference'),
            data_get($parcel, 'order_details.order_number'),
            data_get($payload, 'parcel.order_number'),
            data_get($payload, 'parcel.reference'),
            data_get($payload, 'parcel.external_reference'),
            data_get($payload, 'order_number'),
            data_get($payload, 'reference'),
            data_get($payload, 'external_reference'),
            data_get($payload, 'data.order_number'),
            data_get($payload, 'data.reference'),
            data_get($payload, 'data.external_reference'),
            data_get($payload, 'data.object.order_number'),
            data_get($payload, 'data.object.reference'),
            data_get($payload, 'data.0.order_number'),
            data_get($payload, 'data.0.reference'),
            data_get($payload, 'data.0.external_reference'),
            data_get($payload, 'data.0.object.order_number'),
            data_get($payload, 'data.0.object.reference'),
        ]);

        return $this->normalizeOrderNumber($orderNumber);
    }

    private function extractIncomingOrderId(array $payload): ?string
    {
        $id = $this->firstScalar([
            data_get($payload, 'data.0.id'),
            data_get($payload, 'data.0.order_id'),
            data_get($payload, 'data.0.order_uuid'),
            data_get($payload, 'data.id'),
            data_get($payload, 'data.order_id'),
            data_get($payload, 'data.order_uuid'),
            data_get($payload, 'data.object.id'),
            data_get($payload, 'data.object.order_id'),
            data_get($payload, 'data.0.object.id'),
            data_get($payload, 'data.0.object.order_id'),
            data_get($payload, 'data.order.id'),
            data_get($payload, 'order.id'),
            data_get($payload, 'id'),
        ]);

        return $this->filledString($id);
    }

    private function extractExternalOrderId(array $parcel, array $payload): ?string
    {
        $id = $this->firstScalar([
            $parcel['external_order_id'] ?? null,
            $parcel['order_id'] ?? null,
            data_get($parcel, 'external_order_id'),
            data_get($parcel, 'order_id'),
            data_get($parcel, 'order.external_order_id'),
            data_get($parcel, 'order.id'),
            data_get($payload, 'parcel.external_order_id'),
            data_get($payload, 'parcel.order_id'),
            data_get($payload, 'external_order_id'),
            data_get($payload, 'order_id'),
            data_get($payload, 'data.external_order_id'),
            data_get($payload, 'data.order_id'),
            data_get($payload, 'data.object.external_order_id'),
            data_get($payload, 'data.object.order_id'),
            data_get($payload, 'data.0.external_order_id'),
            data_get($payload, 'data.0.order_id'),
            data_get($payload, 'data.0.object.external_order_id'),
            data_get($payload, 'data.0.object.order_id'),
        ]);

        return $this->filledString($id);
    }

    private function extractParcelId(array $parcel): ?string
    {
        $parcelId = $this->firstScalar([
            $parcel['id'] ?? null,
            $parcel['parcel_id'] ?? null,
            data_get($parcel, 'parcel.id'),
            data_get($parcel, 'parcel_id'),
            data_get($parcel, 'data.parcel.id'),
            data_get($parcel, 'data.object.parcel.id'),
            data_get($parcel, 'data.object.id'),
            data_get($parcel, 'data.0.parcel.id'),
            data_get($parcel, 'data.0.object.parcel.id'),
            data_get($parcel, 'data.0.object.id'),
        ]);

        return $this->filledString($parcelId);
    }

    private function extractTrackingNumber(array $parcel): ?string
    {
        $trackingNumber = $this->firstScalar([
            $parcel['tracking_number'] ?? null,
            $parcel['tracking_code'] ?? null,
            $parcel['colli_tracking_number'] ?? null,
            $parcel['carrier_tracking_number'] ?? null,
            $parcel['tracking_url'] ?? null,
            $parcel['track_trace_url'] ?? null,
            data_get($parcel, 'tracking.number'),
            data_get($parcel, 'tracking.code'),
            data_get($parcel, 'tracking.tracking_number'),
            data_get($parcel, 'tracking.tracking_code'),
            data_get($parcel, 'tracking.url'),
            data_get($parcel, 'tracking.track_trace_url'),
            data_get($parcel, 'shipment.tracking_number'),
            data_get($parcel, 'shipment.tracking_code'),
            data_get($parcel, 'carrier.tracking_number'),
            data_get($parcel, 'carrier_tracking_number'),
            data_get($parcel, 'data.tracking_number'),
            data_get($parcel, 'data.object.tracking_number'),
            data_get($parcel, 'data.0.tracking_number'),
            data_get($parcel, 'data.0.object.tracking_number'),
        ]);

        $trackingNumber = $this->filledString($trackingNumber);

        if ($trackingNumber !== null && filter_var($trackingNumber, FILTER_VALIDATE_URL)) {
            parse_str((string) parse_url($trackingNumber, PHP_URL_QUERY), $query);

            $trackingNumber = $this->filledString($query['code'] ?? null)
                ?: $this->filledString(basename((string) parse_url($trackingNumber, PHP_URL_PATH)))
                ?: $trackingNumber;
        }

        return $trackingNumber;
    }

    private function extractLabelUrl(array $parcel): ?string
    {
        $labelUrl = $this->firstScalar([
            data_get($parcel, 'label.normal_printer.0'),
            data_get($parcel, 'label.normal_printer'),
            data_get($parcel, 'label.label_printer'),
            data_get($parcel, 'label.url'),
            data_get($parcel, 'label.href'),
            data_get($parcel, 'label.link'),
            data_get($parcel, 'documents.0.link'),
            data_get($parcel, 'documents.0.url'),
            data_get($parcel, 'documents.label.url'),
            data_get($parcel, 'documents.label.link'),
            data_get($parcel, 'data.label.url'),
            data_get($parcel, 'data.object.label.url'),
            data_get($parcel, 'data.0.label.url'),
            data_get($parcel, 'data.0.object.label.url'),
            $parcel['label_url'] ?? null,
        ]);

        return $this->filledString($labelUrl);
    }

    private function extractSendcloudStatus(array $parcel, array $payload): ?string
    {
        $status = $this->firstScalar([
            data_get($parcel, 'status.message'),
            data_get($parcel, 'status.code'),
            data_get($parcel, 'status.id'),
            data_get($parcel, 'status'),
            data_get($parcel, 'order_details.status.message'),
            data_get($parcel, 'order_details.status.code'),
            data_get($payload, 'parcel.status.message'),
            data_get($payload, 'parcel.status.code'),
            data_get($payload, 'parcel.status.id'),
            data_get($payload, 'status.message'),
            data_get($payload, 'status.code'),
            data_get($payload, 'status.id'),
            data_get($payload, 'status'),
            data_get($payload, 'data.status.message'),
            data_get($payload, 'data.status.code'),
            data_get($payload, 'data.object.status.message'),
            data_get($payload, 'data.object.status.code'),
            data_get($payload, 'data.0.status.message'),
            data_get($payload, 'data.0.status.code'),
            data_get($payload, 'data.0.object.status.message'),
            data_get($payload, 'data.0.object.status.code'),
            data_get($payload, 'event'),
            data_get($payload, 'action'),
            data_get($payload, 'type'),
        ]);

        $status = $this->normalizeStatus($status);

        return $status !== '' ? $status : null;
    }

    private function extractStatusMessage(array $parcel, array $payload): ?string
    {
        $message = $this->firstScalar([
            data_get($parcel, 'status.message'),
            data_get($payload, 'parcel.status.message'),
            data_get($payload, 'status.message'),
            data_get($payload, 'data.status.message'),
            data_get($payload, 'data.object.status.message'),
            data_get($payload, 'data.0.status.message'),
            data_get($payload, 'data.0.object.status.message'),
        ]);

        return $this->filledString($message);
    }

    private function hasSendcloudLabelData(?string $parcelId, ?string $trackingNumber, ?string $labelUrl, ?string $status): bool
    {
        return $parcelId !== null
            || $trackingNumber !== null
            || $labelUrl !== null
            || $this->isCompleteStatus($status);
    }

    private function shouldSendAcceptedCustomerMail(Order $order): bool
    {
        if (!$order->isB2c()) {
            return false;
        }

        if ((string) $order->payment_status !== 'paid') {
            return false;
        }

        if (!$this->orderHasTrackingOrBarcode($order)) {
            return false;
        }

        return !$this->customerStatusMailWasAlreadyHandled($order, 'accepted');
    }


    private function sendCustomerStatusMailOnce(Order $order, string $event): void
    {
        if (!in_array($event, ['accepted', 'canceled', 'refunded'], true)) {
            return;
        }

        if (!$order->isB2c()) {
            return;
        }

        if ($event === 'accepted' && !$this->orderHasTrackingOrBarcode($order)) {
            return;
        }

        $to = trim((string) ($order->customer_email ?: $order->shipping_email ?: $order->billing_email));

        if ($to === '') {
            return;
        }

        $lockedOrder = null;
        $mailKey = $event . '_customer';

        DB::transaction(function () use ($order, $event, $mailKey, &$lockedOrder): void {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            if (!$lockedOrder instanceof Order) {
                return;
            }

            $lockedOrder->loadMissing(['store', 'customer', 'items']);

            if ($this->customerStatusMailWasAlreadyHandled($lockedOrder, $event)) {
                $lockedOrder = null;
                return;
            }

            $meta = $this->orderMeta($lockedOrder);
            $meta['mail'] = array_merge($meta['mail'] ?? [], [
                $mailKey . '_sending_at' => now()->toISOString(),
                $mailKey . '_error' => null,
                $mailKey . '_failed_at' => null,
            ]);

            $lockedOrder->forceFill(['meta' => $meta])->save();
        });

        if (!$lockedOrder instanceof Order) {
            return;
        }

        try {
            $lockedOrder = $lockedOrder->fresh(['store', 'customer', 'items']) ?: $lockedOrder;

            Mail::to($to)->send(new OrderStatusMail($lockedOrder, $event));

            $meta = $this->orderMeta($lockedOrder);
            $meta['mail'] = array_merge($meta['mail'] ?? [], [
                $mailKey . '_sent_at' => now()->toISOString(),
                $mailKey . '_sending_at' => null,
                $mailKey . '_error' => null,
                $mailKey . '_failed_at' => null,
            ]);

            $lockedOrder->forceFill(['meta' => $meta])->save();
        } catch (Throwable $exception) {
            report($exception);

            $meta = $this->orderMeta($lockedOrder);
            $meta['mail'] = array_merge($meta['mail'] ?? [], [
                $mailKey . '_sending_at' => null,
                $mailKey . '_error' => $exception->getMessage(),
                $mailKey . '_failed_at' => now()->toISOString(),
            ]);

            $lockedOrder->forceFill(['meta' => $meta])->save();
        }
    }

    private function customerStatusMailWasAlreadyHandled(Order $order, string $event): bool
    {
        $meta = $this->orderMeta($order);
        $mailKey = $event . '_customer';

        return filled(data_get($meta, 'mail.' . $mailKey . '_sent_at'))
            || filled(data_get($meta, 'mail.' . $mailKey . '_sending_at'));
    }

    private function orderHasTrackingOrBarcode(Order $order): bool
    {
        $meta = $this->orderMeta($order);

        return filled($order->shipping_tracking_number)
            || filled(data_get($meta, 'sendcloud.barcode'))
            || filled(data_get($meta, 'sendcloud.tracking_number'))
            || filled(data_get($meta, 'sendcloud.parcel_payload.parcel.tracking_number'))
            || filled(data_get($meta, 'sendcloud.parcel_payload.parcel.tracking_url'))
            || filled(data_get($meta, 'sendcloud.webhook_payload.parcel.tracking_number'))
            || filled(data_get($meta, 'sendcloud.webhook_payload.parcel.tracking_url'));
    }

    private function isCanceledStatus(?string $status): bool
    {
        $status = $this->normalizeStatus($status);

        return in_array($status, [
            'cancelled',
            'canceled',
            'cancel',
            'deleted',
            'delete',
            'removed',
            'cancellation.requested',
            'order.deleted',
            'order.cancelled',
            'order.canceled',
            'incoming.order.deleted',
            'incoming.order.cancelled',
            'incoming.order.canceled',
        ], true) || str_contains($status, 'deleted') || str_contains($status, 'cancel');
    }

    private function isCompleteStatus(?string $status): bool
    {
        $status = $this->normalizeStatus($status);

        return in_array($status, [
            '1000',
            '1001',
            'announced',
            'being.announced',
            'ready.to.send',
            'ready.to.ship',
            'ready.for.pickup',
            'label.created',
            'parcel.created',
            'shipped',
            'delivered',
            'fulfilled',
            'complete',
            'completed',
        ], true);
    }

    private function isRefundedPaymentStatus(?string $status): bool
    {
        return in_array($this->normalizeStatus($status), ['refunded', 'refund'], true);
    }

    private function isCanceledPaymentStatus(?string $status): bool
    {
        return in_array($this->normalizeStatus($status), ['cancelled', 'canceled', 'cancel', 'failed', 'voided'], true);
    }

    private function isPaidPaymentStatus(?string $status): bool
    {
        return in_array($this->normalizeStatus($status), ['paid', 'captured', 'completed'], true);
    }

    private function normalizeStatus(mixed $status): string
    {
        if (is_array($status)) {
            $status = $status['message']
                ?? $status['code']
                ?? $status['id']
                ?? '';
        }

        if (is_object($status)) {
            $status = '';
        }

        return strtolower(str_replace([' ', '_', '-'], ['.', '.', '.'], trim((string) $status)));
    }

    private function canceledPaymentStatusForGateway(Order $order): string
    {
        return (string) $order->payment_status === 'refunded' ? 'refunded' : 'canceled';
    }

    private function resolveIncomingOrderId(Order $order): ?string
    {
        $meta = $this->orderMeta($order);

        return $this->filledString(
            data_get($meta, 'sendcloud.incoming_order_id')
            ?? data_get($meta, 'sendcloud.incoming_order_payload.data.0.id')
        );
    }

    private function resolveParcelId(Order $order): ?string
    {
        return $this->filledString(data_get($this->orderMeta($order), 'sendcloud.parcel_id'));
    }

    private function firstScalar(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            if (is_scalar($value)) {
                $value = trim((string) $value);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function filledString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function orderMeta(?Order $order): array
    {
        if (!$order instanceof Order) {
            return [];
        }

        $meta = $order->meta ?? [];

        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        return is_array($meta) ? $meta : [];
    }

    private function isSendcloudAlreadyCancellingError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'already being cancelled')
            || str_contains($message, 'already being canceled')
            || str_contains($message, 'already cancelled')
            || str_contains($message, 'already canceled')
            || str_contains($message, 'being cancelled')
            || str_contains($message, 'being canceled');
    }
}