<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\ProjectionService;

/**
 * Job to trigger a full or partial rebuild of read models.
 */
class RebuildProjectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'projections';

    protected $tenantId;
    protected $branchId;

    public function __construct(string $tenantId, ?string $branchId = null)
    {
        $this->tenantId = $tenantId;
        $this->branchId = $branchId;
    }

    public function handle(ProjectionService $projectionService)
    {
        $projectionService->rebuildProjections($this->tenantId, $this->branchId);
    }
}
