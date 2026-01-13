<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\ProjectionService;

/**
 * Job to process a ledger event and update read-model projections.
 */
class ProcessLedgerEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'projections';

    protected $event;

    public function __construct($event)
    {
        $this->event = $event;
    }

    public function handle(ProjectionService $projectionService)
    {
        $projectionService->projectEvent($this->event);
    }
}
