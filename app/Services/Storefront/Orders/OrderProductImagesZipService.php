<?php

namespace App\Services\Storefront\Orders;

use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\Product;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class OrderProductImagesZipService
{
    /**
     * @return array{
     *     disk:string,
     *     path:string,
     *     filename:string,
     *     size:int,
     *     created_at:string,
     *     expires_at:string
     * }|null
     */
    public function importLatestLegacyLocalArchive(Order $order): ?array
    {
        if (!$order->isB2b()) {
            return null;
        }

        $legacyPath = $this->latestLegacyLocalArchivePath($order);

        if ($legacyPath === null) {
            return null;
        }

        $filename = basename($legacyPath);
        $size = (int) filesize($legacyPath);
        $createdAt = now();
        $expiresAt = $createdAt->copy()->addDays($this->retentionDays());
        $archivePath = $this->archivePath($order, $filename);

        if (!$this->uploadArchive($legacyPath, $archivePath)) {
            return null;
        }

        $archive = [
            'disk' => $this->archiveDiskName(),
            'path' => $archivePath,
            'filename' => $filename,
            'size' => $size,
            'created_at' => $createdAt->toISOString(),
            'expires_at' => $expiresAt->toISOString(),
        ];

        $this->storeArchiveMeta($order, $archive);
        @unlink($legacyPath);

        return $archive;
    }

    /**
     * @return array{
     *     local_path:string,
     *     disk:string,
     *     path:string,
     *     filename:string,
     *     size:int,
     *     created_at:string,
     *     expires_at:string
     * }|null
     */
    public function buildForOrder(Order $order): ?array
    {
        if (!$order->isB2b()) {
            return null;
        }

        $order->loadMissing('items');

        $skus = $order->items
            ->pluck('sku')
            ->filter()
            ->map(fn ($sku) => trim((string) $sku))
            ->filter(fn (string $sku) => $sku !== '' && !str_starts_with(mb_strtoupper($sku), 'MTBUONO'))
            ->unique()
            ->values();

        if ($skus->isEmpty()) {
            return null;
        }

        $products = Product::query()
            ->with(['mediaAssets' => function ($query) {
                $query
                    ->whereIn('role', [MediaAsset::ROLE_MAIN, MediaAsset::ROLE_GALLERY])
                    ->orderBy('sort_order')
                    ->orderBy('id');
            }])
            ->where('ditta_cg18', (int) $order->ditta_cg18)
            ->where('site_type', (int) $order->site_type)
            ->whereIn('sku', $skus->all())
            ->get();

        if ($products->isEmpty()) {
            return null;
        }

        $zipDir = storage_path('app/tmp/order-product-images-zips');

        if (!is_dir($zipDir) && !mkdir($zipDir, 0755, true) && !is_dir($zipDir)) {
            return null;
        }

        $zipFilename = 'ordine-'
            . $this->safeName((string) $order->order_number)
            . '-prodotti-'
            . now()->format('YmdHis')
            . '-'
            . Str::lower(Str::random(8))
            . '.zip';

        $zipPath = $zipDir . '/' . $zipFilename;

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $added = 0;
        $temporarySources = [];

        foreach ($products as $product) {
            foreach ($product->mediaAssets as $asset) {
                $absolutePath = $this->resolveAbsolutePath($asset);

                if ($absolutePath === null) {
                    continue;
                }

                $sku = $this->safeName((string) $product->sku);
                $assetFilename = $this->safeFilename((string) ($asset->filename ?: basename($absolutePath)));
                $rolePrefix = $asset->role === MediaAsset::ROLE_MAIN ? 'main' : 'gallery';

                $zipName = $this->uniqueZipName(
                    $zip,
                    $sku
                        . '/'
                        . str_pad((string) ((int) $asset->sort_order), 3, '0', STR_PAD_LEFT)
                        . '-'
                        . $rolePrefix
                        . '-'
                        . $assetFilename
                );

                if ($zip->addFile($absolutePath, $zipName)) {
                    $added++;
                }

                if (str_starts_with($absolutePath, storage_path('app/tmp/order-product-images/'))) {
                    $temporarySources[] = $absolutePath;
                }
            }
        }

        $zip->close();

        foreach (array_unique($temporarySources) as $temporarySource) {
            @unlink($temporarySource);
        }

        if ($added === 0) {
            @unlink($zipPath);

            return null;
        }

        $size = (int) filesize($zipPath);
        $createdAt = now();
        $expiresAt = $createdAt->copy()->addDays($this->retentionDays());
        $archivePath = $this->archivePath($order, $zipFilename);

        if (!$this->uploadArchive($zipPath, $archivePath)) {
            @unlink($zipPath);

            return null;
        }

        $archive = [
            'local_path' => $zipPath,
            'disk' => $this->archiveDiskName(),
            'path' => $archivePath,
            'filename' => $zipFilename,
            'size' => $size,
            'created_at' => $createdAt->toISOString(),
            'expires_at' => $expiresAt->toISOString(),
        ];

        $this->storeArchiveMeta($order, $archive);

        return $archive;
    }

    private function resolveAbsolutePath(MediaAsset $asset): ?string
    {
        $path = MediaUrl::path($asset->local_path);

        if (!$path) {
            return null;
        }
        $diskName = env('MEDIA_SYNC_DISK', config('filesystems.default', 'public'));
        $disk = Storage::disk($diskName);

        if (!$disk->exists($path)) {
            return null;
        }

        if ($diskName !== 's3') {
            $absolutePath = $disk->path($path);

            return is_file($absolutePath) && is_readable($absolutePath)
                ? $absolutePath
                : null;
        }

        $tmpDir = storage_path('app/tmp/order-product-images');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $tmpPath = $tmpDir . '/' . uniqid('img_', true) . '_' . basename($path);
        $contents = $disk->get($path);

        if ($contents === false || file_put_contents($tmpPath, $contents) === false) {
            return null;
        }

        return $tmpPath;
    }

    private function latestLegacyLocalArchivePath(Order $order): ?string
    {
        $pattern = storage_path('app/mail-attachments/orders/ordine-' . $this->safeName((string) $order->order_number) . '-prodotti-*.zip');
        $files = collect(glob($pattern) ?: [])
            ->filter(fn (string $path): bool => is_file($path) && is_readable($path))
            ->sortByDesc(fn (string $path): int => (int) filemtime($path))
            ->values();

        return $files->first();
    }


    private function uploadArchive(string $zipPath, string $archivePath): bool
    {
        $stream = @fopen($zipPath, 'rb');

        if (!is_resource($stream)) {
            return false;
        }

        try {
            return (bool) Storage::disk($this->archiveDiskName())->put($archivePath, $stream);
        } finally {
            @fclose($stream);
        }
    }

    private function archivePath(Order $order, string $filename): string
    {
        return trim($this->archivePrefix(), '/')
            . '/orders/'
            . (int) $order->store_id
            . '/'
            . (int) $order->getKey()
            . '/'
            . $filename;
    }

    private function archiveDiskName(): string
    {
        return trim((string) config('mail.storefront.order_product_images.disk', 's3')) ?: 's3';
    }

    private function archivePrefix(): string
    {
        return trim((string) config('mail.storefront.order_product_images.prefix', 'order-product-images')) ?: 'order-product-images';
    }

    private function retentionDays(): int
    {
        return max(1, (int) config('mail.storefront.order_product_images.retention_days', 7));
    }

    /**
     * @param array<string, mixed> $archive
     */
    private function storeArchiveMeta(Order $order, array $archive): void
    {
        $meta = $order->meta ?? [];

        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        $meta = is_array($meta) ? $meta : [];
        $meta['mail'] = is_array($meta['mail'] ?? null) ? $meta['mail'] : [];
        $meta['mail']['product_images_zip'] = [
            'disk' => (string) $archive['disk'],
            'path' => (string) $archive['path'],
            'filename' => (string) $archive['filename'],
            'size' => (int) $archive['size'],
            'created_at' => (string) $archive['created_at'],
            'expires_at' => (string) $archive['expires_at'],
            'deleted_at' => null,
        ];

        $order->forceFill(['meta' => $meta])->save();
        $order->setAttribute('meta', $meta);
    }

    private function uniqueZipName(ZipArchive $zip, string $zipName): string
    {
        if ($zip->locateName($zipName) === false) {
            return $zipName;
        }

        $directory = pathinfo($zipName, PATHINFO_DIRNAME);
        $extension = pathinfo($zipName, PATHINFO_EXTENSION);
        $basename = pathinfo($zipName, PATHINFO_FILENAME);

        $directory = $directory !== '.' ? $directory . '/' : '';
        $suffix = 2;

        do {
            $candidate = $directory . $basename . '-' . $suffix;

            if ($extension !== '') {
                $candidate .= '.' . $extension;
            }

            $suffix++;
        } while ($zip->locateName($candidate) !== false);

        return $candidate;
    }

    private function safeName(string $value): string
    {
        $value = Str::slug($value, '-');

        return $value !== '' ? $value : 'file';
    }

    private function safeFilename(string $value): string
    {
        $extension = pathinfo($value, PATHINFO_EXTENSION);
        $basename = pathinfo($value, PATHINFO_FILENAME);

        $safeBasename = $this->safeName($basename);
        $safeExtension = Str::slug($extension, '');

        return $safeExtension !== ''
            ? $safeBasename . '.' . $safeExtension
            : $safeBasename;
    }
}
