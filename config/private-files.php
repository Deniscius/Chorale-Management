<?php

return [
    // Render Free has no persistent disk. Keep bounded, encrypted assets in PostgreSQL.
    'driver' => env('PRIVATE_FILES_DRIVER', env('FILES_EPHEMERAL', false) ? 'database' : 'local'),
    'tenant_quota' => (int) env('PRIVATE_FILES_TENANT_QUOTA', 64 * 1024 * 1024),
    'global_quota' => (int) env('PRIVATE_FILES_GLOBAL_QUOTA', 256 * 1024 * 1024),
];
