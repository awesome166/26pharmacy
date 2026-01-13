<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\SyncService;

/**
 * Job to pull events from the cloud to the local branch.
 */
class PullCloudEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'sync-inbox';

    protected $branchId;

    public function __construct(string $branchId)
    {
        $this->branchId = $branchId;
    }

    public function handle(SyncService $syncService)
    {
        $syncService->pullFromCloud($this->branchId);
    }
}
