<?php

use App\Services\LicenseService;

uses(Tests\TestCase::class);

it('signs and verifies a device-bound license', function () {
    config()->set('sync.license_hmac_secret', 'test-secret-that-is-not-used-in-production');
    $service = app(LicenseService::class);
    $payload = [
        'account_id' => '01KACCOUNT0000000000000000',
        'branch_id' => '01KBRANCH00000000000000000',
        'device_id' => '01KDEVICE00000000000000000',
        'expires_at' => now()->addDay()->toIso8601String(),
        'features' => ['sync'],
    ];

    $license = $service->signPayload($payload);

    expect($service->verifyLicense($license, [
        'account_id' => $payload['account_id'],
        'branch_id' => $payload['branch_id'],
        'device_id' => $payload['device_id'],
        'feature' => 'sync',
    ]))->toBeTrue();
});

it('rejects a tampered or expired license', function () {
    config()->set('sync.license_hmac_secret', 'test-secret-that-is-not-used-in-production');
    $service = app(LicenseService::class);
    $license = $service->signPayload([
        'account_id' => '01KACCOUNT0000000000000000',
        'expires_at' => now()->addDay()->toIso8601String(),
        'features' => ['sync'],
    ]);
    $license['payload']['account_id'] = 'tampered';

    $service->verifyLicense($license);
})->throws(RuntimeException::class);
