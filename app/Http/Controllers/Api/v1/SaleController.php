<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\SaleService;
use App\Http\Requests\Sale\FinalizeSaleRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Jobs\AuditEventJob;
use Inertia\Inertia;

class SaleController extends Controller
{
    protected $saleService;

    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
    }

    public function index(Request $request)
    {
        $branchId = $request->header('X-Branch-Id');
        $sales = $this->saleService->getAllSales($branchId);

        if ($request->wantsJson()) {
            return response()->json(['data' => $sales]);
        }

        return Inertia::render('Sales/Index', ['sales' => $sales]);
    }

    /**
     * Finalize a sale, emit events, and log for audit.
     */
    public function finalizeSale(FinalizeSaleRequest $request) // Removed JsonResponse return type
    {
        // Thin controller: orchestrates service and compliance jobs
        $eventReceipt = $this->saleService->processSale($request->validated());

        // Regulator-grade audit trail entry
        AuditEventJob::dispatch([
            'entity_type' => 'sale',
            'entity_id' => $eventReceipt->event_id,
            'action' => 'SALE_FINALIZED',
            'actor_user_id' => $request->user_id,
            'metadata' => [
                'branch_id' => $request->branch_id,
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
