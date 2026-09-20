<?php

namespace App\Services;

/**
 * Service for managing devices (POS terminals) within branches.
 */
class DeviceService
{
    /**
     * Register a new device to a branch.
     *
     * @param string $accountid
     * @param array $data
     * @return object
     */
    public function registerDevice(string $accountid, string $branchId, array $data)
    {
        $id = \Illuminate\Support\Str::ulid();
        $plainToken = \Illuminate\Support\Str::random(64);
        \Illuminate\Support\Facades\DB::table('devices')->insert([
            'device_id' => $id,
            'account_id' => $accountid,
            'branch_id' => $branchId,
            'device_name' => $data['device_name'] ?? null,
            'trust_status' => 'active',
            'sync_token_hash' => hash('sha256', $plainToken),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) ['device_id' => $id, 'sync_token' => $plainToken];
    }

    /**
     * Revoke trust from a device.
     *
     * @param string $deviceId
     * @return bool
     */
    public function revokeDevice(string $deviceId)
    {
        return (bool) \Illuminate\Support\Facades\DB::table('devices')
            ->where('device_id', $deviceId)
            ->update(['trust_status' => 'revoked', 'updated_at' => now()]);
    }

    /**
     * Verify if a device is active and trusted.
     *
     * @param string $deviceId
     * @return bool
     */
    public function isDeviceTrusted(string $deviceId)
    {
        $device = \Illuminate\Support\Facades\DB::table('devices')
            ->where('device_id', $deviceId)
            ->first();

        return $device && $device->trust_status === 'active';
    }
}
