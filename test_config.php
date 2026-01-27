#!/usr/bin/env php
<?php

// Test the actual controller response with FIXED logic
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Simulate what the controller does
$accountId = '01KFND09R043NG9PZB89F9RZZ0';

// Get platform and tenant settings
$platformSettings = DB::table('system_settings')->whereNull('account_id')->get()->pluck('value', 'key');
$tenantSettings = DB::table('system_settings')->where('account_id', $accountId)->get()->pluck('value', 'key');

$merged = $platformSettings->merge($tenantSettings);

$defaults = [
    'inventory_batch_mode' => false,
    'inventory_prevent_negative_stock' => false,
];

$finalSettings = collect($defaults)->merge($merged);

echo "Final settings (before conversion):\n";
foreach ($finalSettings as $key => $value) {
    echo "$key => " . var_export($value, true) . " (type: " . gettype($value) . ")\n";
}

// Apply FIXED conversion with loose comparison
$finalSettings = $finalSettings->map(function ($value) {
    // Use loose comparison to handle both string and integer types
    if ($value == 1 || $value === 'true' || $value === true) return true;
    if ($value == 0 || $value === 'false' || $value === false || $value === null) return false;
    return $value;
});

echo "\nFinal settings (after conversion):\n";
foreach ($finalSettings as $key => $value) {
    echo "$key => " . var_export($value, true) . " (type: " . gettype($value) . ")\n";
}
