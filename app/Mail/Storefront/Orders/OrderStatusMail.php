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

        $productImagesArchive = $this->productImagesArchive();
        $productImagesZipPath = is_array($productImagesArchive) ? (string) ($productImagesArchive['local_path'] ?? '') : null;
        $productImagesZipSize = is_array($productImagesArchive) ? (int) ($productImagesArchive['size'] ?? 0) : null;
        $productImagesMaxAttachmentBytes = $this->productImagesMaxAttachmentBytes();
        $productImagesDownloadUrl = null;
        $productImagesAttachmentSkipped = false;

        if ($productImagesZipPath !== null && $productImagesZipPath !== '' && is_file($productImagesZipPath)) {
            if ($productImagesMaxAttachmentBytes > 0 && $productImagesZipSize !== null && $productImagesZipSize <= $productImagesMaxAttachmentBytes) {
                $productImagesAttachmentSkipped = false;
            } else {
                $productImagesAttachmentSkipped = true;
                $productImagesDownloadUrl = $this->accountOrderUrl($store);
            }
        }

        $mail = $this
            ->subject($this->customSubject ?: $this->defaultSubject())
            ->view('storefront.mail.orders.status')
            ->with([
                'store' => $store,
                'order' => $this->order,
                'items' => $this->mailItems(),
                'itemsTotalCount' => $this->order->items->count(),
                'itemsDisplayLimit' => $this->itemsDisplayLimit(),
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

        if (!$productImagesAttachmentSkipped && $productImagesZipPath !== null && is_file($productImagesZipPath)) {
            $contents = file_get_contents($productImagesZipPath);

            if ($contents !== false) {
                $mail->attachData($contents, 'ordine-' . $this->safeOrderNumber() . '-foto-prodotti.zip', [
                    'mime' => 'application/zip',
                ]);
            }
        }

        if ($productImagesZipPath !== null && is_file($productImagesZipPath)) {
            @unlink($productImagesZipPath);
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

    private function productImagesArchive(): ?array
    {
        if ($this->event !== 'created') {
            return null;
        }

        if (!$this->order->isB2b()) {
            return null;
        }

        if ($this->order->items->count() > $this->productImagesArchiveItemLimit()) {
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
            $locale = $store->defaultLocale(app()->getLocale() ?: 'it');
            $relativeUrl = '/' . trim($locale, '/') . '/account/orders/' . $this->order->getKey();

            return rtrim($this->storeBaseUrl($store), '/') . $relativeUrl;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function mailItems()
    {
        $limit = $this->itemsDisplayLimit();

        return $limit > 0
            ? $this->order->items->take($limit)
            : $this->order->items;
    }

    private function itemsDisplayLimit(): int
    {
        return (int) config('storefront.checkout.mail_items_display_limit', 80);
    }

    private function productImagesArchiveItemLimit(): int
    {
        return (int) config('storefront.checkout.product_images_archive_item_limit', 120);
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
