<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class ReportController extends Controller
{
    protected $reportingService;

    public function __construct(ReportingService $reportingService)
    {
        $this->reportingService = $reportingService;
    }

    /**
     * Fetch daily sales summary (Read Model).
     */
    public function dailySales(Request $request)
    {
        $branchId = $request->header('X-Branch-Id') ?? $request->user()->branch_id;
        $date = $request->query('date', now()->toDateString());

        $summary = $this->reportingService->getDailySummary($branchId, $date);

        if ($request->wantsJson()) {
            return response()->json(['data' => $summary]);
        }

        return Inertia::render('Reports/DailySales', ['summary' => $summary]);
    }

    /**
     * Export regulatory-grade ledger snapshots.
     */
    public function exportLedger(Request $request)
    {
        $path = $this->reportingService->exportRegulatoryData(
            $request->header('X-Tenant-Id'),
            $request->query('start_date'),
            $request->query('end_date')
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Export initiated',
                'download_url' => asset($path)
            ]);
        }

        return Inertia::render('Reports/Export', ['path' => $path]);
    }
}
