<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Services\Storefront\Orders\OrderProductImagesZipService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderProductImagesController extends Controller
{
    public function download(Order $order, OrderProductImagesZipService $zipService): BinaryFileResponse
    {
        $store = app('currentStore');

        abort_unless(
            $store instanceof Store
            && (int) $order->store_id === (int) $store->id
            && $order->isB2b(),
            404
        );

        $zipPath = $zipService->buildForOrder($order);

        abort_if($zipPath === null || !is_file($zipPath), 404);

        return response()
            ->download($zipPath, 'ordine-' . $this->safeOrderNumber($order) . '-foto-prodotti.zip', [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);
    }

    private function safeOrderNumber(Order $order): string
    {
        $orderNumber = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) $order->order_number);
        $orderNumber = trim((string) $orderNumber, '-_');

        return $orderNumber !== '' ? $orderNumber : (string) $order->id;
    }
}
