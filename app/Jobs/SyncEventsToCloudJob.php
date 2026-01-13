<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\SyncService;

/**
 * Job to synchronize local events to the cloud.
 */
class SyncEventsToCloudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'sync-outbox';

    protected $branchId;

    public function __construct(string $branchId)
    {
        $this->branchId = $branchId;
    }

    public function handle(SyncService $syncService)
    {
        $syncService->pushToCloud($this->branchId);
    }
}
