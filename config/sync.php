<?php

return [
    'role' => env('SYNC_ROLE', 'child'),
    'client_id' => env('SYNC_CLIENT_ID'),
    'api_token' => env('SYNC_API_TOKEN'),
    'cloud_url' => env('CLOUD_URL'),
    'batch_size' => (int) env('SYNC_BATCH_SIZE', 100),
    'queue_connection' => env('SYNC_QUEUE_CONNECTION', 'database'),
    'heartbeat_interval' => (int) env('SYNC_HEARTBEAT_INTERVAL', 60),
    'license_public_key' => env('LICENSE_PUBLIC_KEY'),
    'license_private_key' => env('LICENSE_PRIVATE_KEY'),
    'license_hmac_secret' => env('LICENSE_HMAC_SECRET'),
    'license_allow_hmac' => (bool) env('LICENSE_ALLOW_HMAC', false),
    'license_grace_hours' => (int) env('LICENSE_OFFLINE_GRACE_HOURS', 72),
    'enforce_license_in_tests' => (bool) env('ENFORCE_LICENSE_IN_TESTS', false),
];
