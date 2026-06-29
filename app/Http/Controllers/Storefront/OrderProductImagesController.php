<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderProductImagesController extends Controller
{
    public function download(Request $request, Order $order, string $file): BinaryFileResponse
    {
        $store = app('currentStore');

        abort_unless(
            $store instanceof Store
            && (int) $order->store_id === (int) $store->id
            && $order->isB2b(),
            404
        );

        $file = basename($file);
        $expires = (int) $request->query('expires', 0);
        $token = (string) $request->query('token', '');

        abort_unless($expires > now()->getTimestamp(), 403);
        abort_unless(hash_equals($this->downloadToken($order, $file, $expires), $token), 403);
        abort_unless($this->isExpectedZipFile($order, $file), 404);

        $zipPath = storage_path('app/mail-attachments/orders/' . $file);

        abort_if(!is_file($zipPath) || !is_readable($zipPath), 404);

        return response()
            ->download($zipPath, 'ordine-' . $this->safeOrderNumber($order) . '-foto-prodotti.zip', [
                'Content-Type' => 'application/zip',
            ]);
    }

    private function downloadToken(Order $order, string $file, int $expires): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [
                (string) $order->order_number,
                $file,
                (string) $expires,
            ]),
            (string) config('app.key')
        );
    }

    private function isExpectedZipFile(Order $order, string $file): bool
    {
        $expectedPrefix = 'ordine-' . $this->safeOrderNumber($order) . '-prodotti-';

        return str_starts_with($file, $expectedPrefix)
            && str_ends_with($file, '.zip')
            && $file === basename($file);
    }

    private function safeOrderNumber(Order $order): string
    {
        $orderNumber = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) $order->order_number);
        $orderNumber = trim((string) $orderNumber, '-_');

        return $orderNumber !== '' ? $orderNumber : (string) $order->id;
    }
}
