<?php

namespace App\Services;

/**
 * Service for handling reconciliation when stock/batch updates conflict across branches.
 */
class ConflictResolutionService
{
    /**
     * Resolve a stock conflict between local and cloud ledger.
     *
     * @param string $batchId
     * @param array $conflictingEvents
     * @return void
     */
    public function resolveStockConflict(string $batchId, array $conflictingEvents)
    {
        // Simple LWW (Last Write Wins) resolution logic
        usort($conflictingEvents, fn($a, $b) => strcmp($b->event_time_utc, $a->event_time_utc));

        // Mark others as superseded or rejected
        foreach (array_slice($conflictingEvents, 1) as $superseded) {
            $this->flagForReview($superseded->id, "Superseded by later event on same batch.");
        }
    }

    /**
     * Flag an event for manual review due to unresolvable conflict.
     *
     * @param string $eventId
     * @param string $reason
     * @return void
     */
    public function flagForReview(string $eventId, string $reason)
    {
        \Illuminate\Support\Facades\DB::table('event_rejections')->insert([
            'id' => $eventId,
            'rejection_reason' => $reason,
            'reviewed_by' => null,
            'resolved_at' => null,
        ]);
    }
}
