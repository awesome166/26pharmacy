<?php

namespace App\Services;

use App\Services\EventLedgerService;
use App\Services\TaxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleService
{
    protected $ledger;
    protected $tax;

    public function __construct(EventLedgerService $ledger, TaxService $tax)
    {
        $this->ledger = $ledger;
        $this->tax = $tax;
    }

    /**
     * Process a new sale transaction.
     */
    public function processSale(array $saleData)
    {
        try {
            return DB::transaction(function () use ($saleData) {
                $saleId = $saleData['sale_id'] ?? Str::uuid()->toString();

                // 1. Create Sale Header (Read Model)
                $sale = \App\Models\Sale::create([
                    'sale_id' => $saleId,
                    'tenant_id' => $saleData['tenant_id'],
                    'branch_id' => $saleData['branch_id'],
                    'total_amount' => $saleData['total_amount'],
                    'tax_amount' => $saleData['tax_amount'] ?? 0,
                    'payment_type' => $saleData['payment_type'],
                    'finalized_at' => now(),
                ]);

                // 2. Process Items & Update Inventory
                foreach ($saleData['items'] as $item) {
                    // Persistent Sale Item
                    \App\Models\SaleItem::create([
                        'sale_id' => $saleId,
                        'batch_id' => $item['batch_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'total' => $item['quantity'] * $item['price'],
                        'dosage_instructions' => $item['dosage_instructions'] ?? null,
                    ]);

                    // Decrement Stock (Inventory Table)
                    // Inventory is keyed by branch + batch + drug.
                    // Assuming we can find it by batch_id + branch_id.
                    // Migration: inventory has uuid primary key, but unique index on branch+drug?
                    // Actually inventory migration: index(['branch_id', 'drug_id']);
                    // It doesn't enforce uniqueness on batch? Wait, let's check platform migration again.
                    // Schema::create('inventory', ... $table->uuid('batch_id'); ...
                    // Ideally, we decrement where batch_id = X and branch_id = Y.

                    DB::table('inventory')
                        ->where('branch_id', $saleData['branch_id'])
                        ->where('batch_id', $item['batch_id']) // specific batch at this branch
                        ->decrement('quantity_on_hand', $item['quantity']);
                }

                // 3. Prepare Event Payload
                $payload = [
                    'sale_id' => $saleId,
                    'items' => $saleData['items'],
                    'total_amount' => $saleData['total_amount'],
                    'tax_amount' => $saleData['tax_amount'] ?? 0,
                    'payment_type' => $saleData['payment_type'],
                    'finalized_at' => now()->toIso8601String(),
                ];

                // 4. Emit 'SALE_FINALIZED' Event
                $event = $this->ledger->emitEvent([
                    'tenant_id' => $saleData['tenant_id'],
                    'branch_id' => $saleData['branch_id'],
                    'device_id' => $saleData['device_id'] ?? 'unknown',
                    'actor_user_id' => $saleData['user_id'] ?? null,
                    'event_type' => 'SALE_FINALIZED',
                    'event_payload' => $payload
                ]);

                return (object) ['sale_id' => $saleId, 'event_id' => $event->event_id];
            });
        } catch (\Exception $e) {
            // Log error or rethrow as needed
            throw $e;
        }
    }

    /**
     * Get a single sale by ID from the Read Model.
     */
    public function getSale(string $saleId)
    {
        return DB::table('sales')->where('sale_id', $saleId)->first();
    }

    /**
     * Get paginated sales for a branch.
     */
    public function getAllSales(?string $branchId = null, int $perPage = 15)
    {
        $query = DB::table('sales')->orderBy('finalized_at', 'desc');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->paginate($perPage);
    }
}
