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
        $query = $request->query('query');
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
        // Emit formal event to ledger
        $event = $this->ledger->emitEvent([
            'account_id' => app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId(),
            'branch_id' => $request->branch_id,
            'device_id' => $request->header('X-Device-Id'),
            'event_type' => 'STOCK_ADJUSTED',
            'event_payload' => [
                'batch_id' => $request->batch_id,
                'change' => $request->quantity_change,
                'reason' => $request->reason
            ]
        ]);

        AuditEventJob::dispatch([
            'entity_type' => 'inventory',
            'entity_id' => $request->batch_id,
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
            'account_id' => 'required|string|exists:accounts,account_id',
            'branch_id' => 'required|string|exists:branches,branch_id',
            'drug_id' => 'required|string|exists:drugs,drug_id',
            'batch_id' => 'required|string|exists:batches,batch_id',
            'selling_price' => 'required|numeric',
            'quantity_on_hand' => 'required|integer|min:1',
        ]);

        $inventory = \App\Models\Inventory::create(array_merge($validated, [
            'inventory_id' => \Illuminate\Support\Str::ulid()
        ]));

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
