<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class ReturnController extends Controller
{
    protected $returnService;

    public function __construct(ReturnService $returnService)
    {
        $this->returnService = $returnService;
    }

    /**
     * Get all returns with related data.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = $request->input('per_page', 15);

        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $branchId = app(\App\Services\DeviceContextService::class)
            ->currentBranchId($accountId, $request->header('X-Device-Id'));
        $query = \App\Models\SalesReturn::with([
            'user:id,name,email',
            'sale:id,created_at',
            'items.saleItem.inventory.drug:id,name,generic_name'
        ])->where('branch_id', $branchId)
        ->orderBy('returned_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('sale_id', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $paginatedReturns = $query->paginate($perPage)
            ->through(function ($return) {
                return [
                    'id' => $return->id,
                    'sale_id' => $return->sale_id,
                    'user' => $return->user,
                    'refund_amount' => $return->refund_amount,
                    'refund_method' => $return->refund_method,
                    'reason' => $return->reason,
                    'returned_at' => $return->returned_at,
                    'items' => $return->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'drug_name' => $item->saleItem?->inventory?->drug?->name ?? 'Unknown',
                            'quantity' => $item->quantity,
                            'refund_amount' => $item->refund_amount,
                            'condition' => $item->condition,
                            'is_restocked' => $item->is_restocked,
                            'sale_item_id' => $item->sale_item_id,
                        ];
                    }),
                ];
            });

        if ($request->wantsJson()) {
            return response()->json($paginatedReturns);
        }

        return Inertia::render('Returns/Index', [
            'returns' => $paginatedReturns,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a new return.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sale_id' => 'required|string|exists:sales,id',
            'refund_amount' => 'nullable|numeric|min:0',
            'refund_method' => 'required|string',
            'reason' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|string|exists:sale_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.restock' => 'boolean',
            'items.*.condition' => 'string|nullable',
        ]);

        try {
            $return = $this->returnService->processReturn($validated, $request->user());

            if ($request->wantsJson()) {
                return response()->json($return, 201);
            }

            return redirect()->back()->with('success', 'Return processed successfully');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Restock a return item.
     */
    public function restock(Request $request, string $returnItemId): JsonResponse
    {
        try {
            $this->returnService->restockReturnItem($returnItemId, $request->user());

            return response()->json(['message' => 'Item restocked successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
