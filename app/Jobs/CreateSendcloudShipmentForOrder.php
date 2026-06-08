<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Shipping\Sendcloud\SendcloudService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class CreateSendcloudShipmentForOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $orderId)
    {
        $this->onQueue('default');
    }

    public function handle(SendcloudService $sendcloudService): void
    {
        $order = Order::query()->with(['store', 'customer', 'items'])->find($this->orderId);

        if (!$order instanceof Order) {
            return;
        }

        if (!$this->canSyncToSendcloud($order)) {
            $this->storeSendcloudSkipped($order, $this->resolveSkipReason($order));
            return;
        }

        $meta = $this->orderMeta($order);

        if (filled(data_get($meta, 'sendcloud.incoming_order_id'))) {
            $this->storeSendcloudSkipped($order, 'Ordine Sendcloud già sincronizzato.');
            return;
        }

        if ($this->hasIncompleteShippingData($order)) {
            $this->storeSendcloudError($order, 'Dati spedizione incompleti.');
            return;
        }

        try {
            $payload = $sendcloudService->createIncomingOrder($order);
            $incomingOrderId = $sendcloudService->extractIncomingOrderId($payload);

            if (!$incomingOrderId) {
                throw new RuntimeException('Sendcloud non ha restituito l’ID ordine.');
            }

            $trackingNumber = $this->firstFilled([
                $sendcloudService->extractTrackingNumber($payload),
                data_get($payload, 'parcel.tracking_number'),
                data_get($payload, 'parcel.tracking_identifier'),
                data_get($payload, 'parcel.colli_tracking_number'),
                data_get($payload, 'data.tracking_number'),
                data_get($payload, 'data.0.tracking_number'),
                data_get($payload, 'tracking_number'),
            ]);

            $barcode = $this->firstFilled([
                $sendcloudService->extractBarcode($payload),
                data_get($payload, 'parcel.barcode'),
                data_get($payload, 'parcel.tracking_number'),
                data_get($payload, 'data.barcode'),
                data_get($payload, 'data.0.barcode'),
                data_get($payload, 'barcode'),
                $trackingNumber,
            ]);

            $trackingUrl = $this->firstFilled([
                $sendcloudService->extractTrackingUrl($payload),
                data_get($payload, 'parcel.tracking_url'),
                data_get($payload, 'data.tracking_url'),
                data_get($payload, 'data.0.tracking_url'),
                data_get($payload, 'tracking_url'),
            ]);

            $labelUrl = $this->firstFilled([
                $sendcloudService->extractLabelUrl($payload),
                data_get($payload, 'parcel.label.normal_printer.0'),
                data_get($payload, 'parcel.label.normal_printer'),
                data_get($payload, 'parcel.label.label_printer'),
                data_get($payload, 'parcel.documents.0.link'),
                data_get($payload, 'data.label_url'),
                data_get($payload, 'data.0.label_url'),
                data_get($payload, 'label_url'),
            ]);

            $hasShipmentData = filled($trackingNumber)
                || filled($barcode)
                || filled($trackingUrl)
                || filled($labelUrl);

            $freshOrder = $order->fresh(['store', 'customer', 'items']) ?: $order;
            $meta = $this->orderMeta($freshOrder);

            $meta['sendcloud'] = array_merge($meta['sendcloud'] ?? [], [
                'managed_by_sendcloud' => true,
                'created_from_bo_processing' => true,
                'pending_webhook' => !$hasShipmentData,
                'incoming_order_id' => $incomingOrderId,
                'incoming_order_payload' => $payload,
                'tracking_number' => $trackingNumber,
                'barcode' => $barcode,
                'tracking_url' => $trackingUrl,
                'label_url' => $labelUrl,
                'synced_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
                'error' => null,
                'failed_at' => null,
                'skipped_reason' => null,
                'skipped_at' => null,
            ]);

            $freshOrder->forceFill([
                'status' => 'processing',
                'fulfillment_status' => $hasShipmentData ? 'shipped' : 'processing',
                'shipping_gateway' => 'sendcloud',
                'shipping_tracking_number' => $trackingNumber ?: $barcode,
                'shipping_label_url' => $labelUrl,
                'shipping_label_created_at' => filled($labelUrl) ? now() : null,
                'meta' => $meta,
            ])->save();
        } catch (Throwable $exception) {
            $this->storeSendcloudError($order, $exception->getMessage());
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $order = Order::query()->find($this->orderId);

        if ($order instanceof Order) {
            $this->storeSendcloudError($order, $exception->getMessage());
        }

        report($exception);
    }

    private function canSyncToSendcloud(Order $order): bool
    {
        return (string) $order->channel === 'b2c'
            && in_array((string) $order->payment_gateway, ['stripe', 'paypal'], true)
            && (string) $order->payment_status === 'paid'
            && (string) $order->status === 'processing'
            && (string) $order->fulfillment_status === 'processing'
            && (string) $order->shipping_gateway === 'sendcloud'
            && blank($order->shipping_tracking_number)
            && blank($order->shipping_label_url);
    }

    private function resolveSkipReason(Order $order): string
    {
        if ((string) $order->channel !== 'b2c') {
            return 'Ordine non B2C: Sendcloud non richiesto.';
        }

        if (!in_array((string) $order->payment_gateway, ['stripe', 'paypal'], true)) {
            return 'Gateway pagamento non gestito per Sendcloud.';
        }

        if ((string) $order->payment_status !== 'paid') {
            return 'Pagamento non ancora acquisito: Sendcloud partirà dopo capture BO.';
        }

        if ((string) $order->status !== 'processing' || (string) $order->fulfillment_status !== 'processing') {
            return 'Ordine non confermato in processing dal BO.';
        }

        if ((string) $order->shipping_gateway !== 'sendcloud') {
            return 'Shipping gateway diverso da Sendcloud.';
        }

        if (filled($order->shipping_tracking_number) || filled($order->shipping_label_url)) {
            return 'Spedizione Sendcloud già presente.';
        }

        return 'Ordine non sincronizzabile con Sendcloud.';
    }

    private function hasIncompleteShippingData(Order $order): bool
    {
        return blank($order->shipping_address_line_1)
            || blank($order->shipping_postcode)
            || blank($order->shipping_city)
            || blank($order->shipping_country_code)
            || blank($order->shipping_email);
    }

    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                $value = reset($value);
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function storeSendcloudError(Order $order, string $message): void
    {
        $meta = $this->orderMeta($order);

        $meta['sendcloud'] = array_merge($meta['sendcloud'] ?? [], [
            'error' => $message,
            'failed_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]);

        $order->forceFill(['meta' => $meta])->save();
    }

    private function storeSendcloudSkipped(Order $order, string $message): void
    {
        $meta = $this->orderMeta($order);

        $meta['sendcloud'] = array_merge($meta['sendcloud'] ?? [], [
            'skipped_reason' => $message,
            'skipped_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
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