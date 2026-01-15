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
                $saleId = $saleData['id'] ?? Str::uuid()->toString();

                // 1. Create Sale Header (Read Model)
                $sale = \App\Models\Sale::create([
                    'id' => $saleId,
                    'account_id' => $saleData['account_id'],
                    'user_id' => $saleData['user_id'] ?? null,
                    'customer_name' => $saleData['customer_name'] ?? null,
                    'customer_dob' => $saleData['customer_dob'] ?? null,
                    'customer_phone' => $saleData['customer_phone'] ?? null,
                    'customer_email' => $saleData['customer_email'] ?? null,
                    'subtotal_amount' => $saleData['subtotal_amount'] ?? 0,
                    'tax_amount' => $saleData['tax_amount'] ?? 0,
                    'total_amount' => $saleData['total_amount'],
                    'payment_type' => $saleData['payment_type'] ?? null,
                    'payment_metadata' => $saleData['payment_metadata'] ?? null,
                    'finalized_at' => now(),
                ]);

                // 2. Process Items & Update Inventory
                foreach ($saleData['items'] as $item) {
                    // Persistent Sale Item
                    \App\Models\SaleItem::create([
                        'id' => $item['id'] ?? Str::uuid()->toString(),
                        'sale_id' => $saleId,
                        'batch_id' => $item['batch_id'],
                        'inventory_id' => $item['inventory_id'],
                        'drug_id' => $item['drug_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'line_total' => $item['quantity'] * $item['price'],
                        'tax_amount' => $item['tax_amount'] ?? 0,
                        'requires_prescription' => $item['requires_prescription'] ?? false,
                        'prescription_metadata' => $item['prescription_metadata'] ?? null,
                        'dosage_instructions' => $item['dosage_instructions'] ?? null,
                    ]);

                    // Decrement Stock (Inventory Table)
                    DB::table('inventory')
                        ->where('id', $item['inventory_id'])
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
                    'account_id' => $saleData['account_id'],
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
        return DB::table('sales')->where('id', $saleId)->first();
    }

    /**
     * Get paginated sales for a branch.
     */
    public function getAllSales(?string $accountId = null, int $perPage = 15)
    {
        $query = DB::table('sales')->orderBy('finalized_at', 'desc');

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return $query->paginate($perPage);
    }
}
