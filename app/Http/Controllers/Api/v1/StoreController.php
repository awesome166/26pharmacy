<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StoreController extends Controller
{
    protected $storeInventoryService;

    public function __construct(\App\Services\StoreInventoryService $storeInventoryService)
    {
        $this->storeInventoryService = $storeInventoryService;
    }

    public function index(Request $request)
    {
        // Get account ID from the package's TenantContext
        // $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        // Fetch optimized inventory (handles search, caching, and top-selling products)
        $perPage = (int) $request->input('per_page', 50);
        $search = $request->input('search');

        $inventory = $this->storeInventoryService->getStoreInventory($perPage, $search);

        // If this is a JSON request (from Axios), return JSON
        if ($request->wantsJson() || $request->expectsJson()) {
            // Transform the inventory to include drug and batch data
            $transformedData = $inventory->through(function ($item) {
                return [
                    'id' => $item->id,
                    'drug_id' => $item->drug_id,
                    'batch_id' => $item->batch_id,
                    'drug_name' => $item->drug?->name ?? 'Unknown Drug',
                    'strength' => $item->drug?->strength ?? '',
                    'expiry_date' => $item->batch?->expiry_date ?? 'N/A',
                    'quantity_on_hand' => $item->quantity_on_hand,
                    'selling_price' => $item->selling_price,
                    'cost_price' => $item->cost_price,
                    'location' => $item->location,
                    'drug_class' => $item->drug?->drug_class, // Added for tax calculation
                    'is_active' => $item->is_active,
                ];
            });

            return response()->json([
                'data' => $transformedData
            ]);
        }

        // Otherwise, render the Inertia page
        return Inertia::render('Store/Index', [
            'inventory' => $inventory,
            'filters' => $request->only(['search', 'per_page']),
            // Pass necessary context for the invoice
            'user' => $request->user(),
            // 'account_id' => $accountId,
        ]);
    }
}
