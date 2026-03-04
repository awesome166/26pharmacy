<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use App\Services\SaleService;
use App\Models\TaxRate;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StoreController extends Controller
{
    protected $storeInventoryService;
    protected $saleService;

    public function __construct(\App\Services\StoreInventoryService $storeInventoryService, SaleService $saleService)
    {
        $this->storeInventoryService = $storeInventoryService;
        $this->saleService = $saleService;
    }

    public function index(Request $request)
    {
        // Get account ID from the package's TenantContext
        // $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        // Fetch optimized inventory (handles search, caching, and top-selling products)
        $perPage = (int) $request->input('per_page', 50);
        $search = $request->input('search');

        $inventory = $this->storeInventoryService->getStoreInventory($perPage, $search);

        // Flatten nested drug/batch relations into flat fields for the frontend
        $transformedInventory = $inventory->through(function ($item) {
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
                'drug_class' => $item->drug?->drug_class,
                'is_active' => $item->is_active,
            ];
        });

        // If this is a JSON request (search/pagination), return JSON
        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'data' => $transformedInventory
            ]);
        }

        // Get account ID for tax scoping
        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        // Render with all data as Inertia props (single request)
        return Inertia::render('Store/Index', [
            'inventory' => $transformedInventory,
            'filters' => $request->only(['search', 'per_page']),
            'user' => $request->user(),
            'taxes' => TaxRate::where('is_active', true)
                ->where('account_id', $accountId)
                ->get(),
            'recentSales' => $this->saleService->getRecentSales(10),
        ]);
    }

    public function findCustomerByPhone(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:50'],
        ]);

        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        $customer = Customer::query()
            ->where('account_id', $accountId)
            ->where('phone', $validated['phone'])
            ->latest('updated_at')
            ->first();

        return response()->json([
            'data' => $customer,
        ]);
    }
}
