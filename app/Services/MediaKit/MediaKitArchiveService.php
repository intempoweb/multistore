<?php

namespace App\Services\MediaKit;

use App\Models\MediaAsset;
use App\Models\MediaKitRequest;
use App\Models\Product;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

final class MediaKitArchiveService
{
    /**
     * @return array{disk:string,path:string,filename:string,size:int,asset_count:int}
     */
    public function buildAndUpload(MediaKitRequest $request, MediaKitSelection $selection): array
    {
        if ($selection->products->isEmpty()) {
            throw new RuntimeException('Nessun prodotto disponibile per la generazione del MediaKit.');
        }

        $tmpRoot = storage_path('app/tmp/mediakit/' . $request->uuid);
        $sourceDir = $tmpRoot . '/sources';
        $zipDir = $tmpRoot . '/archives';

        foreach ([$sourceDir, $zipDir] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException("Impossibile creare la cartella temporanea: {$directory}");
            }
        }

        $filename = 'mediakit-' . $this->safePathPart($selection->sourceType)
            . '-' . now()->format('YmdHis')
            . '-' . Str::lower(Str::random(8))
            . '.zip';

        $zipPath = $zipDir . '/' . $filename;
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossibile creare lo ZIP MediaKit.');
        }

        $added = 0;
        $temporaryFiles = [];
        $zipIsOpen = true;
        $seenAssets = [];
        $manifestRows = [];

        try {
            $products = $selection->products->values();
            $productTotal = max(1, $products->count());

            foreach ($products as $productIndex => $product) {
                $assets = $this->orderedAssets($product);

                foreach ($assets as $asset) {
                    if ($added >= max(1, (int) config('mediakit.max_assets', 10000))) {
                        $manifestRows[] = $this->manifestRow($product, $asset, '', 'limite_raggiunto');
                        break 2;
                    }

                    $dedupeKey = $this->dedupeKey($asset);

                    if (isset($seenAssets[$dedupeKey])) {
                        $manifestRows[] = $this->manifestRow($product, $asset, $seenAssets[$dedupeKey], 'duplicato_ignorato');
                        continue;
                    }

                    $resolved = $this->resolveAbsolutePath($asset, $sourceDir);

                    if ($resolved === null) {
                        $manifestRows[] = $this->manifestRow($product, $asset, '', 'file_non_trovato');
                        continue;
                    }

                    [$absolutePath, $temporary] = $resolved;

                    if ($temporary) {
                        $temporaryFiles[] = $absolutePath;
                    }

                    $sku = $this->safePathPart((string) $product->sku);
                    $assetFilename = $this->safeFilename(
                        (string) ($asset->filename ?: basename($absolutePath))
                    );

                    // Mantiene il nome reale: SKU.jpg, SKU_A.jpg, SKU_B.jpg.
                    $zipName = $this->uniqueZipName($zip, $sku . '/' . $assetFilename);

                    if (!$zip->addFile($absolutePath, $zipName)) {
                        $manifestRows[] = $this->manifestRow($product, $asset, $zipName, 'errore_aggiunta_zip');
                        continue;
                    }

                    $seenAssets[$dedupeKey] = $zipName;
                    $manifestRows[] = $this->manifestRow($product, $asset, $zipName, 'aggiunto');
                    $added++;
                }

                $request->forceFill([
                    'progress' => min(90, 10 + (int) floor((($productIndex + 1) / $productTotal) * 80)),
                    'asset_count' => $added,
                ])->save();
            }

            $this->addManifest($zip, $request, $selection, $manifestRows, $added);

            if (!$zip->close()) {
                throw new RuntimeException('Errore durante la chiusura dello ZIP MediaKit.');
            }

            $zipIsOpen = false;
            clearstatcache(true, $zipPath);

            if ($added === 0 || !is_file($zipPath) || (int) filesize($zipPath) <= 0) {
                throw new RuntimeException('Lo ZIP MediaKit non contiene file multimediali validi.');
            }

            $diskName = (string) config('mediakit.archive_disk', 's3');
            $prefix = trim((string) config('mediakit.archive_prefix', 'mediakit'), '/');
            $archivePath = $prefix
                . '/' . (int) $request->store_id
                . '/' . now()->format('Y/m')
                . '/' . $request->uuid
                . '/' . $filename;

            $stream = fopen($zipPath, 'rb');

            if (!is_resource($stream)) {
                throw new RuntimeException('Impossibile leggere lo ZIP MediaKit generato.');
            }

            try {
                $uploaded = Storage::disk($diskName)->put($archivePath, $stream, [
                    'visibility' => 'private',
                ]);
            } finally {
                fclose($stream);
            }

            if (!$uploaded || !Storage::disk($diskName)->exists($archivePath)) {
                throw new RuntimeException('Caricamento dello ZIP MediaKit su storage non riuscito.');
            }

            return [
                'disk' => $diskName,
                'path' => $archivePath,
                'filename' => $filename,
                'size' => (int) filesize($zipPath),
                'asset_count' => $added,
            ];
        } catch (Throwable $e) {
            if (isset($archivePath, $diskName)) {
                Storage::disk($diskName)->delete($archivePath);
            }

            throw $e;
        } finally {
            if ($zipIsOpen) {
                try {
                    $zip->close();
                } catch (Throwable) {
                    // Lo ZIP può essere già non inizializzato o chiuso.
                }
            }

            foreach (array_unique($temporaryFiles) as $file) {
                @unlink($file);
            }

            $this->deleteDirectory($tmpRoot);
        }
    }

    /** @return array{0:string,1:bool}|null */
    private function resolveAbsolutePath(MediaAsset $asset, string $sourceDir): ?array
    {
        $path = MediaUrl::path($asset->local_path);

        if (!$path) {
            return null;
        }

        $diskName = (string) config('mediakit.media_disk', config('filesystems.default', 'public'));
        $disk = Storage::disk($diskName);

        if (!$disk->exists($path)) {
            return null;
        }

        if ($diskName !== 's3') {
            $absolutePath = $disk->path($path);

            return is_file($absolutePath) && is_readable($absolutePath)
                ? [$absolutePath, false]
                : null;
        }

        $tmpPath = $sourceDir . '/' . uniqid('asset_', true) . '_' . basename($path);
        $source = $disk->readStream($path);

        if (!is_resource($source)) {
            return null;
        }

        $target = fopen($tmpPath, 'wb');

        if (!is_resource($target)) {
            fclose($source);
            return null;
        }

        stream_copy_to_stream($source, $target);
        fclose($source);
        fclose($target);

        return is_file($tmpPath) && filesize($tmpPath) > 0
            ? [$tmpPath, true]
            : null;
    }

    private function orderedAssets(Product $product): array
    {
        $assets = $product->relationLoaded('mediaAssets')
            ? $product->mediaAssets
            : $product->mediaAssets()
                ->whereIn('role', config('mediakit.roles', []))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

        return $assets
            ->whereIn('role', config('mediakit.roles', []))
            ->sortBy(fn (MediaAsset $asset) => implode('|', [
                $asset->role === MediaAsset::ROLE_MAIN ? '0' : '1',
                str_pad((string) ((int) $asset->sort_order), 8, '0', STR_PAD_LEFT),
                str_pad((string) ((int) $asset->id), 12, '0', STR_PAD_LEFT),
            ]))
            ->values()
            ->all();
    }

    private function dedupeKey(MediaAsset $asset): string
    {
        $path = (string) (MediaUrl::path($asset->local_path) ?: $asset->local_path ?: '');

        return implode('|', [
            (string) $asset->getKey(),
            (string) config('mediakit.media_disk', config('filesystems.default', 'public')),
            mb_strtolower($path),
        ]);
    }

    /** @return array<int, string> */
    private function manifestRow(Product $product, MediaAsset $asset, string $zipPath, string $status): array
    {
        return [
            (string) $product->sku,
            (string) $product->getKey(),
            (string) ($product->title ?? $product->name ?? ''),
            (string) $asset->getKey(),
            (string) $asset->role,
            (string) ($asset->filename ?: basename((string) $asset->local_path)),
            (string) $asset->local_path,
            $zipPath,
            $status,
        ];
    }

    /** @param array<int, array<int, string>> $manifestRows */
    private function addManifest(
        ZipArchive $zip,
        MediaKitRequest $request,
        MediaKitSelection $selection,
        array $manifestRows,
        int $assetCount
    ): void {
        $rows = [
            ['MEDIAKIT UUID', $request->uuid],
            ['ORIGINE', $selection->sourceType],
            ['RIFERIMENTO', $selection->sourceReference ?? ''],
            ['PRODOTTI', (string) $selection->products->count()],
            ['ASSET AGGIUNTI', (string) $assetCount],
            ['GENERATO IL', now()->toDateTimeString()],
            [],
            ['SKU', 'ID PRODOTTO', 'TITOLO', 'ID ASSET', 'RUOLO', 'NOME REALE', 'PERCORSO SORGENTE', 'PERCORSO ZIP', 'ESITO'],
            ...$manifestRows,
        ];

        if ($selection->warnings !== []) {
            $rows[] = [];
            $rows[] = ['AVVISI'];

            foreach ($selection->warnings as $warning) {
                $rows[] = [(string) $warning];
            }
        }

        $stream = fopen('php://temp', 'w+');

        foreach ($rows as $row) {
            fputcsv($stream, $row, ';');
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        $zip->addFromString('manifest.csv', "\xEF\xBB\xBF" . (string) $contents);
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

    private function safePathPart(string $value): string
    {
        $value = trim((string) preg_replace('/[^A-Za-z0-9._-]+/u', '-', $value), '-. ');

        return $value !== '' ? $value : 'file';
    }

    private function safeFilename(string $value): string
    {
        $value = basename(str_replace('\\', '/', $value));
        $value = trim((string) preg_replace('/[^A-Za-z0-9._-]+/u', '-', $value), '-. ');

        return $value !== '' ? $value : 'file';
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
