<?php

namespace App\Services\MediaKit\Selection;

use App\Models\MediaKitRequest;
use App\Services\MediaKit\MediaKitContext;
use App\Services\MediaKit\MediaKitSelection;

interface SelectionResolver
{
    public function supports(string $sourceType): bool;

    public function resolve(MediaKitRequest $request, MediaKitContext $context): MediaKitSelection;
}
