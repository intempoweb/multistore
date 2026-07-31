<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Shipping\Sendcloud\SendcloudService;
use Illuminate\Console\Command;
use Throwable;

class SyncPendingSendcloudLabels extends Command
{
    protected $signature = 'sendcloud:sync-pending-labels
                            {--limit=50 : Numero massimo ordini da verificare}';

    protected $description = 'Riconcilia automaticamente tracking/barcode/etichette Sendcloud per ordini B2C in attesa webhook';

    public function handle(SendcloudService $sendcloudService): int
    {
        $orders = Order::query()
            ->where('channel', 'b2c')
            ->where('shipping_gateway', 'sendcloud')
            ->whereNotNull('placed_at')
            ->where(function ($query): void {
                $query
                    ->whereNull('shipping_tracking_number')
                    ->orWhereNull('shipping_label_url')
                    ->orWhere('meta->sendcloud->pending_webhook', true);
            })
            ->where(function ($query): void {
                $query
                    ->whereNotNull('meta->sendcloud->incoming_order_id')
                    ->orWhereNotNull('meta->sendcloud->incoming_order_payload->data->0->id');
            })
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Nessun ordine Sendcloud in attesa.');

            return self::SUCCESS;
        }

        $synced = 0;
        $waiting = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $parcel = $this->resolveParcel($sendcloudService, $order);

                if ($parcel === null) {
                    $this->markStillWaiting($order, 'Parcel non ancora trovato su Sendcloud.');
                    $waiting++;
                    continue;
                }

                $hasData = $this->syncOrderFromParcel($sendcloudService, $order, $parcel);

                if ($hasData) {
                    $synced++;
                    $this->info(sprintf('[#%d] Aggiornato da Sendcloud.', $order->id));
                } else {
                    $waiting++;
                    $this->line(sprintf('[#%d] Parcel trovato, dati etichetta non ancora disponibili.', $order->id));
                }
            } catch (Throwable $exception) {
                $failed++;
                $this->storeSyncError($order, $exception->getMessage());
                report($exception);
                $this->error(sprintf('[#%d] ERRORE: %s', $order->id, $exception->getMessage()));
            }
        }

        $this->info(sprintf(
            'Sync Sendcloud completata. Aggiornati: %d - In attesa: %d - Errori: %d',
            $synced,
            $waiting,
            $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveParcel(SendcloudService $sendcloudService, Order $order): ?array
    {
        $meta = $this->orderMeta($order);
        $parcelId = $this->filledString(data_get($meta, 'sendcloud.parcel_id'));

        if ($parcelId !== null) {
            return $sendcloudService->getParcel($parcelId);
        }

        $parcels = $sendcloudService->findParcelsForOrder($order);

        if ($parcels === []) {
            return null;
        }

        foreach ($parcels as $parcel) {
            if (
                filled($sendcloudService->extractTrackingNumber($parcel))
                || filled($sendcloudService->extractBarcode($parcel))
                || filled($sendcloudService->extractTrackingUrl($parcel))
                || filled($sendcloudService->extractLabelUrl($parcel))
            ) {
                return $parcel;
            }
        }

        return $parcels[0];
    }

    private function syncOrderFromParcel(SendcloudService $sendcloudService, Order $order, array $parcel): bool
    {
        $parcelId = $this->firstFilled([
            $sendcloudService->extractParcelId($parcel),
            data_get($parcel, 'parcel.id'),
            data_get($parcel, 'id'),
        ]);

        $trackingNumber = $sendcloudService->extractTrackingNumber($parcel);
        $barcode = $sendcloudService->extractBarcode($parcel);
        $trackingUrl = $sendcloudService->extractTrackingUrl($parcel);
        $labelUrl = $sendcloudService->extractLabelUrl($parcel);

        $hasShipmentData = filled($trackingNumber)
            || filled($barcode)
            || filled($trackingUrl)
            || filled($labelUrl);

        $meta = $this->orderMeta($order);
        $meta['sendcloud'] = array_merge($meta['sendcloud'] ?? [], [
            'parcel_id' => $parcelId ?: data_get($meta, 'sendcloud.parcel_id'),
            'tracking_number' => $trackingNumber ?: data_get($meta, 'sendcloud.tracking_number'),
            'barcode' => $barcode ?: data_get($meta, 'sendcloud.barcode'),
            'tracking_url' => $trackingUrl ?: data_get($meta, 'sendcloud.tracking_url'),
            'label_url' => $labelUrl ?: data_get($meta, 'sendcloud.label_url'),
            'parcel_payload' => $parcel,
            'status_message' => $hasShipmentData
                ? 'Etichetta riletta automaticamente da Sendcloud'
                : data_get($meta, 'sendcloud.status_message'),
            'pending_webhook' => !$hasShipmentData,
            'last_reconciled_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
            'status_sync_error' => null,
            'status_sync_failed_at' => null,
        ]);

        $updates = [
            'meta' => $meta,
        ];

        if (filled($trackingNumber) || filled($barcode)) {
            $updates['shipping_tracking_number'] = $trackingNumber ?: $barcode;
        }

        if (filled($labelUrl)) {
            $updates['shipping_label_url'] = $labelUrl;
            $updates['shipping_label_created_at'] = $order->shipping_label_created_at ?: now();
        }

        if ($hasShipmentData) {
            $updates['status'] = 'complete';
            $updates['fulfillment_status'] = 'complete';
        }

        $order->forceFill($updates)->save();

        return $hasShipmentData;
    }

    private function markStillWaiting(Order $order, string $message): void
    {
        $meta = $this->orderMeta($order);
        $meta['sendcloud'] = array_merge($meta['sendcloud'] ?? [], [
            'pending_webhook' => true,
            'last_reconciled_at' => now()->toISOString(),
            'status_message' => data_get($meta, 'sendcloud.status_message') ?: $message,
        ]);

        $order->forceFill(['meta' => $meta])->save();
    }

    private function storeSyncError(Order $order, string $message): void
    {
        $meta = $this->orderMeta($order);
        $meta['sendcloud'] = array_merge($meta['sendcloud'] ?? [], [
            'status_sync_error' => $message,
            'status_sync_failed_at' => now()->toISOString(),
            'last_reconciled_at' => now()->toISOString(),
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

    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            $value = $this->filledString($value);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function filledString(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
