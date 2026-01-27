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
            // Scope automatically handles account_id filtering via UsesTenant
            $lastEvent = \App\Models\EventLedger::query()
                ->orderBy('local_sequence', 'desc')
                ->first();

            $sequence = $lastEvent ? $lastEvent->local_sequence + 1 : 1;
            $previousHash = $lastEvent ? $lastEvent->event_hash : str_repeat('0', 64);

            $id = \Illuminate\Support\Str::ulid();
            $hash = $this->computeHash($eventData['event_payload'], $previousHash);

            \App\Models\EventLedger::create([
                'id' => $id,
                // 'account_id' => $eventData['account_id'], // Explicitly passed, but trait likely enforces/defaults
                'device_id' => $eventData['device_id'],
                'actor_user_id' => $eventData['actor_user_id'] ?? null,
                'event_type' => $eventData['event_type'],
                'event_payload' => $eventData['event_payload'], // Casts handle json encoding
                'local_sequence' => $sequence,
                'event_time_utc' => now(),
                'event_hash' => $hash,
            ]);

            // Dispatch job for processing
            \App\Jobs\ProcessLedgerEventJob::dispatch((object) [
                'id' => $id,
                'event_type' => $eventData['event_type'],
                'event_payload' => $eventData['event_payload']
            ]);

            return (object) ['id' => $id, 'hash' => $hash];
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
     * @param string $accountid
     * @param int $sinceSequence
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getEvents(string $accountid, int $sinceSequence = 0)
    {
        return \App\Models\EventLedger::query()
            // account_id filter handled by UsesTenant scope
            ->where('local_sequence', '>', $sinceSequence)
            ->orderBy('local_sequence', 'asc')
            ->get();
    }
}
