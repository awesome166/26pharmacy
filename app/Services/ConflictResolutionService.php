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
        $additiveTypes = ['STOCK_ADJUSTED', 'STOCK_TRANSFERRED', 'SALE_FINALIZED', 'SALE_RETURNED'];
        $types = collect($conflictingEvents)->map(fn ($event) => is_object($event)
            ? ($event->event_type ?? null) : ($event['event_type'] ?? null));
        if ($types->every(fn ($type) => in_array($type, $additiveTypes, true))) {
            // Deltas commute; every valid event must be projected exactly once.
            return;
        }

        usort($conflictingEvents, function ($a, $b) {
            $timeA = strtotime(is_string($a->event_time_utc ?? $a['event_time_utc'] ?? '') ? ($a->event_time_utc ?? $a['event_time_utc']) : 'now');
            $timeB = strtotime(is_string($b->event_time_utc ?? $b['event_time_utc'] ?? '') ? ($b->event_time_utc ?? $b['event_time_utc']) : 'now');
            return $timeB <=> $timeA;
        });

        foreach ($conflictingEvents as $superseded) {
            $id = is_object($superseded) ? $superseded->id : $superseded['id'];
            $this->flagForReview($id, "Concurrent absolute stock state requires manual reconciliation for batch {$batchId}.");
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
