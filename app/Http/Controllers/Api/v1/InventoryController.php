<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use App\Services\EventLedgerService;
use App\Http\Requests\Inventory\AdjustStockRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Jobs\AuditEventJob;
use Inertia\Inertia;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use App\Services\StoreInventoryService;

class InventoryController extends Controller
{
    protected $inventoryService;
    protected $ledger;

    public function __construct(InventoryService $inventoryService, EventLedgerService $ledger)
    {
        $this->inventoryService = $inventoryService;
        $this->ledger = $ledger;
    }

    public function search(Request $request)
    {
        $query = (string) $request->query('query', '');
        $results = $this->inventoryService->searchDrugs($query);

        if ($request->wantsJson()) {
            return response()->json(['data' => $results]);
        }

        return Inertia::render('Inventory/Search', ['results' => $results]);
    }

    /**
     * Adjust stock levels via an auditable event.
     */
    public function adjustStock(AdjustStockRequest $request)
    {
        $inventory = Inventory::query()->with('batch')->findOrFail($request->inventory_id);
        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $deviceId = $request->header('X-Device-Id') ?? config('sync.client_id')
            ?? \App\Models\Device::where('account_id', $accountId)->where('trust_status', 'active')->value('device_id');
        abort_if((string) $inventory->account_id !== (string) $accountId, 403);

        if ($request->boolean('remove_from_inventory')) {
            DB::transaction(function () use ($inventory, $request) {
                $inventory->update([
                    'is_active' => false,
                    'quantity_on_hand' => 0,
                ]);
                app(\App\Services\DomainEventService::class)->record(
                    'INVENTORY_DEACTIVATED', ['id' => (string) $inventory->id], $request->user()?->id, $inventory->branch_id,
                );
            });

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Drug removed from inventory.']);
            }

            return redirect()->back()->with('success', 'Drug removed from inventory.');
        }

        $payload = [];
        if ($request->filled('location')) {
            $payload['location'] = $request->string('location')->toString();
        }
        if ($request->filled('drug_id')) {
            $payload['drug_id'] = $request->string('drug_id')->toString();
        }
        if ($request->filled('selling_price')) {
            $payload['selling_price'] = (float) $request->selling_price;
        }
        if ($request->filled('cost_price')) {
            $payload['cost_price'] = (float) $request->cost_price;
        }

        $quantityChange = (int) ($request->quantity_change ?? 0);
        if (empty($payload) && $quantityChange === 0) {
            return back()->withErrors(['adjustment' => 'No changes detected. Update at least one field.']);
        }

        $event = DB::transaction(function () use (&$inventory, $payload, $quantityChange, $accountId, $deviceId, $request) {
            $inventory = Inventory::query()->whereKey($inventory->id)->lockForUpdate()->firstOrFail();
            $updates = $payload;
            if ($quantityChange !== 0) {
                $newQty = (int) $inventory->quantity_on_hand + $quantityChange;
                if ($newQty < 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'quantity_change' => 'Adjustment cannot result in negative stock.',
                    ]);
                }
                $updates['quantity_on_hand'] = $newQty;
            }
            $inventory->update($updates);

