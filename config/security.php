<?php

return [
    'headers' => [
        'enabled' => env('SECURITY_HEADERS_ENABLED', true),
        'hsts' => env('SECURITY_HSTS_ENABLED', true),
        'csp' => env('SECURITY_CSP_ENABLED', true),
        'csp_report_only' => env('SECURITY_CSP_REPORT_ONLY', false),
    ],

    'content_security_policy' => implode('; ', [
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "form-action 'self' https://www.paypal.com https://www.sandbox.paypal.com",
        "img-src 'self' data: blob: https:",
        "font-src 'self' data: https:",
        "style-src 'self' 'unsafe-inline' https:",
        "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com https://js.stripe.com https://www.paypal.com https://www.google.com https://www.gstatic.com https://maps.googleapis.com",
        "frame-src 'self' https://js.stripe.com https://hooks.stripe.com https://www.paypal.com https://www.sandbox.paypal.com https://www.google.com",
        "connect-src 'self' https:",
        "media-src 'self' https:",
        "worker-src 'self' blob:",
        "upgrade-insecure-requests",
    ]),
];
