<?php

namespace App\Jobs;

use App\Models\MediaKitRequest;
use App\Models\Store;
use App\Services\MediaKit\MediaKitArchiveService;
use App\Services\MediaKit\MediaKitContext;
use App\Services\MediaKit\MediaKitSelectionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GenerateMediaKitArchive implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800;
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $mediaKitRequestId,
    ) {
    }

    public function handle(
        MediaKitSelectionManager $selectionManager,
        MediaKitArchiveService $archiveService,
    ): void {
        $request = MediaKitRequest::query()->findOrFail($this->mediaKitRequestId);

        if (in_array($request->status, [
            MediaKitRequest::STATUS_COMPLETED,
            MediaKitRequest::STATUS_DELETED,
        ], true)) {
            return;
        }

        $store = Store::query()->findOrFail($request->store_id);
        $contextData = (array) (($request->meta ?? [])['context'] ?? []);

        $context = new MediaKitContext(
            store: $store,
            customerId: $request->customer_id ? (int) $request->customer_id : null,
            actorType: $request->actor_type,
            actorId: $request->actor_id ? (int) $request->actor_id : null,
            tipoCf: isset($contextData['tipo_cf']) ? (int) $contextData['tipo_cf'] : null,
            clifor: isset($contextData['clifor']) ? (int) $contextData['clifor'] : null,
            applyCustomerAcl: (bool) ($contextData['apply_customer_acl'] ?? true),
        );

        $request->forceFill([
            'status' => MediaKitRequest::STATUS_PROCESSING,
            'progress' => 5,
            'started_at' => $request->started_at ?: now(),
            'error_message' => null,
        ])->save();

        try {
            $selection = $selectionManager->resolve($request, $context);

            $request->forceFill([
                'progress' => 10,
                'product_count' => $selection->products->count(),
                'meta' => array_merge($request->meta ?? [], [
                    'warnings' => $selection->warnings,
                ]),
            ])->save();

            $archive = $archiveService->buildAndUpload($request, $selection);
            $completedAt = now();

            $request->forceFill([
                'status' => MediaKitRequest::STATUS_COMPLETED,
                'progress' => 100,
                'asset_count' => $archive['asset_count'],
                'output_disk' => $archive['disk'],
                'output_path' => $archive['path'],
                'output_filename' => $archive['filename'],
                'output_size' => $archive['size'],
                'completed_at' => $completedAt,
                'expires_at' => $completedAt->copy()->addDays(
                    max(1, (int) config('mediakit.undownloaded_retention_days', 7))
                ),
            ])->save();
        } catch (Throwable $e) {
            $request->forceFill([
                'status' => MediaKitRequest::STATUS_FAILED,
                'progress' => 0,
                'error_message' => mb_substr($e->getMessage(), 0, 65000),
            ])->save();

            Log::error('MediaKit generation failed.', [
                'media_kit_request_id' => $request->getKey(),
                'uuid' => $request->uuid,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        MediaKitRequest::query()
            ->whereKey($this->mediaKitRequestId)
            ->whereNotIn('status', [
                MediaKitRequest::STATUS_COMPLETED,
                MediaKitRequest::STATUS_DELETED,
            ])
            ->update([
                'status' => MediaKitRequest::STATUS_FAILED,
                'progress' => 0,
                'error_message' => mb_substr($exception->getMessage(), 0, 65000),
                'updated_at' => now(),
            ]);
    }
}
