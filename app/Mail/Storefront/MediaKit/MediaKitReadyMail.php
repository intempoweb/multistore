<?php

namespace App\Mail\Storefront\MediaKit;

use App\Models\MediaKitRequest;
use App\Models\Store;
use App\Services\Storefront\Mail\StorefrontMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class MediaKitReadyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Store $store,
        public MediaKitRequest $mediaKitRequest,
        public ?string $downloadUrl,
        public bool $attachArchive,
    ) {
    }

    public function build(): self
    {
        $mailService = app(StorefrontMailService::class);
        $mailService->applyStoreSender($this, $this->store);

        $mail = $this
            ->subject('MediaKit prodotti - ' . ($this->store->name ?? 'Store'))
            ->view('storefront.mail.mediakit.ready')
            ->with([
                'store' => $this->store,
                'mailConfig' => $mailService->configForStore($this->store),
                'mediaKitRequest' => $this->mediaKitRequest,
                'downloadUrl' => $this->downloadUrl,
                'attachArchive' => $this->attachArchive,
            ]);

        if ($this->attachArchive) {
            $mail->attachFromStorageDisk(
                (string) $this->mediaKitRequest->output_disk,
                (string) $this->mediaKitRequest->output_path,
                (string) $this->mediaKitRequest->output_filename,
                ['mime' => 'application/zip']
            );
        }

        return $mail;
    }
}
