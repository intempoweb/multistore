<?php

return [
    'archive_disk' => env('MEDIAKIT_ARCHIVE_DISK', 's3'),
    'archive_prefix' => env('MEDIAKIT_ARCHIVE_PREFIX', 'mediakit'),
    'media_disk' => env('MEDIA_SYNC_DISK', env('FILESYSTEM_DISK', 'public')),

    'temporary_url_minutes' => (int) env('MEDIAKIT_DOWNLOAD_URL_MINUTES', 10),
    'delete_after_download_minutes' => (int) env('MEDIAKIT_DELETE_AFTER_DOWNLOAD_MINUTES', 30),
    'undownloaded_retention_days' => (int) env('MEDIAKIT_UNDOWNLOADED_RETENTION_DAYS', 7),

    'queue' => env('MEDIAKIT_QUEUE', 'default'),

    'email' => [
        'max_attachment_bytes' => (int) env('MAIL_MEDIAKIT_MAX_ATTACHMENT_BYTES', 7000000),
        'download_url_ttl_minutes' => (int) env('MAIL_MEDIAKIT_DOWNLOAD_TTL_MINUTES', 10080),
    ],
    'max_products' => (int) env('MEDIAKIT_MAX_PRODUCTS', 1000),
    'max_assets' => (int) env('MEDIAKIT_MAX_ASSETS', 10000),

    'roles' => [
        \App\Models\MediaAsset::ROLE_MAIN,
        \App\Models\MediaAsset::ROLE_GALLERY,
    ],
];
