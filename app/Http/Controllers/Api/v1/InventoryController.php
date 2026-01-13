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
            'tenant_id' => $request->header('X-Tenant-Id'), // Example of context extraction
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

    public function index(string $branchId, Request $request)
    {
        $stocks = $this->inventoryService->getStockLevels($branchId);

        if ($request->wantsJson()) {
            return response()->json(['data' => $stocks]);
        }

        return Inertia::render('Inventory/Index', ['stocks' => $stocks]);
    }
}
