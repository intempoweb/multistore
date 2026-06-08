<?php

namespace App\Services\Shipping\Sendcloud;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SendcloudService
{
    public function createIncomingOrder(Order $order): array
    {
        $order->loadMissing('items');

        $payload = [[
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'order_details' => [
                'integration' => ['id' => $this->integrationId()],
                'status' => $this->sendcloudOrderStatusPayload($order),
                'order_created_at' => optional($order->placed_at ?: $order->created_at)->toISOString(),
                'order_updated_at' => optional($order->updated_at)->toISOString(),
                'order_items' => $this->orderItemsPayload($order),
            ],
            'payment_details' => [
                'status' => $this->sendcloudPaymentStatusPayload($order),
                'total_price' => [
                    'value' => $this->formatMoney($order->grand_total),
                    'currency' => $this->currency($order),
                ],
            ],
            'shipping_address' => [
                'name' => $this->resolveRecipientName($order),
                'company_name' => $this->nullableString($order->shipping_company),
                'address_line_1' => $this->nullableString($order->shipping_address_line_1),
                'address_line_2' => $this->nullableString($order->shipping_address_line_2),
                'postal_code' => $this->nullableString($order->shipping_postcode),
                'city' => $this->nullableString($order->shipping_city),
                'country_code' => $this->toIso2Country($order->shipping_country_code),
                'email' => $this->nullableString($order->shipping_email),
                'phone_number' => $this->nullableString($order->shipping_phone),
            ],
        ]];

        $response = $this->client()->post($this->ordersBaseUrl() . '/orders', $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Errore creazione ordine Sendcloud: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    public function updateIncomingOrderStatus(Order $order, ?string $status = null): array
    {
        $incomingOrderId = $this->resolveIncomingOrderId($order);

        $payload = [
            'order_details' => [
                'status' => $this->sendcloudOrderStatusPayload($order, $status),
                'order_updated_at' => now()->toISOString(),
            ],
            'payment_details' => [
                'status' => $this->sendcloudPaymentStatusPayload($order),
            ],
        ];

        $response = $this->client()->patch($this->ordersBaseUrl() . '/orders/' . $incomingOrderId, $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Errore aggiornamento ordine Sendcloud: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    public function cancelIncomingOrder(Order $order): array
    {
        $meta = $this->orderMeta($order);
        $parcelId = $this->nullableString(data_get($meta, 'sendcloud.parcel_id'));

        $parcelPayload = null;

        if ($parcelId !== null) {
            $parcelPayload = $this->cancelParcel($parcelId);
        }

        $incomingPayload = null;

        try {
            $incomingPayload = $this->updateIncomingOrderStatus($order, 'canceled');
        } catch (RuntimeException $exception) {
            if (!$this->isAlreadyCancellingMessage($exception->getMessage())) {
                throw $exception;
            }

            $incomingPayload = [
                'status' => 'accepted',
                'message' => $exception->getMessage(),
                'already_cancelling' => true,
            ];
        }

        return [
            'status' => 'cancelled',
            'parcel_payload' => $parcelPayload,
            'incoming_order_payload' => $incomingPayload,
        ];
    }

    public function deleteIncomingOrder(Order $order): array
    {
        $incomingOrderId = $this->resolveIncomingOrderId($order);

        $response = $this->client()->delete($this->ordersBaseUrl() . '/orders/' . $incomingOrderId);

        if (!$response->successful()) {
            throw new RuntimeException('Errore eliminazione ordine Sendcloud: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    public function createParcel(Order $order): array
    {
        $shipmentId = config('services.sendcloud.default_shipment_id');

        if (!$shipmentId) {
            throw new RuntimeException('SENDCLOUD_DEFAULT_SHIPMENT_ID non configurato.');
        }

        $response = $this->client()->post($this->baseUrl() . '/parcels', [
            'parcel' => [
                'name' => $this->resolveRecipientName($order),
                'company_name' => $this->nullableString($order->shipping_company),
                'address' => $this->nullableString($order->shipping_address_line_1),
                'address_2' => $this->nullableString($order->shipping_address_line_2),
                'postal_code' => $this->nullableString($order->shipping_postcode),
                'city' => $this->nullableString($order->shipping_city),
                'country' => $this->toIso2Country($order->shipping_country_code),
                'telephone' => $this->nullableString($order->shipping_phone),
                'email' => $this->nullableString($order->shipping_email),
                'order_number' => (string) $order->order_number,
                'request_label' => true,
                'shipment' => ['id' => (int) $shipmentId],
            ],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Errore Sendcloud: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    public function getParcel(string|int $parcelId): array
    {
        $parcelId = $this->normalizeParcelId($parcelId);

        $response = $this->client()->get($this->baseUrl() . '/parcels/' . $parcelId);

        if (!$response->successful()) {
            throw new RuntimeException('Errore lettura parcel Sendcloud: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    public function cancelParcel(string|int $parcelId): array
    {
        $parcelId = $this->normalizeParcelId($parcelId);

        $response = $this->client()->post($this->baseUrl() . '/parcels/' . $parcelId . '/cancel');

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        if ($this->responseMeansAlreadyCancelling($response)) {
            return [
                'status' => 'accepted',
                'message' => 'This shipment is already being cancelled.',
                'already_cancelling' => true,
                'original_payload' => $response->json() ?? $response->body(),
            ];
        }

        throw new RuntimeException('Errore annullamento Sendcloud: ' . $response->body());
    }

    public function extractIncomingOrderId(array $payload): ?string
    {
        $order = $this->extractOrderPayload($payload);

        if (!is_array($order)) {
            return null;
        }

        return $this->firstFilledValue($order, [
            'id',
            'order_id',
            'order_uuid',
            'order_id_internal',
            'external_order_id',
            'order.id',
            'order_details.id',
        ]);
    }

    public function extractParcelId(array $payload): ?string
    {
        $parcel = $this->extractParcelPayload($payload);

        if (!is_array($parcel)) {
            return null;
        }

        return $this->firstFilledValue($parcel, [
            'id',
            'parcel_id',
            'parcel.id',
        ]);
    }

    public function extractTrackingNumber(array $payload): ?string
    {
        $parcel = $this->extractParcelPayload($payload);

        if (!is_array($parcel)) {
            return null;
        }

        $trackingNumber = $this->firstFilledValue($parcel, [
            'tracking_number',
            'tracking_code',
            'tracking_number_external',
            'carrier_tracking_number',
            'colli_tracking_number',
            'awb_tracking_number',
            'tracking_identifier',
            'tracking.number',
            'tracking.code',
            'tracking.tracking_number',
            'tracking.tracking_code',
            'shipment.tracking_number',
            'shipment.tracking_code',
            'carrier.tracking_number',
            'data.tracking_number',
            'data.object.tracking_number',
            'data.0.tracking_number',
            'data.0.object.tracking_number',
            'parcel.tracking_number',
            'parcel.tracking_code',
            'parcel.colli_tracking_number',
            'parcel.awb_tracking_number',
        ]);

        if ($trackingNumber !== null) {
            return $this->trackingCodeFromPossibleUrl($trackingNumber);
        }

        $trackingUrl = $this->extractTrackingUrl($payload);

        return $trackingUrl !== null ? $this->trackingCodeFromPossibleUrl($trackingUrl) : null;
    }

    public function extractBarcode(array $payload): ?string
    {
        $parcel = $this->extractParcelPayload($payload);

        if (!is_array($parcel)) {
            return null;
        }

        return $this->firstFilledValue($parcel, [
            'barcode',
            'colli_uuid',
            'collo_uuid',
            'label_barcode',
            'label.barcode',
            'parcel.barcode',
            'parcel.colli_uuid',
            'parcel.collo_uuid',
            'tracking_number',
            'tracking_code',
            'colli_tracking_number',
            'tracking.number',
            'tracking.code',
            'tracking.tracking_number',
            'data.barcode',
            'data.tracking_number',
            'data.0.barcode',
            'data.0.tracking_number',
        ]);
    }

    public function extractTrackingUrl(array $payload): ?string
    {
        $parcel = $this->extractParcelPayload($payload);

        if (!is_array($parcel)) {
            return null;
        }

        return $this->firstFilledValue($parcel, [
            'tracking_url',
            'external_tracking_url',
            'tracking_link',
            'track_trace_url',
            'tracking.url',
            'tracking.tracking_url',
            'tracking.href',
            'tracking.track_trace_url',
            'parcel.tracking_url',
            'parcel.track_trace_url',
            'data.tracking_url',
            'data.object.tracking_url',
            'data.0.tracking_url',
            'data.0.object.tracking_url',
        ]);
    }

    public function extractLabelUrl(array $payload): ?string
    {
        $parcel = $this->extractParcelPayload($payload);

        if (!is_array($parcel)) {
            return null;
        }

        return $this->firstFilledValue($parcel, [
            'label_url',
            'label.url',
            'label.normal_printer.0',
            'label.normal_printer',
            'label.label_printer',
            'documents.0.link',
            'documents.0.url',
            'documents.label.url',
            'documents.label.link',
            'parcel.label.normal_printer.0',
            'parcel.label.normal_printer',
            'parcel.label.label_printer',
            'parcel.documents.0.link',
            'data.label_url',
            'data.label.url',
            'data.object.label.url',
            'data.0.label.url',
            'data.0.object.label.url',
            'label',
        ]);
    }

    public function extractSendcloudOrderStatus(array $payload): ?string
    {
        $status = $this->firstFilledValue($payload, [
            'order_details.status.code',
            'data.0.order_details.status.code',
            'data.order_details.status.code',
            'status.code',
            'status',
            'data.0.status.code',
            'data.0.status',
            'data.status.code',
            'data.status',
            'event',
            'action',
        ]);

        return $status !== null ? strtolower($status) : null;
    }

    public function extractSendcloudPaymentStatus(array $payload): ?string
    {
        $status = $this->firstFilledValue($payload, [
            'payment_details.status.code',
            'data.0.payment_details.status.code',
            'data.payment_details.status.code',
        ]);

        return $status !== null ? strtolower($status) : null;
    }

    public function normalizeIncomingStatus(?string $status): ?string
    {
        $status = strtolower(trim((string) $status));

        if ($status === '') {
            return null;
        }

        return match ($status) {
            'canceled', 'cancelled', 'cancel', 'deleted', 'delete', 'removed', 'order_deleted', 'order.deleted', 'order.cancelled', 'order.canceled' => 'canceled',
            'closed', 'refunded', 'refund', 'order.refunded' => 'closed',
            'complete', 'completed', 'fulfilled', 'shipped', 'delivered' => 'complete',
            'processing', 'ready_to_ship', 'ready-to-ship', 'unshipped', 'created', 'new' => 'processing',
            'pending', 'placed', 'draft' => 'pending',
            default => $status,
        };
    }

    public function normalizeIncomingPaymentStatus(?string $status): ?string
    {
        $status = strtolower(trim((string) $status));

        if ($status === '') {
            return null;
        }

        return match ($status) {
            'paid', 'captured', 'completed', 'succeeded' => 'paid',
            'authorized', 'requires_capture', 'requires_capture_payment', 'approved' => 'authorized',
            'refunded', 'refund' => 'refunded',
            'canceled', 'cancelled', 'cancel', 'failed', 'voided' => 'canceled',
            default => 'pending',
        };
    }

    private function responseMeansAlreadyCancelling(Response $response): bool
    {
        $payload = $response->json();

        $message = '';

        if (is_array($payload)) {
            $message = (string) (
                data_get($payload, 'message')
                ?: data_get($payload, 'error.message')
                ?: data_get($payload, 'detail')
                ?: data_get($payload, 'details.0.message')
                ?: data_get($payload, 'errors.0.message')
                ?: ''
            );
        }

        $message .= ' ' . $response->body();

        return $this->isAlreadyCancellingMessage($message);
    }

    private function isAlreadyCancellingMessage(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'already being cancelled')
            || str_contains($message, 'already being canceled')
            || str_contains($message, 'already cancelled')
            || str_contains($message, 'already canceled')
            || str_contains($message, 'being cancelled')
            || str_contains($message, 'being canceled');
    }

    private function extractOrderPayload(array $payload): ?array
    {
        $order = $payload['order']
            ?? data_get($payload, 'data.0')
            ?? data_get($payload, 'data.order')
            ?? data_get($payload, 'data')
            ?? ($payload[0] ?? null)
            ?? $payload;

        return is_array($order) ? $order : null;
    }

    private function extractParcelPayload(array $payload): ?array
    {
        $parcel = $payload['parcel']
            ?? data_get($payload, 'data.parcel')
            ?? data_get($payload, 'data.object.parcel')
            ?? data_get($payload, 'data.object')
            ?? data_get($payload, 'data.0.parcel')
            ?? data_get($payload, 'data.0.object.parcel')
            ?? data_get($payload, 'data.0.object')
            ?? data_get($payload, 'data.0')
            ?? data_get($payload, 'data')
            ?? data_get($payload, 'order.parcel')
            ?? data_get($payload, 'incoming_order.parcel')
            ?? data_get($payload, 'object.parcel')
            ?? data_get($payload, 'object')
            ?? data_get($payload, 'shipment')
            ?? $payload;

        return is_array($parcel) ? $parcel : null;
    }

    private function firstFilledValue(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if (is_array($value)) {
                $value = $value[0]
                    ?? data_get($value, 'url')
                    ?? data_get($value, 'link')
                    ?? data_get($value, 'href')
                    ?? data_get($value, 'id')
                    ?? data_get($value, 'barcode')
                    ?? data_get($value, 'tracking_number')
                    ?? data_get($value, 'tracking_code')
                    ?? null;
            }

            $value = $this->nullableString($value);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function trackingCodeFromPossibleUrl(string $value): string
    {
        $value = trim($value);

        if ($value === '' || !filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        parse_str((string) parse_url($value, PHP_URL_QUERY), $query);

        return $this->nullableString($query['code'] ?? null)
            ?? $this->nullableString(basename((string) parse_url($value, PHP_URL_PATH)))
            ?? $value;
    }

    private function resolveIncomingOrderId(Order $order): string
    {
        $incomingOrderId = $this->nullableString(data_get($this->orderMeta($order), 'sendcloud.incoming_order_id'));

        if ($incomingOrderId === null) {
            throw new RuntimeException('ID ordine Sendcloud mancante.');
        }

        return $incomingOrderId;
    }

    private function sendcloudOrderStatusPayload(Order $order, ?string $forcedStatus = null): array
    {
        return match ($this->normalizeLocalOrderStatus($forcedStatus ?: $order->status)) {
            'canceled', 'closed' => ['code' => 'cancelled', 'message' => 'Cancelled'],
            'complete' => ['code' => 'fulfilled', 'message' => 'Fulfilled'],
            'processing' => ['code' => 'unshipped', 'message' => 'Unshipped'],
            default => ['code' => 'unshipped', 'message' => 'Pending'],
        };
    }

    private function sendcloudPaymentStatusPayload(Order $order): array
    {
        return match (strtolower(trim((string) $order->payment_status))) {
            'paid', 'captured' => ['code' => 'paid', 'message' => 'Paid'],
            'authorized', 'requires_capture' => ['code' => 'pending', 'message' => 'Authorized'],
            'refunded' => ['code' => 'refunded', 'message' => 'Refunded'],
            'cancelled', 'canceled', 'failed' => ['code' => 'cancelled', 'message' => 'Cancelled'],
            default => ['code' => 'pending', 'message' => 'Pending'],
        };
    }

    private function normalizeLocalOrderStatus(mixed $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'cancelled', 'canceled', 'cancel' => 'canceled',
            'closed', 'refunded' => 'closed',
            'completed', 'complete', 'fulfilled', 'ready_to_ship', 'shipped' => 'complete',
            'processing' => 'processing',
            default => 'pending',
        };
    }

    private function orderItemsPayload(Order $order): array
    {
        return $order->items
            ->filter(fn ($item) => filled($item->sku))
            ->map(function ($item) use ($order): array {
                $quantity = max(1, (float) ($item->quantity ?? 1));
                $rowTotal = (float) ($item->row_total ?? 0);
                $unitValue = $quantity > 0 ? $rowTotal / $quantity : (float) ($item->price ?? 0);

                return [
                    'sku' => (string) $item->sku,
                    'name' => (string) ($item->product_name ?? $item->product_description ?? $item->sku),
                    'quantity' => (int) $quantity,
                    'unit_price' => [
                        'value' => $this->formatMoney($unitValue),
                        'currency' => $this->currency($order),
                    ],
                    'total_price' => [
                        'value' => $this->formatMoney($rowTotal),
                        'currency' => $this->currency($order),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    private function client(): PendingRequest
    {
        $publicKey = config('services.sendcloud.public_key');
        $secretKey = config('services.sendcloud.secret_key');

        if (!$publicKey || !$secretKey) {
            throw new RuntimeException('Credenziali Sendcloud non configurate.');
        }

        return Http::withBasicAuth($publicKey, $secretKey)
            ->timeout(30)
            ->acceptJson()
            ->asJson();
    }

    private function baseUrl(): string
    {
        return rtrim(trim((string) config('services.sendcloud.base_url', 'https://panel.sendcloud.sc/api/v2')), '/');
    }

    private function ordersBaseUrl(): string
    {
        return rtrim(trim((string) config('services.sendcloud.orders_base_url', 'https://panel.sendcloud.sc/api/v3')), '/');
    }

    private function integrationId(): int
    {
        $integrationId = $this->nullableString(config('services.sendcloud.integration_id'));

        if ($integrationId === null) {
            throw new RuntimeException('SENDCLOUD_INTEGRATION_ID non configurato.');
        }

        return (int) $integrationId;
    }

    private function resolveRecipientName(Order $order): string
    {
        $name = trim(implode(' ', array_filter([
            $order->shipping_first_name,
            $order->shipping_last_name,
        ])));

        return $name !== ''
            ? $name
            : ($this->nullableString($order->shipping_contact_name ?: $order->shipping_company) ?? 'Cliente');
    }

    private function toIso2Country(mixed $country): string
    {
        $country = strtoupper(trim((string) $country));

        return match ($country) {
            'ITA', 'IT' => 'IT',
            'FRA', 'FR' => 'FR',
            'DEU', 'DE' => 'DE',
            'ESP', 'ES' => 'ES',
            'GBR', 'GB' => 'GB',
            'NLD', 'NL' => 'NL',
            'BEL', 'BE' => 'BE',
            'AUT', 'AT' => 'AT',
            'CHE', 'CH' => 'CH',
            'JEY', 'JE' => 'JE',
            'JAM', 'JM' => 'JM',
            default => strlen($country) === 2 ? $country : $country,
        };
    }

    private function normalizeParcelId(string|int $parcelId): string
    {
        $parcelId = trim((string) $parcelId);

        if ($parcelId === '') {
            throw new RuntimeException('Parcel ID Sendcloud mancante.');
        }

        return $parcelId;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function currency(Order $order): string
    {
        return strtoupper(trim((string) ($order->currency ?: 'EUR'))) ?: 'EUR';
    }

    private function formatMoney(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
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