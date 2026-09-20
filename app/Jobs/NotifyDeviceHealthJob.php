<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\DeviceHealthService;

/**
 * Job to notify administrators about device health issues.
 */
class NotifyDeviceHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'notifications';

    protected $deviceId;
    protected $issue;

    public function __construct(string $deviceId, string $issue)
    {
        $this->deviceId = $deviceId;
        $this->issue = $issue;
    }

    public function handle(DeviceHealthService $healthService): void
    {
        $device = \App\Models\Device::where('device_id', $this->deviceId)->first();
        if (!$device) {
            return;
        }

        \Illuminate\Support\Facades\Log::warning("Device health issue: {$this->issue}", [
            'device_id' => $this->deviceId,
            'device_name' => $device->device_name,
        ]);
    }
}
