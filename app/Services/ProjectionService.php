<?php

namespace App\Services;

/**
 * Service for managing read-model projections from the event ledger.
 */
class ProjectionService
{
    /**
     * Project a single event into the read models.
     *
     * @param object $event
     * @return void
     */
    public function projectEvent($event)
    {
        $payload = is_string($event->event_payload) ? json_decode($event->event_payload, true) : $event->event_payload;

        switch ($event->event_type) {
            case 'SALE_FINALIZED':
                \Illuminate\Support\Facades\DB::table('sales')->insert([
                    'id' => $payload['id'],
                    'account_id' => $event->account_id,
                    'total_amount' => $payload['total_amount'],
                    'tax_amount' => $payload['tax_amount'],
                    'payment_type' => $payload['payment_type'],
                    'finalized_at' => $event->event_time_utc,
                ]);
                break;

            case 'STOCK_ADJUSTED':
                \Illuminate\Support\Facades\DB::table('inventory')->updateOrInsert(
                    ['account_id' => $event->account_id, 'batch_id' => $payload['batch_id']],
                    ['quantity_on_hand' => \Illuminate\Support\Facades\DB::raw("quantity_on_hand + " . (int)$payload['change'])]
                );
                break;
        }
    }

    /**
     * Trigger a full rebuild of projections from a specific point in time.
     *
     * @param string $accountid
     * @return void
     */
    public function rebuildProjections(string $accountid)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ( $accountid) {
            $query = \Illuminate\Support\Facades\DB::table('event_ledger')->where('account_id', $accountid);
            // if ($accountid) $query->where('account_id', $accountid);

            // 1. Truncate current read models
            \Illuminate\Support\Facades\DB::table('sales')->where('account_id', $accountid)->delete();
            \Illuminate\Support\Facades\DB::table('inventory')->where('account_id', $accountid)->delete();

            // 2. Replay all events
            $query->orderBy('local_sequence', 'asc')->chunk(100, function ($events) {
                foreach ($events as $event) {
                    $this->projectEvent($event);
                }
            });
        });
    }
}
