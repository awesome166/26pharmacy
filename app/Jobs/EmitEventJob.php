<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\EventLedgerService;

/**
 * Job to emit an event to the ledger and trigger downstream processing.
 */
class EmitEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'ledger-events';

    protected $eventData;

    public function __construct(array $eventData)
    {
        $this->eventData = $eventData;
    }

    public function handle(EventLedgerService $ledger): void
    {
        $result = $ledger->emitEvent($this->eventData);

        SyncEventsToCloudJob::dispatch(
            $this->eventData['account_id'] ?? null,
            $this->eventData['device_id'] ?? null,
        );

        if (isset($this->eventData['event_payload']['batch_id'])) {
            ResolveRejectedEventJob::dispatch($result);
        }
    }
}
