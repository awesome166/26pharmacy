<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\SaleService;
use App\Http\Requests\Sale\FinalizeSaleRequest;
use Illuminate\Http\Request;
use App\Jobs\AuditEventJob;
use Inertia\Inertia;

use App\Services\ReturnService;
use Illuminate\Support\Facades\Log;

class SaleController extends Controller
{
    protected $saleService;

    protected $returnService;

    public function __construct(SaleService $saleService, ReturnService $returnService)
    {
        $this->saleService = $saleService;
        $this->returnService = $returnService;
    }


    public function index(Request $request)
    {
        // Dynamic Pagination Limit
        $perPage = (int) $request->input('per_page', 15);
        if (!in_array($perPage, [10, 20, 50, 100, 200])) {
            $perPage = 15;
        }

        // Extract filters
        $filters = [
            'search' => $request->input('search'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ];

        // Extract sort parameters
        $sortBy = $request->input('sort_by', 'finalized_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $branchId = app(\App\Services\DeviceContextService::class)
            ->currentBranchId($accountId, $request->header('X-Device-Id'));
        $sales = $this->saleService->getAllSales($perPage, $filters, $sortBy, $sortDirection, $branchId);

        if ($request->wantsJson()) {
            return response()->json(['data' => $sales]);
        }

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'filters' => $request->only(['search', 'per_page', 'start_date', 'end_date', 'sort_by', 'sort_direction'])
        ]);
    }

    /**
     * Finalize a sale, emit events, and log for audit.
     */
    public function store(FinalizeSaleRequest $request) // Removed JsonResponse return type
    {
        // Thin controller: orchestrates service and compliance jobs
        $saleData = $request->validated();
        $saleData['user_id'] = (string) $request->user()->id;
        $saleData['device_id'] = $request->header('X-Device-Id') ?: ($saleData['device_id'] ?? null);
        $eventReceipt = $this->saleService->processSale($saleData);

        Log::info('Sale processed', ['event_receipt' => $eventReceipt]);

        // Regulator-grade audit trail entry
        if ($eventReceipt) {
            AuditEventJob::dispatch([
                'entity_type' => 'sale',
                'entity_id' => $eventReceipt->event_id,
                'action' => 'SALE_FINALIZED',
                'actor_user_id' => $request->user()->id,
                'metadata' => [
                    'account_id' => app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId(),
                    'device_id' => $saleData['device_id'],
                    'total_amount' => $eventReceipt->sale->total_amount,
                ]
            ]);


                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'Sale finalized and ledgered',
                        'event_id' => $eventReceipt->event_id,
                        'hash' => $eventReceipt->hash,
                        'sale' => $eventReceipt->sale,
                    ], 201);
                }

                return redirect()->back()->with('success', 'Sale finalized');
        }else {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Sale not finalized'], 400);
            }
            abort(400);
        }
    }

    public function show(string $saleId, Request $request) // Added Request parameter and removed JsonResponse return type
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $branchId = app(\App\Services\DeviceContextService::class)
            ->currentBranchId($accountId, $request->header('X-Device-Id'));
        $sale = $this->saleService->getSale($saleId, $branchId);

        if (!$sale) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Sale not found'], 404);
            }
            abort(404);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $sale]);
        }

        return Inertia::render('Sales/Show', ['sale' => $sale]);
    }

    public function reverse(string $saleId, Request $request)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'items' => 'required|array',
            'items.*.sale_item_id' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'refund_amount' => 'nullable|numeric|min:0',
            'refund_method' => 'nullable|string',
            'items.*.restock' => 'boolean',
            'items.*.condition' => 'nullable|string|max:100',
        ]);

        $validated['sale_id'] = $saleId;

        $return = $this->returnService->processReturn($validated, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Sale reversed successfully',
                'return_id' => $return->id,
            ]);
        }

        return redirect()->back()->with('success', 'Sale reversed successfully');
    }
}
