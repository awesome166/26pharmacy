<?php

/**
 * Recommended Queue Configuration for Pharmacy POS.
 *
 * Add these to your 'connections' array in config/queue.php
 */

return [
    'ledger-events' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'ledger-events',
        'retry_after' => 90,
        'block_for' => null,
    ],
    'sync-outbox' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'sync-outbox',
        'retry_after' => 300,
    ],
    'sync-inbox' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'sync-inbox',
        'retry_after' => 300,
    ],
    'audit-trail' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'audit-trail',
        'retry_after' => 90,
    ],
    'projections' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'projections',
        'retry_after' => 120,
    ],
    'reporting' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'reporting',
        'retry_after' => 3600, // Longer timeout for heavy exports
    ],
    'notifications' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'notifications',
        'retry_after' => 90,
    ],
];