            return $this->ledger->emitEvent([
                'account_id' => $accountId,
                'device_id' => $deviceId,
                'actor_user_id' => $request->user()?->id,
                'event_type' => 'STOCK_ADJUSTED',
                'project_locally' => false,
                'event_payload' => [
                    'inventory_id' => $inventory->id,
                    'batch_id' => $inventory->batch_id,
                    'quantity_change' => $quantityChange,
                    'reason' => $request->reason,
                    'location' => $updates['location'] ?? $inventory->location,
                    'drug_id' => $updates['drug_id'] ?? $inventory->drug_id,
                    'selling_price' => $updates['selling_price'] ?? $inventory->selling_price,
                    'cost_price' => $updates['cost_price'] ?? $inventory->cost_price,
                ],
            ]);
        });

        AuditEventJob::dispatch([
            'entity_type' => 'inventory',
            'entity_id' => $inventory->id,
            'action' => 'STOCK_ADJUSTED',
            'metadata' => ['event_id' => $event->id]
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Stock adjustment ledgered',
                'event_id' => $event->id
            ], 202);
        }

        return redirect()->back()->with('success', 'Stock adjustment ledgered');
    }

    public function index(Request $request, ?string $branch = null)
    {
        // Dynamic Pagination Limit
        $perPage = (int) $request->input('per_page', 15);
        if (!in_array($perPage, [10, 20, 50, 100, 200])) {
            $perPage = 15;
        }

        $search = $request->input('search');
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $branchId = $branch ?: app(\App\Services\DeviceContextService::class)
            ->currentBranchId($accountId, $request->header('X-Device-Id'));
        abort_unless(DB::table('branches')->where('account_id', $accountId)
            ->where('branch_id', $branchId)->exists(), 403);

        // Use search if provided, otherwise get all stock levels
        if ($search) {
            $stocks = $this->inventoryService->searchDrugs($search, $perPage, $branchId);
        } else {
            $stocks = $this->inventoryService->getStockLevels($perPage, $branchId);
        }

        if ($request->wantsJson()) {
            // Transform the data to include drug and batch information
            $transformedData = $stocks->through(function ($item) {
                return [
                    'id' => $item->id,
                    'inventory_id' => $item->id,
                    'drug_id' => $item->drug_id,
                    'batch_id' => $item->batch_id,
                    'drug_name' => $item->drug?->name ?? 'Unknown Drug',
                    'strength' => $item->drug?->strength ?? '',
                    'lot_number' => $item->batch?->lot_number ?? null,
                    'batch_number' => $item->batch?->lot_number ?? null,
                    'expiry_date' => $item->batch?->expiry_date ?? null,
                    'quantity_on_hand' => $item->quantity_on_hand,
                    'selling_price' => $item->selling_price,
                    'cost_price' => $item->cost_price,
                    'reorder_level' => $item->reorder_level,
                    'location' => $item->location,
                    'is_active' => $item->is_active,
                ];
            });

            return response()->json(['data' => $transformedData]);
        }

        return Inertia::render('Inventory/Index', ['inventory' => $stocks, 'filters' => $request->only(['search', 'per_page'])]);
    }

    public function show(Request $request, string $id)
    {
        $inventory = Inventory::query()->with(['drug', 'batch'])->findOrFail($id);
        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        abort_if((string) $inventory->account_id !== (string) $accountId, 403);

        if ($request->wantsJson()) {
            return response()->json(['data' => $inventory]);
        }

        return Inertia::render('Inventory/Show', ['inventory' => $inventory]);
    }

    public function update(Request $request, string $id)
    {
        $inventory = Inventory::query()->with('batch')->findOrFail($id);
        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        abort_if((string) $inventory->account_id !== (string) $accountId, 403);

        $validated = $request->validate([
            'selling_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($inventory, $validated, $request) {
            $inventory->update($validated);
            app(\App\Services\DomainEventService::class)->record(
                'INVENTORY_UPSERTED', ['inventory' => $inventory->fresh()->attributesToArray()], $request->user()?->id, $inventory->branch_id,
            );
        });

        if ($request->wantsJson()) {
            return response()->json(['data' => $inventory->fresh()->load(['drug', 'batch'])]);
        }

        return redirect()->back()->with('success', 'Inventory updated');
    }

    public function destroy(Request $request, string $id)
    {
        $inventory = Inventory::findOrFail($id);
        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        abort_if((string) $inventory->account_id !== (string) $accountId, 403);

        DB::transaction(function () use ($inventory, $request) {
            $inventory->update(['is_active' => false, 'quantity_on_hand' => 0]);
            app(\App\Services\DomainEventService::class)->record(
                'INVENTORY_DEACTIVATED', ['id' => (string) $inventory->id], $request->user()?->id, $inventory->branch_id,
            );
        });

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Inventory record deactivated']);
        }

        return redirect()->back()->with('success', 'Inventory deactivated');
    }

    /**
     * Store new inventory record
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'drug_id' => 'required|ulid|exists:drugs,id',
            'batch_id' => 'required|ulid|exists:batches,id',
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
            'quantity_on_hand' => 'required|integer|min:1',
        ]);

        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        abort_if(!$accountId, 422, 'Select an account before adding stock.');
        $branchId = $request->input('branch_id') ?? \App\Models\Device::where('account_id', $accountId)
            ->where('trust_status', 'active')->value('branch_id');
        abort_if(!$branchId, 422, 'No active branch is configured for this device.');

        $inventory = DB::transaction(function () use ($validated, $accountId, $branchId, $request) {
            $location = $validated['location'] ?? 'Shelf A';

            $existing = Inventory::query()
                ->where('account_id', $accountId)
                ->where('branch_id', $branchId)
                ->where('drug_id', $validated['drug_id'])
                ->where('batch_id', $validated['batch_id'])
                ->where('location', $location)
                ->first();

            if ($existing) {
                $existing->update([
                    'quantity_on_hand' => (int) $existing->quantity_on_hand + (int) $validated['quantity_on_hand'],
                    'selling_price' => $validated['selling_price'],
                    'cost_price' => $validated['cost_price'] ?? $validated['selling_price'],
                    'is_active' => true,
                ]);

                $inventory = $existing->fresh();
            } else {
                $inventory = Inventory::create([
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'account_id' => $accountId,
                    'branch_id' => $branchId,
                    'drug_id' => $validated['drug_id'],
                    'batch_id' => $validated['batch_id'],
                    'selling_price' => $validated['selling_price'],
                    'cost_price' => $validated['cost_price'] ?? $validated['selling_price'],
                    'quantity_on_hand' => $validated['quantity_on_hand'],
                    'reorder_level' => 10,
                    'location' => $location,
                    'is_active' => true,
                ]);
            }

            app(\App\Services\DomainEventService::class)->record(
                'INVENTORY_UPSERTED', ['inventory' => $inventory->attributesToArray()], $request->user()?->id, $inventory->branch_id,
            );

            return $inventory;
        });

        app(StoreInventoryService::class)->invalidateCache($accountId);

        if ($request->wantsJson()) {
            return response()->json($inventory, 201);
        }

        return redirect()->back()->with('success', 'Stock added');
    }

    /**
     * Get expired or expiring stock.
     */
    public function expired(Request $request)
    {
        $daysThreshold = (int) $request->input('days_threshold', 0);
        $perPage = (int) $request->input('per_page', 15);

        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $branchId = app(\App\Services\DeviceContextService::class)
            ->currentBranchId($accountId, $request->header('X-Device-Id'));
        $expiredStock = $this->inventoryService->getExpiredStock($perPage, $daysThreshold, $branchId);

        if ($request->wantsJson()) {
             // Transform the data consistent with index method
             $transformedData = $expiredStock->through(function ($item) {
                return [
                    'id' => $item->id,
                    'inventory_id' => $item->id,
                    'drug_id' => $item->drug_id,
                    'batch_id' => $item->batch_id,
                    'drug_name' => $item->drug?->name ?? 'Unknown Drug',
                    'strength' => $item->drug?->strength ?? '',
                    'lot_number' => $item->batch?->lot_number ?? null,
                    'expiry_date' => $item->batch?->expiry_date ?? null,
                    'quantity_on_hand' => $item->quantity_on_hand,
                    'selling_price' => $item->selling_price,
                    'cost_price' => $item->cost_price,
                    'location' => $item->location,
                ];
            });
            return response()->json(['data' => $transformedData]);
        }

        return Inertia::render('Inventory/Expired', [
            'expiredStock' => $expiredStock,
            'filters' => $request->only(['days_threshold', 'per_page'])
        ]);
    }

    /**
     * Process expired stock (Write-off).
     */
    public function processExpired(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.batch_id' => 'required|string|exists:batches,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $items = $request->input('items');
        $processedCount = 0;
        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $deviceId = app(\App\Services\DeviceContextService::class)
            ->currentDevice((string) $accountId, $request->header('X-Device-Id'))->device_id;

        foreach ($items as $item) {
             // Use Service to ensure consistent event structure
             // We are reducing stock, so quantity change is negative
             $this->inventoryService->adjustStock(
                 $item['batch_id'],
                 -1 * abs($item['quantity']),
                 'EXPIRED',
                 ['device_id' => $deviceId, 'user_id' => $request->user()->id]
             );

             // Also log audit event
             AuditEventJob::dispatch([
                'entity_type' => 'inventory',
                'entity_id' => $item['batch_id'],
                'action' => 'STOCK_EXPIRED_WRITEOFF',
                'metadata' => ['quantity' => $item['quantity'], 'account_id' => $accountId]
            ]);

            $processedCount++;
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => "Successfully processed {$processedCount} expired items."]);
        }

        return redirect()->back()->with('success', "Processed {$processedCount} expired items.");
    }
}
