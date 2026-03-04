<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountingReportController extends Controller
{
    public function __construct(protected AccountingService $accounting)
    {
    }

    public function balanceSheet(Request $request)
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $date = $request->input('date', now()->toDateString());
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $reportData = $this->accounting->getBalanceSheet($accountId, $date);
        $incomeData = $this->accounting->getIncomeStatement($accountId, $from, $to);
        $trialData = $this->accounting->getTrialBalance($accountId, $date);

        return Inertia::render('Accounting/Reports/BalanceSheet', [
            'reportData' => $reportData,
            'incomeData' => $incomeData,
            'trialData' => $trialData,
            'filters' => [
                'date' => $date,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function incomeStatement(Request $request)
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $this->accounting->getIncomeStatement($accountId, $from, $to),
            ]);
        }

        return redirect()->route('accounting.reports.balance-sheet', ['from' => $from, 'to' => $to]);
    }

    public function trialBalance(Request $request)
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $date = $request->input('date', now()->toDateString());

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $this->accounting->getTrialBalance($accountId, $date),
            ]);
        }

        return redirect()->route('accounting.reports.balance-sheet', ['date' => $date]);
    }
}
