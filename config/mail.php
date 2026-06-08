<?php

return [

    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    'storefront' => [
        'default' => [
            'from_address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'from_name' => env('MAIL_FROM_NAME', 'Example'),
            'logo' => env('MAIL_LOGO'),
            'contacts' => env('MAIL_CONTACTS'),
            'info' => env('MAIL_INFO'),
            'to_address' => env('MAIL_TO_ADDRESS'),
        ],

        'stores' => [
            'intempodistribution' => [
                'from_address' => env('MAIL_INTEMPO_FROM_ADDRESS', 'no-reply@emails.intempodistribution.it'),
                'from_name' => env('MAIL_INTEMPO_FROM_NAME', 'B2B INTEMPO'),
                'logo' => env('MAIL_INTEMPO_LOGO'),
                'contacts' => env('MAIL_INTEMPO_CONTACTS'),
                'info' => env('MAIL_INTEMPO_INFO'),
                'to_address' => env('MAIL_TO_INTEMPO_ADDRESS'),
            ],

            'fipell' => [
                'from_address' => env('MAIL_FIPELL_FROM_ADDRESS', 'no-reply@emails.fipell.it'),
                'from_name' => env('MAIL_FIPELL_FROM_NAME', 'FIPELL'),
                'logo' => env('MAIL_FIPELL_LOGO'),
                'contacts' => env('MAIL_FIPELL_CONTACTS'),
                'info' => env('MAIL_FIPELL_INFO'),
                'to_address' => env('MAIL_TO_FIPELL_ADDRESS'),
            ],

            'teknikoshop' => [
                'from_address' => env('MAIL_TEKNIKO_FROM_ADDRESS', 'no-reply@emails.teknikoshop.it'),
                'from_name' => env('MAIL_TEKNIKO_FROM_NAME', 'TEKNIKO SHOP'),
                'logo' => env('MAIL_TEKNIKO_LOGO'),
                'contacts' => env('MAIL_TEKNIKO_CONTACTS'),
                'info' => env('MAIL_TEKNIKO_INFO'),
                'to_address' => env('MAIL_TO_TEKNIKO_ADDRESS'),
            ],

            'ciak' => [
                'from_address' => env('MAIL_CIAK_FROM_ADDRESS', 'no-reply@emails.ciak.fi.it'),
                'from_name' => env('MAIL_CIAK_FROM_NAME', 'CIAK'),
                'logo' => env('MAIL_CIAK_LOGO'),
                'contacts' => env('MAIL_CIAK_CONTACTS'),
                'info' => env('MAIL_CIAK_INFO'),
                'to_address' => env('MAIL_TO_CIAK_ADDRESS'),
            ],
        ],
    ],

];