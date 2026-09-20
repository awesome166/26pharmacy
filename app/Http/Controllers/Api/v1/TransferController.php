<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use App\Services\EventLedgerService;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TransferController extends Controller
{
    protected $inventoryService;
    protected $ledger;

    public function __construct(InventoryService $inventoryService, EventLedgerService $ledger)
    {
        $this->inventoryService = $inventoryService;
        $this->ledger = $ledger;
    }

    public function initiateTransfer(Request $request)
    {
        $validated = $request->validate([
            'from_inventory_id' => 'required|string|exists:inventory,id',
            'to_branch_id' => 'required|ulid|exists:branches,branch_id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:500',
        ]);

        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $deviceId = $request->header('X-Device-Id') ?? config('sync.client_id')
            ?? \App\Models\Device::where('account_id', $accountId)->where('trust_status', 'active')->value('device_id');
        $destination = DB::table('branches')->where('branch_id', $validated['to_branch_id'])
            ->where('account_id', $accountId)->where('is_active', true)->firstOrFail();

        DB::transaction(function () use ($validated, $accountId, $deviceId) {
            $source = Inventory::where('id', $validated['from_inventory_id'])
                ->where('account_id', $accountId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $source->branch_id === (string) $validated['to_branch_id']) {
                throw new \InvalidArgumentException('Source and destination branches must be different.');
            }

            if ($source->quantity_on_hand < $validated['quantity']) {
                throw new \Exception("Insufficient stock. Available: {$source->quantity_on_hand}, requested: {$validated['quantity']}");
            }

            $source->decrement('quantity_on_hand', $validated['quantity']);

            $destinationInventory = Inventory::where('account_id', $accountId)
                ->where('branch_id', $validated['to_branch_id'])
                ->where('drug_id', $source->drug_id)->where('batch_id', $source->batch_id)
                ->lockForUpdate()->first();
            if ($destinationInventory) {
                $destinationInventory->increment('quantity_on_hand', $validated['quantity']);
            } else {
                $destinationInventory = Inventory::create([
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'account_id' => $accountId, 'branch_id' => $validated['to_branch_id'],
                    'drug_id' => $source->drug_id, 'batch_id' => $source->batch_id,
                    'quantity_on_hand' => $validated['quantity'], 'selling_price' => $source->selling_price,
                    'cost_price' => $source->cost_price, 'is_active' => true,
                ]);
            }

            $this->ledger->emitEvent([
                'account_id' => $accountId,
                'device_id' => $deviceId,
                'actor_user_id' => $request->user()?->id,
                'event_type' => 'STOCK_TRANSFERRED',
                'project_locally' => false,
                'event_payload' => [
                    'from_inventory_id' => $source->id,
                    'drug_id' => $source->drug_id,
                    'batch_id' => $source->batch_id,
                    'quantity' => $validated['quantity'],
                    'destination_inventory_id' => $destinationInventory->id,
                    'selling_price' => $source->selling_price,
                    'cost_price' => $source->cost_price,
                    'to_branch_id' => $validated['to_branch_id'],
                    'reason' => $validated['reason'] ?? null,
                    'transferred_at' => now()->toIso8601String(),
                ],
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Transfer completed']);
        }

        return redirect()->back()->with('success', 'Stock transfer completed');
    }
}
