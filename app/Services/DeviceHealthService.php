<?php

namespace App\Services;

/**
 * Service for monitoring the health and connectivity of POS devices.
 */
class DeviceHealthService
{
    /**
     * Report heartbeat from a device.
     *
     * @param string $deviceId
     * @param array $metadata
     * @return void
     */
    public function recordHeartbeat(string $deviceId, array $metadata = [])
    {
        \Illuminate\Support\Facades\DB::table('devices')
            ->where('device_id', $deviceId)
            ->update(['updated_at' => now()]);
    }

    /**
     * Get list of offline devices.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getUnhealthyDevices()
    {
        return \Illuminate\Support\Facades\DB::table('devices')
            ->where('updated_at', '<', now()->subMinutes(5))
            ->where('trust_status', 'active')
            ->get();
    }
}
