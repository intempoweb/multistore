<?php

namespace App\Services\MediaKit;

use Illuminate\Support\Collection;

final readonly class MediaKitSelection
{
    /**
     * @param Collection<int, \App\Models\Product> $products
     * @param array<int, string> $warnings
     */
    public function __construct(
        public Collection $products,
        public string $sourceType,
        public ?string $sourceReference = null,
        public array $warnings = [],
    ) {
    }
}
