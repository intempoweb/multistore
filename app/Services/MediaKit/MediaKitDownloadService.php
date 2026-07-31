<?php

namespace App\Services\MediaKit;

use App\Models\MediaKitRequest;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class MediaKitDownloadService
{
    public function temporaryUrl(MediaKitRequest $request): string
    {
        $request->refresh();

        if (!$request->isDownloadable()) {
            throw new RuntimeException('Archivio MediaKit non disponibile o scaduto.');
        }

        $disk = Storage::disk((string) $request->output_disk);

        if (!$disk->exists((string) $request->output_path)) {
            $request->forceFill([
                'status' => MediaKitRequest::STATUS_DELETED,
                'deleted_at' => now(),
                'delete_reason' => 'missing',
            ])->save();

            throw new RuntimeException('Archivio MediaKit non presente sullo storage.');
        }

        $minutes = max(1, (int) config('mediakit.temporary_url_minutes', 10));
        $deleteDelay = max($minutes + 5, (int) config('mediakit.delete_after_download_minutes', 30));

        $url = $disk->temporaryUrl(
            (string) $request->output_path,
            now()->addMinutes($minutes),
            [
                'ResponseContentDisposition' => 'attachment; filename="' . $request->output_filename . '"',
                'ResponseContentType' => 'application/zip',
            ],
        );

        $request->forceFill([
            'downloaded_at' => $request->downloaded_at ?: now(),
            'download_count' => ((int) $request->download_count) + 1,
            'delete_after' => now()->addMinutes($deleteDelay),
        ])->save();

        return $url;
    }
}
