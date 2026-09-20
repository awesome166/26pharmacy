<?php

namespace App\Services;

use App\Services\EventLedgerService;
use App\Services\AccountingService;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Inventory;
use App\Models\Device;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use AbacPermissions\Tenancy\TenantContext;

class ReturnService
{
    protected $ledger;
    protected $accounting;

    public function __construct(EventLedgerService $ledger, AccountingService $accounting)
    {
        $this->ledger = $ledger;
        $this->accounting = $accounting;
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
            $deviceId = $returnData['device_id'] ?? request()->header('X-Device-Id') ?? config('sync.client_id')
                ?? Device::where('account_id', $accountId)->where('trust_status', 'active')->value('device_id');
            $device = Device::where('device_id', $deviceId)->where('account_id', $accountId)->firstOrFail();
            if ((string) $sale->branch_id !== (string) $device->branch_id) {
                throw new \RuntimeException('Returns must be processed by a device in the original sale branch.');
            }

            $calculatedRefundAmount = 0.0;
            foreach ($returnData['items'] as $item) {
                $saleItem = $sale->items->firstWhere('id', $item['sale_item_id']);
                $taxRefund = $saleItem->quantity > 0
                    ? ((int) $item['quantity'] / (int) $saleItem->quantity) * (float) $saleItem->tax_amount
                    : 0;
                $calculatedRefundAmount += ((float) $saleItem->price * (int) $item['quantity']) + $taxRefund;
            }
            $calculatedRefundAmount = round($calculatedRefundAmount, 2);

            // 1. Create Return Header
            $salesReturn = SalesReturn::create([
                'id' => $returnId,
                'sale_id' => $sale->id,
                // 'account_id' => handled via UsesTenant or explicit?
                'account_id' => $accountId,
                'branch_id' => $device->branch_id,
                'user_id' => $user->id,
                'refund_amount' => $calculatedRefundAmount,
                'refund_method' => $returnData['refund_method'] ?? 'cash',
                'reason' => $returnData['reason'],
                'returned_at' => now(),
            ]);

            $returnItems = [];
            $eventItems = [];

            $totalRefundAmount = 0;
            $totalTaxRefund = 0;

            foreach ($returnData['items'] as $item) {
                $saleItem = $sale->items->where('id', $item['sale_item_id'])->first();

                // Calculate refund amount strictly proportional if not provided?
                // Or trust frontend? Trust frontend but maybe validate cap.
                // For now, let's assume simple unit price refund
                $baseRefund = (float) $saleItem->price * (int) $item['quantity'];

                // Calculate prorated tax refund
                // Formula: (Returned Qty / Original Qty) * Original Tax Amount
                $taxRefund = 0;
                if ($saleItem->quantity > 0) {
                     $taxRefund = ($item['quantity'] / $saleItem->quantity) * $saleItem->tax_amount;
                }
                $totalTaxRefund += $taxRefund;
                $refundAmount = round($baseRefund + $taxRefund, 2);
                $totalRefundAmount += $refundAmount;

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
                $eventItems[] = [
                    'id' => (string) $returnItem->id, 'sale_item_id' => (string) $saleItem->id,
                    'inventory_id' => (string) $saleItem->inventory_id, 'quantity' => (int) $item['quantity'],
                    'refund_amount' => (float) $refundAmount, 'is_restocked' => (bool) ($item['restock'] ?? false),
                    'condition' => $item['condition'] ?? 'Good',
                ];

                // Update SaleItem return status
                $saleItem->increment('return_quantity', $item['quantity']);
                $saleItem->update([
                    'is_returned' => true, // Mark as having returns
                    'return_date' => now(),
                ]);

                // Restock Inventory if requested
                if ($item['restock'] ?? false) {
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

            // Update Sale return totals (preserve original sale amounts for audit trail)
            $sale->increment('total_returned_amount', $totalRefundAmount);

            // Emit Event
            $eventData = [
                'account_id' => $accountId,
                'device_id' => $deviceId,
                'actor_user_id' => $user->id,
                'event_type' => 'SALE_RETURNED',
                'project_locally' => false,
                'event_payload' => [
                    'return_id' => $returnId,
                    'sale_id' => $sale->id,
                    'refund_amount' => $salesReturn->refund_amount,
                    'refund_method' => $salesReturn->refund_method,
                    'reason' => $salesReturn->reason,
                    'user_id' => $user->id,
                    'items' => $eventItems,
                    'returned_at' => now()->toIso8601String(),
                ]
            ];

            $this->ledger->emitEvent($eventData);

            if ((bool) SystemSetting::getValue('accounting_enabled', false)) {
                $this->accounting->recordSaleReturn($salesReturn, $accountId, $user->id ?? null);
            }

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
            $device = app(DeviceContextService::class)->currentDevice((string) $accountId);
            if ((string) $returnItem->salesReturn->branch_id !== (string) $device->branch_id) {
                throw new \RuntimeException('This return belongs to a different branch.');
            }

            // Restock the inventory
            Inventory::where('id', $saleItem->inventory_id)
                ->increment('quantity_on_hand', $returnItem->quantity);

            // Update the return item status
            $returnItem->update(['is_restocked' => true]);

            $this->ledger->emitEvent([
                'account_id' => $accountId,
                'device_id' => $device->device_id,
                'actor_user_id' => $user->id,
                'event_type' => 'STOCK_ADJUSTED',
                'project_locally' => false,
                'event_payload' => [
                    'inventory_id' => $saleItem->inventory_id,
                    'batch_id' => $saleItem->batch_id,
                    'quantity_change' => (int) $returnItem->quantity,
                    'reason' => 'RETURN_RESTOCKED_LATER',
                    'return_id' => $returnItem->return_id,
                ],
            ]);

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
