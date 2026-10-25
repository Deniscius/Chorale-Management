<?php

return [
    // Enable only behind a proxy that replaces incoming forwarded headers.
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
];
