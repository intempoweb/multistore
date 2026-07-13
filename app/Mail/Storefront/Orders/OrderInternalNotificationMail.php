<?php

namespace App\Mail\Storefront\Orders;

use App\Models\Order;
use App\Models\Store;
use App\Services\Storefront\Mail\StorefrontMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class OrderInternalNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $event = 'created',
    ) {
    }

    public function build(): self
    {
        $this->order->loadMissing(['store', 'customer', 'items']);

        $store = $this->order->store;
        $mailService = app(StorefrontMailService::class);
        $mailConfig = $store instanceof Store ? $mailService->configForStore($store) : [];

        if ($store instanceof Store) {
            $mailService->applyStoreSender($this, $store);
        }

        return $this
            ->subject($this->subjectLine())
            ->view('storefront.mail.orders.internal')
            ->with([
                'store' => $store,
                'order' => $this->order,
                'items' => $this->mailItems(),
                'itemsTotalCount' => $this->order->items->count(),
                'itemsDisplayLimit' => $this->itemsDisplayLimit(),
                'event' => $this->event,
                'eventLabel' => $this->eventLabel(),
                'mailConfig' => $mailConfig,
                'currencyDecimals' => $this->order->priceDecimals(),
                'adminOrderUrl' => $this->adminOrderUrl(),
                'isB2b' => $this->order->isB2b(),
                'isB2c' => $this->order->isB2c(),
                'invoiceRequired' => (bool) $this->order->invoice_required,
            ]);
    }

    private function subjectLine(): string
    {
        $storeName = $this->order->store?->name ?? 'Store';
        $orderNumber = $this->order->order_number ?: $this->order->id;

        return match ($this->event) {
            'created' => '[NUOVO ORDINE] #' . $orderNumber . ' - ' . $storeName,
            'accepted' => '[ORDINE ACCETTATO] #' . $orderNumber,
            'shipped' => '[ORDINE SPEDITO] #' . $orderNumber,
            'completed' => '[ORDINE COMPLETATO] #' . $orderNumber,
            'canceled' => '[ORDINE ANNULLATO] #' . $orderNumber,
            'closed' => '[ORDINE CHIUSO] #' . $orderNumber,
            'refunded' => '[ORDINE RIMBORSATO] #' . $orderNumber,
            default => '[ORDINE] #' . $orderNumber,
        };
    }

    private function eventLabel(): string
    {
        return match ($this->event) {
            'created' => 'Nuovo ordine',
            'accepted' => 'Ordine accettato',
            'shipped' => 'Ordine spedito',
            'completed' => 'Ordine completato',
            'canceled' => 'Ordine annullato',
            'closed' => 'Ordine chiuso',
            'refunded' => 'Ordine rimborsato',
            default => 'Aggiornamento ordine',
        };
    }

    private function adminOrderUrl(): ?string
    {
        $baseUrl = rtrim((string) config('app.admin_url', 'http://intempodistribution.test'), '/');

        return $baseUrl . '/admin/orders/' . $this->order->getKey();
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
}
