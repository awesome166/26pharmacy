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
        abort_if((string) $inventory->account_id !== (string) $accountId, 403);

        if ($request->boolean('remove_from_inventory')) {
            $inventory->update([
                'is_active' => false,
                'quantity_on_hand' => 0,
            ]);

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
        if ($quantityChange !== 0) {
            $newQty = (int) $inventory->quantity_on_hand + $quantityChange;
            if ($newQty < 0) {
                return back()->withErrors(['quantity_change' => 'Adjustment cannot result in negative stock.']);
            }
            $payload['quantity_on_hand'] = $newQty;
        }

        if (empty($payload) && $quantityChange === 0) {
            return back()->withErrors(['adjustment' => 'No changes detected. Update at least one field.']);
        }

        if (!empty($payload)) {
            $inventory->update($payload);
        }

        // Emit formal event to ledger
        $event = $this->ledger->emitEvent([
            'account_id' => $accountId,
            'device_id' => $request->header('X-Device-Id'),
            'event_type' => 'STOCK_ADJUSTED',
            'event_payload' => [
                'inventory_id' => $inventory->id,
                'batch_id' => $inventory->batch_id,
                'change' => $quantityChange,
                'reason' => $request->reason,
                'location' => $payload['location'] ?? $inventory->location,
                'drug_id' => $payload['drug_id'] ?? $inventory->drug_id,
                'selling_price' => $payload['selling_price'] ?? $inventory->selling_price,
                'cost_price' => $payload['cost_price'] ?? $inventory->cost_price,
            ]
        ]);

        AuditEventJob::dispatch([
            'entity_type' => 'inventory',
            'entity_id' => $inventory->id,
            'action' => 'STOCK_ADJUSTED',
            'metadata' => ['event_id' => $event->event_id]
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Stock adjustment ledgered',
                'event_id' => $event->event_id
            ], 202);
        }

        return redirect()->back()->with('success', 'Stock adjustment ledgered');
    }

    public function index(Request $request)
    {
        // Dynamic Pagination Limit
        $perPage = (int) $request->input('per_page', 15);
        if (!in_array($perPage, [10, 20, 50, 100, 200])) {
            $perPage = 15;
        }

        $search = $request->input('search');

        // Use search if provided, otherwise get all stock levels
        if ($search) {
            $stocks = $this->inventoryService->searchDrugs($search, $perPage);
        } else {
            $stocks = $this->inventoryService->getStockLevels($perPage);
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

        $inventory = DB::transaction(function () use ($validated, $accountId) {
            $location = $validated['location'] ?? 'Shelf A';

            $existing = Inventory::query()
                ->where('account_id', $accountId)
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

                return $existing->fresh();
            }

            return Inventory::create([
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'account_id' => $accountId,
                'drug_id' => $validated['drug_id'],
                'batch_id' => $validated['batch_id'],
                'selling_price' => $validated['selling_price'],
                'cost_price' => $validated['cost_price'] ?? $validated['selling_price'],
                'quantity_on_hand' => $validated['quantity_on_hand'],
                'reorder_level' => 10,
                'location' => $location,
                'is_active' => true,
            ]);
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

        $expiredStock = $this->inventoryService->getExpiredStock($perPage, $daysThreshold);

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
        $deviceId = $request->header('X-Device-Id') ?? 'unknown';

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
