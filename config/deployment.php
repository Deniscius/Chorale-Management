<?php

return [
    'demo' => env('APP_DEMO_MODE', false),
    'bootstrap' => [
        'email' => env('PLATFORM_BOOTSTRAP_EMAIL'),
        'password' => env('PLATFORM_BOOTSTRAP_PASSWORD'),
        'totp_secret' => env('PLATFORM_BOOTSTRAP_TOTP_SECRET'),
    ],
    // Enable only behind a proxy that replaces incoming forwarded headers.
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
];
