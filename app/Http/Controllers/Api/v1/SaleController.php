<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\SaleService;
use App\Http\Requests\Sale\FinalizeSaleRequest;
use Illuminate\Http\Request;
use App\Jobs\AuditEventJob;
use Inertia\Inertia;

use Illuminate\Support\Facades\Log;

class SaleController extends Controller
{
    protected $saleService;

    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
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

        $sales = $this->saleService->getAllSales($perPage, $filters, $sortBy, $sortDirection);

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
        $eventReceipt = $this->saleService->processSale($request->validated());

        Log::info('Sale processed', ['event_receipt' => $eventReceipt]);

        // Regulator-grade audit trail entry
        if ($eventReceipt) {
            AuditEventJob::dispatch([
                'entity_type' => 'sale',
                'entity_id' => $eventReceipt->event_id,
                'action' => 'SALE_FINALIZED',
                'actor_user_id' => $request->user_id,
                'metadata' => [
                    'account_id' => $request->account_id,
                    'device_id' => $request->device_id,
                    'total_amount' => $request->subtotal
                ]
            ]);


                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'Sale finalized and ledgered',
                        'event_id' => $eventReceipt->event_id,
                        'hash' => $eventReceipt->hash
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
        $sale = $this->saleService->getSale($saleId);

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
}
