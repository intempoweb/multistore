<?php

namespace App\Mail\Storefront\Orders;

use App\Models\Order;
use App\Models\Store;
use App\Services\Storefront\Mail\StorefrontMailService;
use App\Services\Storefront\Orders\OrderProductImagesZipService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class OrderStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $event,
        public ?string $customSubject = null,
    ) {
    }

    public function build(): self
    {
        $this->order->loadMissing(['store', 'customer', 'items']);

        /** @var Store $store */
        $store = $this->order->store;

        $mailService = app(StorefrontMailService::class);
        $mailConfig = $mailService->configForStore($store);

        $mailService->applyStoreSender($this, $store);

        $productImagesZipPath = $this->productImagesZipPath();
        $productImagesZipSize = $productImagesZipPath !== null && is_file($productImagesZipPath)
            ? (int) filesize($productImagesZipPath)
            : null;
        $productImagesMaxAttachmentBytes = $this->productImagesMaxAttachmentBytes();
        $productImagesDownloadUrl = null;
        $productImagesAttachmentSkipped = false;

        if ($productImagesZipPath !== null && is_file($productImagesZipPath)) {
            if ($productImagesMaxAttachmentBytes > 0 && $productImagesZipSize !== null && $productImagesZipSize <= $productImagesMaxAttachmentBytes) {
                $productImagesAttachmentSkipped = false;
            } else {
                $productImagesAttachmentSkipped = true;
                $productImagesDownloadUrl = $this->accountOrderUrl($store);
                $productImagesZipPath = null;
            }
        }

        $mail = $this
            ->subject($this->customSubject ?: $this->defaultSubject())
            ->view('storefront.mail.orders.status')
            ->with([
                'store' => $store,
                'order' => $this->order,
                'items' => $this->order->items,
                'event' => $this->event,
                'eventLabel' => $this->eventLabel(),
                'mailConfig' => $mailConfig,
                'trackingNumber' => $this->trackingNumber(),
                'trackingUrl' => $this->trackingUrl(),
                'productImagesDownloadUrl' => $productImagesDownloadUrl,
                'productImagesAttachmentSkipped' => $productImagesAttachmentSkipped,
                'productImagesZipSize' => $productImagesZipSize,
                'productImagesZipSizeLabel' => $productImagesZipSize !== null ? $this->formatBytes($productImagesZipSize) : null,
                'productImagesMaxAttachmentSizeLabel' => $this->formatBytes($productImagesMaxAttachmentBytes),
            ]);

        if ($productImagesZipPath !== null && is_file($productImagesZipPath)) {
            $mail->attach($productImagesZipPath, [
                'as' => 'ordine-' . $this->safeOrderNumber() . '-foto-prodotti.zip',
                'mime' => 'application/zip',
            ]);
        }

        return $mail;
    }

    private function defaultSubject(): string
    {
        $storeName = $this->order->store?->name ?? 'Store';

        return match ($this->event) {
            'created' => 'Ordine ricevuto - ' . $storeName,
            'accepted' => 'Ordine accettato - ' . $storeName,
            'shipped' => 'Ordine inviato - ' . $storeName,
            'completed' => 'Ordine completato - ' . $storeName,
            'canceled' => 'Ordine annullato - ' . $storeName,
            'refunded' => 'Ordine rimborsato - ' . $storeName,
            default => 'Aggiornamento ordine - ' . $storeName,
        };
    }

    private function eventLabel(): string
    {
        return match ($this->event) {
            'created' => 'Ordine ricevuto',
            'accepted' => 'Ordine accettato',
            'shipped' => 'Ordine inviato',
            'completed' => 'Ordine completato',
            'canceled' => 'Ordine annullato',
            'refunded' => 'Ordine rimborsato',
            default => 'Aggiornamento ordine',
        };
    }

    private function trackingNumber(): ?string
    {
        $trackingNumber = trim((string) ($this->order->shipping_tracking_number ?? ''));

        if ($trackingNumber !== '') {
            return $trackingNumber;
        }

        if (method_exists($this->order, 'sendcloudTrackingNumber')) {
            return $this->order->sendcloudTrackingNumber();
        }

        return null;
    }

    private function trackingUrl(): ?string
    {
        if (method_exists($this->order, 'sendcloudTrackingUrl')) {
            return $this->order->sendcloudTrackingUrl();
        }

        return null;
    }

    private function productImagesZipPath(): ?string
    {
        if ($this->event !== 'created') {
            return null;
        }

        if (!$this->order->isB2b()) {
            return null;
        }

        try {
            return app(OrderProductImagesZipService::class)->buildForOrder($this->order);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function accountOrderUrl(Store $store): ?string
    {
        try {
            $locale = trim((string) ($store->default_locale ?: app()->getLocale() ?: 'it'));
            $relativeUrl = '/' . trim($locale, '/') . '/account/orders/' . $this->order->getKey();

            return rtrim($this->storeBaseUrl($store), '/') . $relativeUrl;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function productImagesMaxAttachmentBytes(): int
    {
        return max(0, (int) config('mail.storefront.order_product_images.max_attachment_bytes', 7000000));
    }

    private function storeBaseUrl(Store $store): string
    {
        $domain = trim((string) ($store->domain ?: config('app.url')));

        if (!preg_match('#^https?://#i', $domain)) {
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
            $domain = $scheme . '://' . $domain;
        }

        return $domain;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', '.') . ' KB';
        }

        return number_format($bytes, 0, ',', '.') . ' B';
    }

    private function safeOrderNumber(): string
    {
        $orderNumber = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) $this->order->order_number);
        $orderNumber = trim((string) $orderNumber, '-_');

        return $orderNumber !== '' ? $orderNumber : (string) $this->order->id;
    }
}
