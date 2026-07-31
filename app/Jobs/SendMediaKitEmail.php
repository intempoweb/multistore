<?php

namespace App\Jobs;

use App\Mail\Storefront\MediaKit\MediaKitReadyMail;
use App\Models\MediaKitRequest;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class SendMediaKitEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $mediaKitRequestId,
        public readonly string $recipient,
    ) {
    }

    public function handle(): void
    {
        $request = MediaKitRequest::query()->findOrFail($this->mediaKitRequestId);
        $store = Store::query()->findOrFail($request->store_id);

        if (!$request->isDownloadable()) {
            throw new RuntimeException('Archivio MediaKit non disponibile o scaduto.');
        }

        $disk = Storage::disk((string) $request->output_disk);

        if (!$disk->exists((string) $request->output_path)) {
            throw new RuntimeException('Archivio MediaKit non presente sullo storage.');
        }

        $request->forceFill([
            'email_status' => MediaKitRequest::EMAIL_SENDING,
            'email_to' => $this->recipient,
            'email_attempts' => ((int) $request->email_attempts) + 1,
            'email_error_message' => null,
        ])->save();

        $maxAttachmentBytes = max(0, (int) config('mediakit.email.max_attachment_bytes', 7000000));
        $attachArchive = $maxAttachmentBytes > 0 && (int) $request->output_size <= $maxAttachmentBytes;
        $downloadUrl = null;

        if (!$attachArchive) {
            $ttl = max(10, (int) config('mediakit.email.download_url_ttl_minutes', 10080));
            $downloadUrl = $disk->temporaryUrl(
                (string) $request->output_path,
                now()->addMinutes($ttl),
                [
                    'ResponseContentDisposition' => 'attachment; filename="' . $request->output_filename . '"',
                    'ResponseContentType' => 'application/zip',
                ]
            );
        }

        Mail::to($this->recipient)->send(new MediaKitReadyMail(
            store: $store,
            mediaKitRequest: $request,
            downloadUrl: $downloadUrl,
            attachArchive: $attachArchive,
        ));

        $request->forceFill([
            'email_status' => MediaKitRequest::EMAIL_SENT,
            'email_sent_at' => now(),
            'email_error_message' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        MediaKitRequest::query()
            ->whereKey($this->mediaKitRequestId)
            ->update([
                'email_status' => MediaKitRequest::EMAIL_FAILED,
                'email_error_message' => mb_substr($exception->getMessage(), 0, 65000),
                'updated_at' => now(),
            ]);
    }
}
