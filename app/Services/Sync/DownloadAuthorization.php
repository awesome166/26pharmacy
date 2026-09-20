<?php

namespace App\Services\Sync;

use App\Models\Device;
use Illuminate\Support\Facades\DB;

final class DownloadAuthorization
{
    /** @return object */
    public function continuous(Device $device): object
    {
        abort_unless($device->trust_status === 'active', 403, 'DEVICE_REVOKED');

        $policy = DB::table('device_sync_policies')
            ->where('device_id', $device->device_id)
            ->where('account_id', $device->account_id)
            ->first();

        abort_unless($policy && $policy->download_mode === 'continuous', 403, 'DOWNLOAD_DISABLED');

        return $policy;
    }
}
