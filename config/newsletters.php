<?php

return [
    'default_provider' => env('NEWSLETTER_PROVIDER', 'mailchimp'),

    'timeout' => (int) env('NEWSLETTER_HTTP_TIMEOUT', 20),
    'retry_times' => (int) env('NEWSLETTER_HTTP_RETRY_TIMES', 2),
    'retry_sleep_ms' => (int) env('NEWSLETTER_HTTP_RETRY_SLEEP_MS', 250),

    'default_price_mode' => env('NEWSLETTER_PRICE_MODE', 'listino'),
    'fallback_to_public_price' => env('NEWSLETTER_FALLBACK_TO_PUBLIC_PRICE', true),

    'providers' => [
        'mailchimp' => [
            'api_key' => env('MAILCHIMP_API_KEY'),
            'server_prefix' => env('MAILCHIMP_SERVER_PREFIX'),
            'audience_id' => env('MAILCHIMP_AUDIENCE_ID'),
            'from_name' => env('MAILCHIMP_FROM_NAME', env('MAIL_FROM_NAME')),
            'reply_to' => env('MAILCHIMP_REPLY_TO', env('MAIL_FROM_ADDRESS')),
        ],

        'brevo' => [
            'api_key' => env('BREVO_API_KEY'),
            'list_ids' => array_filter(array_map('intval', explode(',', (string) env('BREVO_LIST_IDS', '')))),
            'sender_id' => env('BREVO_SENDER_ID'),
            'sender_email' => env('BREVO_SENDER_EMAIL', env('MAIL_FROM_ADDRESS')),
            'sender_name' => env('BREVO_SENDER_NAME', env('MAIL_FROM_NAME')),
            'reply_to' => env('BREVO_REPLY_TO', env('MAIL_FROM_ADDRESS')),
        ],
    ],

    'contexts' => [
        '1:1' => [
            'provider' => env('NEWSLETTER_1_1_PROVIDER', env('NEWSLETTER_PROVIDER', 'mailchimp')),
            'listino_id' => env('NEWSLETTER_1_1_LISTINO_ID', 31),
            'mailchimp' => [
                'audience_id' => env('MAILCHIMP_1_1_AUDIENCE_ID', env('MAILCHIMP_AUDIENCE_ID')),
                'from_name' => env('MAILCHIMP_1_1_FROM_NAME', env('MAILCHIMP_FROM_NAME', env('MAIL_FROM_NAME'))),
                'reply_to' => env('MAILCHIMP_1_1_REPLY_TO', env('MAILCHIMP_REPLY_TO', env('MAIL_FROM_ADDRESS'))),
            ],
            'brevo' => [
                'list_ids' => array_filter(array_map('intval', explode(',', (string) env('BREVO_1_1_LIST_IDS', env('BREVO_LIST_IDS', ''))))),
                'sender_id' => env('BREVO_1_1_SENDER_ID', env('BREVO_SENDER_ID')),
                'sender_email' => env('BREVO_1_1_SENDER_EMAIL', env('BREVO_SENDER_EMAIL', env('MAIL_FROM_ADDRESS'))),
                'sender_name' => env('BREVO_1_1_SENDER_NAME', env('BREVO_SENDER_NAME', env('MAIL_FROM_NAME'))),
                'reply_to' => env('BREVO_1_1_REPLY_TO', env('BREVO_REPLY_TO', env('MAIL_FROM_ADDRESS'))),
            ],
        ],

        '3:1' => [
            'provider' => env('NEWSLETTER_3_1_PROVIDER', env('NEWSLETTER_PROVIDER', 'mailchimp')),
            'listino_id' => env('NEWSLETTER_3_1_LISTINO_ID', 1),
            'mailchimp' => [
                'audience_id' => env('MAILCHIMP_3_1_AUDIENCE_ID', env('MAILCHIMP_AUDIENCE_ID')),
                'from_name' => env('MAILCHIMP_3_1_FROM_NAME', env('MAILCHIMP_FROM_NAME', env('MAIL_FROM_NAME'))),
                'reply_to' => env('MAILCHIMP_3_1_REPLY_TO', env('MAILCHIMP_REPLY_TO', env('MAIL_FROM_ADDRESS'))),
            ],
            'brevo' => [
                'list_ids' => array_filter(array_map('intval', explode(',', (string) env('BREVO_3_1_LIST_IDS', env('BREVO_LIST_IDS', ''))))),
                'sender_id' => env('BREVO_3_1_SENDER_ID', env('BREVO_SENDER_ID')),
                'sender_email' => env('BREVO_3_1_SENDER_EMAIL', env('BREVO_SENDER_EMAIL', env('MAIL_FROM_ADDRESS'))),
                'sender_name' => env('BREVO_3_1_SENDER_NAME', env('BREVO_SENDER_NAME', env('MAIL_FROM_NAME'))),
                'reply_to' => env('BREVO_3_1_REPLY_TO', env('BREVO_REPLY_TO', env('MAIL_FROM_ADDRESS'))),
            ],
        ],
    ],
];
