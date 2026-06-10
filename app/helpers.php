<?php

use App\Models\Store;
use App\Support\MediaUrl;

if (!function_exists('current_store')) {
    function current_store(): ?Store
    {
        return app()->bound('currentStore')
            ? app('currentStore')
            : null;
    }
}

if (!function_exists('media_path')) {
    function media_path(?string $value): ?string
    {
        return MediaUrl::path($value);
    }
}

if (!function_exists('media_url')) {
    function media_url(?string $value, int $minutes = 60): ?string
    {
        return MediaUrl::url($value, $minutes);
    }
}

if (!function_exists('media_public_url')) {
    function media_public_url(?string $value): ?string
    {
        return MediaUrl::publicUrl($value);
    }
}