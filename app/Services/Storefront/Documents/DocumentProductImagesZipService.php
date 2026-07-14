<?php

namespace App\Services\Storefront\Documents;

use App\Models\Erp\DocumentHeader;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class DocumentProductImagesZipService
{
    public function build(DocumentHeader $document): ?string
    {
        $products = collect($document->rows ?? [])
            ->map(fn ($row) => method_exists($row, 'attachedProduct') ? $row->attachedProduct() : null)
            ->filter(fn ($product) => $product instanceof Product)
            ->unique(fn (Product $product) => (int) $product->getKey())
            ->values();

        if ($products->isEmpty()) {
            return null;
        }

        $zipDir = storage_path('app/tmp/document-product-images-zips');

        if (!is_dir($zipDir) && !mkdir($zipDir, 0775, true) && !is_dir($zipDir)) {
            return null;
        }

        $zipPath = $zipDir . '/'
            . $this->safeName('documento-' . (string) $document->NUMREG_CO99)
            . '-immagini-'
            . now()->format('YmdHis')
            . '-'
            . Str::lower(Str::random(8))
            . '.zip';

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $added = 0;
        $temporarySources = [];

        foreach ($products as $product) {
            $assets = $this->orderedAssets($product);

            foreach ($assets as $index => $asset) {
                $absolutePath = $this->resolveAbsolutePath($asset);

                if ($absolutePath === null) {
                    continue;
                }

                $sku = $this->safeName((string) $product->sku);
                $assetFilename = $this->safeFilename((string) ($asset->filename ?: basename($absolutePath)));
                $rolePrefix = $asset->role === MediaAsset::ROLE_MAIN ? 'main' : 'gallery';

                $zipName = $this->uniqueZipName(
                    $zip,
                    $sku . '/'
                        . str_pad((string) $index, 3, '0', STR_PAD_LEFT)
                        . '-'
                        . $rolePrefix
                        . '-'
                        . $assetFilename
                );

                if ($zip->addFile($absolutePath, $zipName)) {
                    $added++;
                }

                if (str_starts_with($absolutePath, storage_path('app/tmp/document-product-images/'))) {
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

        return $zipPath;
    }

    private function orderedAssets(Product $product): array
    {
        $assets = $product->relationLoaded('mediaAssets')
            ? $product->mediaAssets
            : collect();

        return $assets
            ->whereIn('role', [MediaAsset::ROLE_MAIN, MediaAsset::ROLE_GALLERY])
            ->sortBy(fn (MediaAsset $asset) => implode('|', [
                $asset->role === MediaAsset::ROLE_MAIN ? '0' : '1',
                str_pad((string) ((int) $asset->sort_order), 8, '0', STR_PAD_LEFT),
                str_pad((string) ((int) $asset->id), 12, '0', STR_PAD_LEFT),
            ]))
            ->values()
            ->all();
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

        $tmpDir = storage_path('app/tmp/document-product-images');

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
