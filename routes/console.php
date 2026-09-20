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

    \Illuminate\Support\Facades\DB::table('system_settings')
        ->where('key', 'sync_client_id')
        ->whereNotNull('account_id')
        ->get(['account_id', 'value'])
        ->each(function ($setting) {
            \App\Jobs\SyncEventsToCloudJob::dispatch($setting->account_id, $setting->value);
            \App\Jobs\PullCloudEventsJob::dispatch($setting->account_id, $setting->value);
        });
})->everyMinute()->name('sync-heartbeat')->withoutOverlapping();
