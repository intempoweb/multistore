<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUrl
{
    private static array $signedUrls = [];
    private static array $publicUrls = [];

    public static function path(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            $path = parse_url($value, PHP_URL_PATH);

            $value = is_string($path) && trim($path) !== ''
                ? $path
                : $value;
        }

        $value = preg_replace('#^/storage/#', '', $value) ?: $value;
        $value = ltrim($value, '/');

        return $value !== '' ? $value : null;
    }

    public static function url(?string $value, int $minutes = 60): ?string
    {
        $path = self::path($value);

        if (!$path) {
            return null;
        }

        if (Str::startsWith($value ?? '', ['http://', 'https://']) && parse_url($value, PHP_URL_QUERY)) {
            return $value;
        }

        return self::publicUrl($path);
    }

    public static function publicUrl(?string $value): ?string
    {
        $path = self::path($value);

        if (!$path) {
            return null;
        }

        if (!isset(self::$publicUrls[$path])) {
            self::$publicUrls[$path] = Storage::disk('s3')->url($path);
        }

        return self::$publicUrls[$path];
    }
}