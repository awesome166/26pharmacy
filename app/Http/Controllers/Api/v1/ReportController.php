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
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'ulid'],
        ]);
        $date = $validated['date'] ?? now()->toDateString();
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $branchId = $validated['branch_id'] ?? app(\App\Services\DeviceContextService::class)
            ->currentBranchId($accountId, $request->header('X-Device-Id'));
        abort_unless(\Illuminate\Support\Facades\DB::table('branches')
            ->where('account_id', $accountId)->where('branch_id', $branchId)->exists(), 403);

        $summary = $this->reportingService->getDailySummary($date, $branchId);

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json($summary);
        }

        return Inertia::render('Reports/DailySales', ['summary' => $summary]);
    }

    /**
     * Export regulatory-grade ledger snapshots.
     */
    public function exportLedger(Request $request)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'branch_id' => ['nullable', 'ulid'],
        ]);
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $branchId = $validated['branch_id'] ?? app(\App\Services\DeviceContextService::class)
            ->currentBranchId($accountId, $request->header('X-Device-Id'));
        abort_unless(\Illuminate\Support\Facades\DB::table('branches')
            ->where('account_id', $accountId)->where('branch_id', $branchId)->exists(), 403);

        $path = $this->reportingService->exportRegulatoryData(
            $validated['start_date'],
            $validated['end_date'],
            $branchId,
        );

        return response()->download($path, 'regulatory-report-'.$validated['start_date'].'-to-'.$validated['end_date'].'.csv')
            ->deleteFileAfterSend(true);
    }
}
