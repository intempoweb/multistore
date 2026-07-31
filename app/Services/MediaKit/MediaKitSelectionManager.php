<?php

namespace App\Services\MediaKit;

use App\Models\MediaKitRequest;
use App\Services\MediaKit\Selection\CatalogSelectionResolver;
use App\Services\MediaKit\Selection\DocumentSelectionResolver;
use App\Services\MediaKit\Selection\OrderSelectionResolver;
use App\Services\MediaKit\Selection\SelectionResolver;
use App\Services\MediaKit\Selection\UploadedDdtSelectionResolver;
use InvalidArgumentException;

final class MediaKitSelectionManager
{
    /** @var array<int, SelectionResolver> */
    private array $resolvers;

    public function __construct(
        CatalogSelectionResolver $catalog,
        UploadedDdtSelectionResolver $uploadedDdt,
        DocumentSelectionResolver $document,
        OrderSelectionResolver $order,
    ) {
        $this->resolvers = [$catalog, $uploadedDdt, $document, $order];
    }

    public function resolve(MediaKitRequest $request, MediaKitContext $context): MediaKitSelection
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($request->source_type)) {
                return $resolver->resolve($request, $context);
            }
        }

        throw new InvalidArgumentException("Sorgente MediaKit non supportata: {$request->source_type}");
    }
}
