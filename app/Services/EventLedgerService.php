<?php

namespace App\Services;

/**
 * Service for managing the event ledger (The Source of Truth).
 */
class EventLedgerService
{
    /**
     * Emit a new event to the ledger.
     *
     * @param array $eventData
     * @return object
     */
    public function emitEvent(array $eventData)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($eventData) {
            $lastEvent = \Illuminate\Support\Facades\DB::table('event_ledger')
                ->where('branch_id', $eventData['branch_id'])
                ->orderBy('local_sequence', 'desc')
                ->first();

            $sequence = $lastEvent ? $lastEvent->local_sequence + 1 : 1;
            $previousHash = $lastEvent ? $lastEvent->event_hash : str_repeat('0', 64);

            $id = \Illuminate\Support\Str::uuid();
            $hash = $this->computeHash($eventData['event_payload'], $previousHash);

            \Illuminate\Support\Facades\DB::table('event_ledger')->insert([
                'event_id' => $id,
                'tenant_id' => $eventData['tenant_id'],
                'branch_id' => $eventData['branch_id'],
                'device_id' => $eventData['device_id'],
                'actor_user_id' => $eventData['actor_user_id'] ?? null,
                'event_type' => $eventData['event_type'],
                'event_payload' => json_encode($eventData['event_payload']),
                'local_sequence' => $sequence,
                'event_time_utc' => now(),
                'event_hash' => $hash,
            ]);

            // Dispatch job for processing
            \App\Jobs\ProcessLedgerEventJob::dispatch((object) [
                'event_id' => $id,
                'event_type' => $eventData['event_type'],
                'event_payload' => $eventData['event_payload']
            ]);

            return (object) ['event_id' => $id, 'hash' => $hash];
        });
    }

    /**
     * Compute a SHA-256 hash for an event to ensure integrity.
     *
     * @param array $payload
     * @param string $previousHash
     * @return string
     */
    public function computeHash(array $payload, string $previousHash)
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES) . $previousHash);
    }

    /**
     * Retrieve events for a specific branch.
     *
     * @param string $branchId
     * @param int $sinceSequence
     * @return \Illuminate\Support\Collection
     */
    public function getEvents(string $branchId, int $sinceSequence = 0)
    {
        return \Illuminate\Support\Facades\DB::table('event_ledger')
            ->where('branch_id', $branchId)
            ->where('local_sequence', '>', $sinceSequence)
            ->orderBy('local_sequence', 'asc')
            ->get();
    }
}
