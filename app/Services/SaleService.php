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
     * OPTIMIZED: Uses batch inserts and updates to reduce query count.
     */
    public function processSale(array $saleData)
    {
        try {
            $result = DB::transaction(function () use ($saleData) {
                $saleId = $saleData['id'] ?? Str::ulid()->toString();

                // 1. Create Sale Header (Read Model)
                // account_id is automatically assigned via UsesTenant trait
                $sale = \App\Models\Sale::create([
                    'id' => $saleId,
                    // 'account_id' => handled automatically
                    'user_id' => $saleData['user_id'] ?? null,
                    'customer_name' => $saleData['customer_name'] ?? null,
                    'customer_dob' => $saleData['customer_dob'] ?? null,
                    'customer_phone' => $saleData['customer_phone'] ?? null,
                    'customer_email' => $saleData['customer_email'] ?? null,
                    // Fix: Map 'subtotal' from request to 'subtotal_amount' in DB
                    'subtotal_amount' => $saleData['subtotal_amount'] ?? $saleData['subtotal'] ?? 0,
                    'tax_amount' => $saleData['tax_amount'] ?? 0,
                    'total_amount' => $saleData['total_amount'],
                    'payment_type' => $saleData['payment_type'] ?? null,
                    'payment_metadata' => $saleData['payment_metadata'] ?? null,
                    'cash_received' => $saleData['cash_received'] ?? null,
                    'change_amount' => $saleData['change_amount'] ?? null,
                    'finalized_at' => now(),
                ]);

                // 2. OPTIMIZED: Batch insert sale items (1 query instead of N)
                $saleItems = collect($saleData['items'])->map(function ($item) use ($saleId) {
                    return [
                        'id' => $item['id'] ?? Str::ulid()->toString(),
                        'sale_id' => $saleId,
                        'batch_id' => $item['batch_id'],
                        'inventory_id' => $item['inventory_id'],
                        'drug_id' => $item['drug_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'line_total' => $item['quantity'] * $item['price'],
                        'tax_amount' => $item['tax_amount'] ?? 0,
                        'requires_prescription' => $item['requires_prescription'] ?? false,
                        'prescription_metadata' => json_encode($item['prescription_metadata'] ?? null),
                        'dosage_instructions' => json_encode($item['dosage_instructions'] ?? null),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->toArray();

                \App\Models\SaleItem::insert($saleItems);

                // 3. OPTIMIZED: Batch update inventory (group by inventory_id to handle duplicates)
                $inventoryUpdates = collect($saleData['items'])
                    ->groupBy('inventory_id')
                    ->map(fn($items) => $items->sum('quantity'));

                foreach ($inventoryUpdates as $inventoryId => $totalQty) {
                    // Use Eloquent model for tenant scoping (UsesTenant trait)
                    \App\Models\Inventory::where('id', $inventoryId)
                        ->decrement('quantity_on_hand', $totalQty);
                }

                // 4. Prepare event data to emit AFTER transaction
                $eventData = [
                    'account_id' => app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId(),
                    'device_id' => $saleData['device_id'] ?? (\App\Models\Device::value('device_id') ?? 'unknown'),
                    'actor_user_id' => $saleData['user_id'] ?? null,
                    'event_type' => 'SALE_FINALIZED',
                    'event_payload' => [
                        'sale_id' => $saleId,
                        'items' => $saleData['items'],
                        'total_amount' => $saleData['total_amount'],
                        'tax_amount' => $saleData['tax_amount'] ?? 0,
                        'payment_type' => $saleData['payment_type'],
                        'finalized_at' => now()->toIso8601String(),
                    ]
                ];

                return (object) [
                    'sale_id' => $saleId,
                    'event_data' => $eventData,
                ];
            });

            // 5. OPTIMIZED: Emit event AFTER transaction (non-blocking)
            $event = $this->ledger->emitEvent($result->event_data);

            // 6. Invalidate store inventory cache
            app(\App\Services\StoreInventoryService::class)->invalidateCache();

            return (object) [
                'sale_id' => $result->sale_id,
                'event_id' => $event->id,
                'hash' => $event->hash
            ];
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
        // Use Eloquent to check TenantScope automatically and load items with drug info and user
        return \App\Models\Sale::with(['items', 'items.drug', 'user'])->find($saleId);
    }

    /**
     * Get paginated sales for a branch with optional filtering and sorting.
     */
    public function getAllSales(int $perPage = 15, array $filters = [], string $sortBy = 'finalized_at', string $sortDirection = 'desc')
    {
        $query = \App\Models\Sale::query();

        // Apply date range filters
        if (!empty($filters['start_date'])) {
            $query->whereDate('finalized_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('finalized_at', '<=', $filters['end_date']);
        }

        // Apply search filter if provided
        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('id', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('payment_type', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Validate and apply sorting
        $allowedSortFields = ['finalized_at', 'total_amount', 'payment_type'];
        $sortField = in_array($sortBy, $allowedSortFields) ? $sortBy : 'finalized_at';
        $sortDir = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        return $query->orderBy($sortField, $sortDir)->paginate($perPage);
    }
}
