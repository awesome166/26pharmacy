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
     * Note: Explicitly sets account_id from event payload since projections
     * rebuild historical data and may not have current TenantContext.
     *
     * @param object $event
     * @return void
     */
    public function projectEvent($event)
    {
        $payload = is_string($event->event_payload) ? json_decode($event->event_payload, true) : $event->event_payload;

        switch ($event->event_type) {
            case 'SALE_FINALIZED':
                // Use Sale model - explicitly set account_id from event
                \App\Models\Sale::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->create([
                        'id' => $payload['id'],
                        'account_id' => $event->account_id, // Explicit from event
                        'total_amount' => $payload['total_amount'],
                        'tax_amount' => $payload['tax_amount'],
                        'payment_type' => $payload['payment_type'],
                        'finalized_at' => $event->event_time_utc,
                    ]);
                break;

            case 'STOCK_ADJUSTED':
                // Use Inventory model - bypass scope and explicitly set account_id
                \App\Models\Inventory::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('account_id', $event->account_id)
                    ->where('batch_id', $payload['batch_id'])
                    ->increment('quantity_on_hand', (int)$payload['change']);
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
        \Illuminate\Support\Facades\DB::transaction(function () use ($accountid) {
            // Use EventLedger model to query events
            $query = \App\Models\EventLedger::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                ->where('account_id', $accountid);

            // 1. Truncate current read models for this account
            \App\Models\Sale::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                ->where('account_id', $accountid)
                ->delete();

            \App\Models\Inventory::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                ->where('account_id', $accountid)
                ->delete();

            // 2. Replay all events
            $query->orderBy('local_sequence', 'asc')->chunk(100, function ($events) {
                foreach ($events as $event) {
                    $this->projectEvent($event);
                }
            });
        });
    }
}
