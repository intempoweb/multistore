<?php

namespace App\Services\Storefront\Orders;

use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class OrderProductImagesZipService
{
    public function buildForOrder(Order $order): ?string
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

        $zipDir = storage_path('app/mail-attachments/orders');

        if (!is_dir($zipDir) && !mkdir($zipDir, 0755, true) && !is_dir($zipDir)) {
            return null;
        }

        $zipPath = $zipDir
            . '/ordine-'
            . $this->safeName((string) $order->order_number)
            . '-prodotti-'
            . now()->format('YmdHis')
            . '-'
            . Str::lower(Str::random(8))
            . '.zip';

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $added = 0;

        foreach ($products as $product) {
            foreach ($product->mediaAssets as $asset) {
                $absolutePath = $this->resolveAbsolutePath($asset);

                if ($absolutePath === null) {
                    continue;
                }

                $sku = $this->safeName((string) $product->sku);
                $filename = $this->safeFilename((string) ($asset->filename ?: basename($absolutePath)));
                $rolePrefix = $asset->role === MediaAsset::ROLE_MAIN ? 'main' : 'gallery';

                $zipName = $this->uniqueZipName(
                    $zip,
                    $sku
                        . '/'
                        . str_pad((string) ((int) $asset->sort_order), 3, '0', STR_PAD_LEFT)
                        . '-'
                        . $rolePrefix
                        . '-'
                        . $filename
                );

                if ($zip->addFile($absolutePath, $zipName)) {
                    $added++;
                }
            }
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);

            return null;
        }

        return $zipPath;
    }

    private function resolveAbsolutePath(MediaAsset $asset): ?string
    {
        $localPath = trim((string) ($asset->local_path ?? ''));

        if ($localPath === '') {
            return null;
        }

        $absolutePath = Storage::disk('public')->path(ltrim($localPath, '/'));

        return is_file($absolutePath) && is_readable($absolutePath)
            ? $absolutePath
            : null;
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