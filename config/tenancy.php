<?php

return [
    'enabled' => env('TENANCY_ENABLED', true),
    'registration' => env('TENANT_REGISTRATION_ENABLED', true),
    'chariow' => [
        'payment_url' => env('CHARIOW_PAYMENT_URL'),
        'api_key' => env('CHARIOW_API_KEY'),
        'product_id' => env('CHARIOW_PRODUCT_ID'),
    ],
];
