<?php

namespace App\Services;

/**
 * Service for validating events against business rules and schema.
 */
class EventValidationService
{
    /**
     * Validate an event before it is committed to the ledger.
     *
     * @param string $eventType
     * @param array $payload
     * @return bool
     * @throws \Exception
     */
    public function validate(string $eventType, array $payload)
    {
        // Example: Ensure quantity is always positive for STOCK_ADJUSTED
        if ($eventType === 'STOCK_ADJUSTED' && ($payload['quantity'] ?? 0) <= 0) {
            throw new \Exception("Invalid stock adjustment quantity.");
        }
        return true;
    }

    /**
     * Check for sequence gaps in local events.
     *
     * @param string $accountid
     * @return bool
     */
    public function checkSequenceIntegrity(string $accountid)
    {
        $sequences = \Illuminate\Support\Facades\DB::table('event_ledger')
            ->where('account_id', $accountid)
            ->orderBy('local_sequence', 'asc')
            ->pluck('local_sequence')
            ->toArray();

        for ($i = 0; $i < count($sequences) - 1; $i++) {
            if ($sequences[$i + 1] !== $sequences[$i] + 1) {
                return false;
            }
        }

        return true;
    }
}
