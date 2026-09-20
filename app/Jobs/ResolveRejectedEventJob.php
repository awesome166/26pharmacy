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

    public function handle(ConflictResolutionService $resolutionService): void
    {
        $rejected = \Illuminate\Support\Facades\DB::table('event_rejections')
            ->whereNull('resolved_at')
            ->limit(10)
            ->get();

        foreach ($rejected as $entry) {
            $resolutionService->flagForReview(
                $entry->id,
                'Auto-retry pending: ' . ($entry->rejection_reason ?? 'unknown')
            );
        }
    }
}
