<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    if (config('sync.role') !== 'child') {
        return;
    }

    // Devices are the installation registry. Environment-provisioned clients
    // must not disappear from recovery merely because settings were not copied.
    \Illuminate\Support\Facades\DB::table('devices')
        ->where('trust_status', 'active')->whereNotNull('account_id')
        ->whereNotNull('branch_id')->orderBy('device_id')
        ->get(['account_id', 'device_id'])
        ->each(function ($device) {
            \App\Jobs\SyncEventsToCloudJob::dispatch($device->account_id, $device->device_id);
            \App\Jobs\PullCloudEventsJob::dispatch($device->account_id, $device->device_id);
        });
})->everyMinute()->name('sync-heartbeat')->withoutOverlapping();
