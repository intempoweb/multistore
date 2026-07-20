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

if (!function_exists('admin_store')) {
    function admin_store(): ?Store
    {
        if (app()->bound('adminStore')) {
            return app('adminStore');
        }

        return current_store();
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
        return MediaUrl::publicUrl($value) ?: MediaUrl::url($value, $minutes);
    }
}

if (!function_exists('media_public_url')) {
    function media_public_url(?string $value): ?string
    {
        return MediaUrl::publicUrl($value);
    }
}

if (!function_exists('b2c_theme_asset_url')) {
    function b2c_theme_asset_url(?string $value): ?string
    {
        $path = media_path($value);

        if (!$path) {
            return null;
        }

        $path = preg_replace('#^(?:public/)?images/themes/b2c/#', '', $path) ?: $path;
        $path = str_starts_with($path, 'storefront/themes/b2c/')
            ? $path
            : 'storefront/themes/b2c/' . ltrim($path, '/');

        return media_public_url($path);
    }
}
