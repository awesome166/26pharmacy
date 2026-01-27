<?php

namespace App\Services;

use App\Services\EventLedgerService;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Inventory;
use App\Models\Device;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use AbacPermissions\Tenancy\TenantContext;

class ReturnService
{
    protected $ledger;

    public function __construct(EventLedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * Process a return transaction.
     */
    public function processReturn(array $returnData, $user)
    {
        return DB::transaction(function () use ($returnData, $user) {
            $sale = Sale::with('items')->findOrFail($returnData['sale_id']);

            // Validation: Check if returnable
            foreach ($returnData['items'] as $item) {
                $saleItem = $sale->items->where('id', $item['sale_item_id'])->first();

                if (!$saleItem) {
                    throw new \Exception("Sale item not found belonging to this sale.");
                }

                // Check already returned quantity
                $previouslyReturned = SalesReturnItem::where('sale_item_id', $item['sale_item_id'])->sum('quantity');

                if (($previouslyReturned + $item['quantity']) > $saleItem->quantity) {
                    throw new \Exception("Cannot return more than sold quantity for item.");
                }
            }

            $returnId = Str::ulid();
            $accountId = app(TenantContext::class)->getAccountId();

            // 1. Create Return Header
            $salesReturn = SalesReturn::create([
                'id' => $returnId,
                'sale_id' => $sale->id,
                // 'account_id' => handled via UsesTenant or explicit?
                'account_id' => $accountId,
                'user_id' => $user->id,
                'refund_amount' => $returnData['refund_amount'],
                'refund_method' => $returnData['refund_method'] ?? 'cash',
                'reason' => $returnData['reason'],
                'returned_at' => now(),
            ]);

            $returnItems = [];

            foreach ($returnData['items'] as $item) {
                $saleItem = $sale->items->where('id', $item['sale_item_id'])->first();

                // Calculate refund amount strictly proportional if not provided?
                // Or trust frontend? Trust frontend but maybe validate cap.
                // For now, let's assume simple unit price refund
                $refundAmount = $saleItem->price * $item['quantity']; // Simple calculation

                $returnItem = SalesReturnItem::create([
                    'id' => Str::ulid(),
                    'return_id' => $returnId,
                    'sale_item_id' => $saleItem->id,
                    'quantity' => $item['quantity'],
                    'refund_amount' => $refundAmount,
                    'is_restocked' => $item['restock'] ?? false,
                    'condition' => $item['condition'] ?? 'Good',
                ]);

                $returnItems[] = $returnItem;

                // Restock Inventory if requested
                if ($item['restock']) {
                    Inventory::where('id', $saleItem->inventory_id)
                        ->increment('quantity_on_hand', $item['quantity']);

                    // Explicit Audit Logging
                    \App\Models\AuditTrail::create([
                        'id' => Str::ulid(),
                        'account_id' => $accountId,
                        'entity_type' => 'inventory',
                        'entity_id' => $saleItem->inventory_id,
                        'action' => 'RETURN_RESTOCK',
                        'actor_user_id' => $user->id,
                        'metadata' => [
                            'return_id' => $returnId,
                            'sale_id' => $sale->id,
                            'quantity' => $item['quantity'],
                            'reason' => $returnData['reason'],
                        ],
                        'timestamp' => now(),
                    ]);
                }
            }

            // Emit Event
            $eventData = [
                'account_id' => $accountId,
                'device_id' => request()->header('X-Device-Id') ?? (Device::value('device_id') ?? 'unknown'),
                'actor_user_id' => $user->id,
                'event_type' => 'SALE_RETURNED',
                'event_payload' => [
                    'return_id' => $returnId,
                    'sale_id' => $sale->id,
                    'refund_amount' => $salesReturn->refund_amount,
                    'items_count' => count($returnItems),
                    'returned_at' => now()->toIso8601String(),
                ]
            ];

            $this->ledger->emitEvent($eventData);

            return $salesReturn;
        });
    }

    /**
     * Restock a return item that wasn't restocked initially.
     */
    public function restockReturnItem(string $returnItemId, $user)
    {
        return DB::transaction(function () use ($returnItemId, $user) {
            $returnItem = SalesReturnItem::with(['saleItem', 'salesReturn'])->findOrFail($returnItemId);

            // Check if already restocked
            if ($returnItem->is_restocked) {
                throw new \Exception("This item has already been restocked.");
            }

            $saleItem = $returnItem->saleItem;
            $accountId = app(TenantContext::class)->getAccountId();

            // Restock the inventory
            Inventory::where('id', $saleItem->inventory_id)
                ->increment('quantity_on_hand', $returnItem->quantity);

            // Update the return item status
            $returnItem->update(['is_restocked' => true]);

            // Create audit log
            \App\Models\AuditTrail::create([
                'id' => Str::ulid(),
                'account_id' => $accountId,
                'entity_type' => 'inventory',
                'entity_id' => $saleItem->inventory_id,
                'action' => 'RETURN_RESTOCK',
                'actor_user_id' => $user->id,
                'metadata' => [
                    'return_id' => $returnItem->return_id,
                    'return_item_id' => $returnItem->id,
                    'sale_id' => $returnItem->salesReturn->sale_id,
                    'quantity' => $returnItem->quantity,
                    'restocked_later' => true,
                ],
                'timestamp' => now(),
            ]);

            return $returnItem;
        });
    }
}
