<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StoreController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function index(Request $request)
    {
        $branchId = $request->header('X-Branch-Id') ?? $request->user()->branch_id;

        // Fetch inventory for the store list (can leverage existing service)
        // Perhaps we want a specific "store listing" method later, but getStockLevels works.
        // We might want more per page for the POS.
        $perPage = (int) $request->input('per_page', 50);

        $inventory = $this->inventoryService->getStockLevels($branchId, $perPage);

        return Inertia::render('Store/Index', [
            'inventory' => $inventory,
            'filters' => $request->only(['search', 'per_page']),
            // Pass necessary context for the invoice
            'user' => $request->user(),
            'branch_id' => $branchId,
        ]);
    }
}
