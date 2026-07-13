<?php

namespace App\Jobs;

use App\Mail\Storefront\Orders\OrderInternalNotificationMail;
use App\Mail\Storefront\Orders\OrderStatusMail;
use App\Models\Order;
use App\Models\Store;
use App\Services\Storefront\Mail\StorefrontMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendStorefrontOrderCreatedEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $orderId)
    {
    }

    public function handle(): void
    {
        $order = Order::query()
            ->with(['store', 'customer', 'items'])
            ->find($this->orderId);

        if (! $order instanceof Order) {
            return;
        }

        $this->sendCustomerOrderCreatedEmail($order);
        $this->sendInternalOrderCreatedEmail($order);
    }

    private function sendCustomerOrderCreatedEmail(Order $order): void
    {
        $to = $this->customerOrderEmail($order);

        if ($to === null) {
            return;
        }

        try {
            Mail::to($to)->send(new OrderStatusMail($order, 'created'));
            $this->storeOrderMailSuccess($order, 'created_customer');
        } catch (Throwable $exception) {
            report($exception);
            $this->storeOrderMailError($order, 'created_customer', $exception->getMessage());
        }
    }

    private function sendInternalOrderCreatedEmail(Order $order): void
    {
        $to = $this->internalOrderEmail($order);

        if ($to === null) {
            return;
        }

        try {
            Mail::to($to)->send(new OrderInternalNotificationMail($order, 'created'));
            $this->storeOrderMailSuccess($order, 'created_internal');
        } catch (Throwable $exception) {
            report($exception);
            $this->storeOrderMailError($order, 'created_internal', $exception->getMessage());
        }
    }

    private function customerOrderEmail(Order $order): ?string
    {
        $email = trim((string) ($order->customer_email ?: $order->shipping_email ?: $order->billing_email));

        return $email !== '' ? $email : null;
    }

    private function internalOrderEmail(Order $order): ?string
    {
        $store = $order->store;

        if (! $store instanceof Store) {
            return null;
        }

        $config = app(StorefrontMailService::class)->configForStore($store);
        $email = trim((string) ($config['to_address'] ?? $config['orders_to_address'] ?? $config['admin_to_address'] ?? ''));

        return $email !== '' ? $email : null;
    }

    private function storeOrderMailSuccess(Order $order, string $operation): void
    {
        try {
            $meta = $this->normalizedMeta($order);

            $meta['mail'] = array_merge($meta['mail'] ?? [], [
                $operation . '_sent_at' => now()->toISOString(),
                $operation . '_error' => null,
                $operation . '_failed_at' => null,
            ]);

            $order->forceFill(['meta' => $meta])->save();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function storeOrderMailError(Order $order, string $operation, string $message): void
    {
        try {
            $meta = $this->normalizedMeta($order);

            $meta['mail'] = array_merge($meta['mail'] ?? [], [
                $operation . '_error' => $message,
                $operation . '_failed_at' => now()->toISOString(),
            ]);

            $order->forceFill(['meta' => $meta])->save();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function normalizedMeta(Order $order): array
    {
        $meta = $order->meta ?? [];

        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        return is_array($meta) ? $meta : [];
    }
}
