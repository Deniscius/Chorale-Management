<?php

return [
    'demo' => env('APP_DEMO_MODE', false),
    'canonical_https' => env('APP_CANONICAL_HTTPS', false),
    'bootstrap_enabled' => env('PLATFORM_BOOTSTRAP_ENABLED', false),
    'setup_token' => env('PLATFORM_SETUP_TOKEN'),
    'ephemeral_files' => env('FILES_EPHEMERAL', false),
    'free_database' => env('DATABASE_FREE_PLAN', false),
    'bootstrap' => [
        'email' => env('PLATFORM_BOOTSTRAP_EMAIL'),
        'password' => env('PLATFORM_BOOTSTRAP_PASSWORD'),
        'totp_secret' => env('PLATFORM_BOOTSTRAP_TOTP_SECRET'),
    ],
    // Enable only behind a proxy that replaces incoming forwarded headers.
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
];
