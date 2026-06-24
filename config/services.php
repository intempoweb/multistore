<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'eur'),
    ],

    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'currency' => env('PAYPAL_CURRENCY', 'EUR'),
    ],

    'sendcloud' => [
        'public_key' => env('SENDCLOUD_PUBLIC_KEY'),
        'secret_key' => env('SENDCLOUD_SECRET_KEY'),
        'base_url' => env('SENDCLOUD_BASE_URL', 'https://panel.sendcloud.sc/api/v2'),
        'orders_base_url' => env('SENDCLOUD_ORDERS_BASE_URL', 'https://panel.sendcloud.sc/api/v3'),
        'integration_id' => env('SENDCLOUD_INTEGRATION_ID'),
        'default_carrier' => env('SENDCLOUD_DEFAULT_CARRIER', 'brt'),
        'default_shipment_id' => env('SENDCLOUD_DEFAULT_SHIPMENT_ID'),
    ],

    'instagram' => [
        'access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
        'user_id' => env('INSTAGRAM_USER_ID'),
        'base_url' => env('INSTAGRAM_GRAPH_BASE_URL', 'https://graph.instagram.com'),
        'limit' => env('INSTAGRAM_FEED_LIMIT', 6),
        'cache_ttl' => env('INSTAGRAM_CACHE_TTL', 3600),
        'include_metrics' => env('INSTAGRAM_INCLUDE_METRICS', false),
    ],

];
