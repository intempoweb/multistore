<?php

namespace App\Services\MediaKit;

use App\Jobs\GenerateMediaKitArchive;
use App\Models\MediaKitRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MediaKitRequestService
{
    private const SOURCES = [
        MediaKitRequest::SOURCE_CATALOG,
        MediaKitRequest::SOURCE_UPLOADED_DDT,
        MediaKitRequest::SOURCE_DOCUMENT,
        MediaKitRequest::SOURCE_ORDER,
    ];

    /**
     * @param array<string, mixed> $data
     */
    public function create(MediaKitContext $context, array $data): MediaKitRequest
    {
        $sourceType = (string) Arr::get($data, 'source_type');

        if (!in_array($sourceType, self::SOURCES, true)) {
            throw new InvalidArgumentException('Tipo sorgente MediaKit non valido.');
        }

        $request = DB::transaction(function () use ($context, $data, $sourceType): MediaKitRequest {
            return MediaKitRequest::query()->create([
                'store_id' => $context->store->getKey(),
                'customer_id' => $context->customerId,
                'actor_type' => $context->actorType,
                'actor_id' => $context->actorId,
                'source_type' => $sourceType,
                'source_reference' => Arr::get($data, 'source_reference'),
                'status' => MediaKitRequest::STATUS_QUEUED,
                'progress' => 0,
                'input_disk' => Arr::get($data, 'input_disk'),
                'input_path' => Arr::get($data, 'input_path'),
                'meta' => array_merge((array) Arr::get($data, 'meta', []), [
                    'context' => [
                        'tipo_cf' => $context->tipoCf,
                        'clifor' => $context->clifor,
                        'apply_customer_acl' => $context->applyCustomerAcl,
                    ],
                ]),
            ]);
        });

        GenerateMediaKitArchive::dispatch($request->getKey())
            ->onQueue((string) config('mediakit.queue', 'default'));

        return $request->fresh();
    }
}
