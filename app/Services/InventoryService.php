<?php

namespace App\Services;

use App\Services\EventLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryService
{
    protected $ledger;

    public function __construct(EventLedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * Get paginated stock levels for a branch.
     */
    public function getStockLevels(string $branchId, int $perPage = 15)
    {
        return DB::table('inventory')
            ->join('drugs', 'inventory.drug_id', '=', 'drugs.drug_id')
            ->join('batches', 'inventory.batch_id', '=', 'batches.batch_id')
            ->where('inventory.branch_id', $branchId)
            ->select(
                'inventory.*',
                'drugs.name as drug_name',
                'drugs.strength',
                'batches.expiry_date',
                'batches.lot_number',
                'batches.batch_id'
            )
            ->paginate($perPage);
    }

    /**
     * Search for drugs/inventory.
     */
    public function searchDrugs(string $query, int $perPage = 15)
    {
        return DB::table('inventory')
            ->join('drugs', 'inventory.drug_id', '=', 'drugs.drug_id')
            ->join('batches', 'inventory.batch_id', '=', 'batches.batch_id')
            ->where('drugs.name', 'like', "%{$query}%")
            ->orWhere('batches.batch_id', 'like', "%{$query}%")
            ->select(
                'inventory.*',
                'drugs.name as drug_name',
                'drugs.strength',
                'batches.expiry_date'
            )
            ->paginate($perPage);
    }

    /**
     * Adjust stock level (Emit Event).
     */
    public function adjustStock(string $branchId, string $batchId, int $quantityChange, string $reason, array $context)
    {
        $payload = [
            'batch_id' => $batchId,
            'quantity_change' => $quantityChange, // + or -
            'reason' => $reason,
            'adjusted_at' => now()->toIso8601String()
        ];

        $this->ledger->emitEvent([
            'tenant_id' => $context['tenant_id'],
            'branch_id' => $branchId,
            'device_id' => $context['device_id'] ?? 'unknown',
            'actor_user_id' => $context['user_id'] ?? null,
            'event_type' => 'STOCK_ADJUSTED',
            'event_payload' => $payload
        ]);

        return true;
    }

    /**
     * Register a new batch (Emit Event).
     */
    public function registerBatch(array $batchData, array $context)
    {
        // ... implementation for batch reg
    }
}
