<?php

return [
    'public_key' => env('WEBPUSH_PUBLIC_KEY'),
    'private_key' => env('WEBPUSH_PRIVATE_KEY'),
    'subject' => env('WEBPUSH_SUBJECT', env('APP_URL')),
    'ttl' => (int) env('WEBPUSH_TTL', 86400),
];
