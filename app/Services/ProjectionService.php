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
                    'sale_id' => $payload['sale_id'],
                    'tenant_id' => $event->tenant_id,
                    'branch_id' => $event->branch_id,
                    'total_amount' => $payload['total_amount'],
                    'tax_amount' => $payload['tax_amount'],
                    'payment_type' => $payload['payment_type'],
                    'finalized_at' => $event->event_time_utc,
                ]);
                break;

            case 'STOCK_ADJUSTED':
                \Illuminate\Support\Facades\DB::table('inventory')->updateOrInsert(
                    ['branch_id' => $event->branch_id, 'batch_id' => $payload['batch_id']],
                    ['quantity_on_hand' => \Illuminate\Support\Facades\DB::raw("quantity_on_hand + " . (int)$payload['change'])]
                );
                break;
        }
    }

    /**
     * Trigger a full rebuild of projections from a specific point in time.
     *
     * @param string $tenantId
     * @param string|null $branchId
     * @return void
     */
    public function rebuildProjections(string $tenantId, string $branchId = null)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($tenantId, $branchId) {
            $query = \Illuminate\Support\Facades\DB::table('event_ledger')->where('tenant_id', $tenantId);
            if ($branchId) $query->where('branch_id', $branchId);

            // 1. Truncate current read models
            \Illuminate\Support\Facades\DB::table('sales')->where('tenant_id', $tenantId)->delete();
            \Illuminate\Support\Facades\DB::table('inventory')->where('tenant_id', $tenantId)->delete();

            // 2. Replay all events
            $query->orderBy('local_sequence', 'asc')->chunk(100, function ($events) {
                foreach ($events as $event) {
                    $this->projectEvent($event);
                }
            });
        });
    }
}
