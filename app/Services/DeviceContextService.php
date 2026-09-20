<?php

namespace App\Services;

use App\Models\Device;
use AbacPermissions\Tenancy\TenantScope;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeviceContextService
{
    public function currentDevice(string $accountId, ?string $deviceId = null): Device
    {
        $configuredDeviceId = DB::table('system_settings')
            ->where('account_id', $accountId)
            ->where('key', 'sync_client_id')
            ->value('value') ?: config('sync.client_id');

        $query = Device::withoutGlobalScope(TenantScope::class)
            ->where('account_id', $accountId)
            ->where('trust_status', 'active')
            ->whereNotNull('branch_id')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('branches')
                    ->whereColumn('branches.branch_id', 'devices.branch_id')
                    ->whereColumn('branches.account_id', 'devices.account_id')
                    ->where('branches.is_active', true);
            });

        // Child installations must stamp writes with their provisioned identity.
        // A browser supplied header is not an authority to switch device streams.
        $requestedDeviceId = $deviceId;
        if ($requestedDeviceId) {
            $device = (clone $query)->where('device_id', $requestedDeviceId)->first();

            if (!$device) {
                throw new RuntimeException('The requested device is not active for this account and branch.');
            }

            return $device;
        }

        $device = $configuredDeviceId
            ? (clone $query)->where('device_id', $configuredDeviceId)->first()
            : null;

        // A shared cloud installation can have a global SYNC_CLIENT_ID from a
        // different tenant. Fall back within the authenticated account rather
        // than failing a valid branch context.
        $device ??= $query->orderBy('created_at')->orderBy('device_id')->first();

        if (!$device) {
            throw new RuntimeException('No active branch device is configured for this installation.');
        }

        return $device;
    }

    public function currentBranchId(string $accountId, ?string $deviceId = null): string
    {
        return (string) $this->currentDevice($accountId, $deviceId)->branch_id;
    }
}
