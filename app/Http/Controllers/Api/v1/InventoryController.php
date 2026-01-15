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
            'account_id' => $request->header('X-Account-Id'), // Example of context extraction
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
        $accountId = $request->header('X-Account-Id') ?? $request->input('account_id');

        // Dynamic Pagination Limit
        $perPage = (int) $request->input('per_page', 15);
        if (!in_array($perPage, [10, 20, 50, 100, 200])) {
            $perPage = 15;
        }

        $stocks = $this->inventoryService->getStockLevels($accountId, $perPage);

        if ($request->wantsJson()) {
            return response()->json(['data' => $stocks]);
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
            'inventory_id' => \Illuminate\Support\Str::uuid()
        ]));

        if ($request->wantsJson()) {
            return response()->json($inventory, 201);
        }

        return redirect()->back()->with('success', 'Stock added');
    }
}
