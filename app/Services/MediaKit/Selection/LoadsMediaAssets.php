<?php

namespace App\Services\MediaKit\Selection;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Builder;

trait LoadsMediaAssets
{
    protected function withMediaAssets(Builder $query): Builder
    {
        return $query->with(['mediaAssets' => function ($media): void {
            $media
                ->whereIn('role', config('mediakit.roles', [
                    MediaAsset::ROLE_MAIN,
                    MediaAsset::ROLE_GALLERY,
                ]))
                ->orderBy('sort_order')
                ->orderBy('id');
        }]);
    }
}
