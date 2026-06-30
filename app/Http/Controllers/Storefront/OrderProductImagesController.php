<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderProductImagesController extends Controller
{
    public function download(Request $request, Order $order, string $file): StreamedResponse
    {
        $store = app('currentStore');
        $customer = auth('customer')->user();

        abort_unless(
            $store instanceof Store
            && $customer instanceof Customer
            && (int) $order->store_id === (int) $store->id
            && (int) $order->customer_id === (int) $customer->id
            && $order->isB2b(),
            404
        );

        $file = basename($file);
        $expires = (int) $request->query('expires', 0);
        $token = (string) $request->query('token', '');

        abort_unless($expires > now()->getTimestamp(), 403);
        abort_unless(hash_equals($this->downloadToken($order, $file, $expires), $token), 403);
        abort_unless($this->isExpectedZipFile($order, $file), 404);

        $archive = $this->productImagesArchive($order, $file);

        abort_if($archive === null, 404);

        $stream = Storage::disk($archive['disk'])->readStream($archive['path']);

        abort_unless(is_resource($stream), 404);

        return response()->streamDownload(
            function () use ($stream): void {
                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            'ordine-' . $this->safeOrderNumber($order) . '-foto-prodotti.zip',
            [
                'Content-Type' => 'application/zip',
                'Content-Length' => (string) $archive['size'],
            ]
        );
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

    private function productImagesArchive(Order $order, string $file): ?array
    {
        $archive = data_get($order->meta ?? [], 'mail.product_images_zip');

        if (!is_array($archive)) {
            return null;
        }

        if (filled(data_get($archive, 'deleted_at'))) {
            return null;
        }

        $disk = trim((string) data_get($archive, 'disk', 's3')) ?: 's3';
        $path = ltrim((string) data_get($archive, 'path', ''), '/');
        $size = (int) data_get($archive, 'size', 0);
        $expiresAt = data_get($archive, 'expires_at');

        if ($path === '' || basename($path) !== $file || $size <= 0) {
            return null;
        }

        if ($expiresAt) {
            try {
                if (Carbon::parse($expiresAt)->isPast()) {
                    return null;
                }
            } catch (\Throwable) {
                return null;
            }
        }

        if (!Storage::disk($disk)->exists($path)) {
            return null;
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'size' => $size,
        ];
    }

    private function safeOrderNumber(Order $order): string
    {
        $orderNumber = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) $order->order_number);
        $orderNumber = trim((string) $orderNumber, '-_');

        return $orderNumber !== '' ? $orderNumber : (string) $order->id;
    }
}
