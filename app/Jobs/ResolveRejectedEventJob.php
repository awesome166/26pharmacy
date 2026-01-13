<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\ConflictResolutionService;

/**
 * Job to attempt automatic resolution of a rejected or conflicting event.
 */
class ResolveRejectedEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'ledger-events';

    protected $event;

    public function __construct($event)
    {
        $this->event = $event;
    }

    public function handle(ConflictResolutionService $resolutionService)
    {
        // Logic to try resolving and re-emitting event
    }
}
